<?php
declare(strict_types=1);

$configuredEnvFile = getenv('DELIVERDESK_ENV_FILE');
$envFile = $configuredEnvFile ?: dirname(__DIR__, 3) . '/etc/deliverdesk.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3307';
    $name = getenv('DB_NAME') ?: 'deliverdesk';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    if ($user === '') {
        database_setup_required();
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->query('SELECT id FROM users LIMIT 0');
    } catch (PDOException $exception) {
        error_log('DeliverDesk database setup error: ' . $exception->getMessage());
        database_setup_required();
    }

    return $pdo;
}

function database_setup_required(): never
{
    if (PHP_SAPI === 'cli') {
        throw new RuntimeException('MySQL bağlantısını ve şema kurulumunu yapılandırın (.env, database/schema.sql).');
    }
    http_response_code(503);
    require dirname(__DIR__) . '/database/setup-required.php';
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.company_id, u.display_name, u.email, u.role, u.is_active, c.name AS company_name
         FROM users u LEFT JOIN companies c ON c.id = u.company_id WHERE u.id = ? LIMIT 1'
    );
    $statement->execute([$userId]);
    $user = $statement->fetch() ?: null;
    if (!$user || !(int)$user['is_active']) {
        unset($_SESSION['user_id']);
        $user = null;
    }

    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_role(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Bu sayfaya erişim yetkiniz yok.');
    }
}

function role_label(string $role): string
{
    return [
        'admin' => 'Yönetici',
        'manager' => 'Proje yöneticisi',
        'developer' => 'Uzman',
        'client' => 'Müşteri temsilcisi',
    ][$role] ?? 'Kullanıcı';
}

function status_label(string $status): string
{
    return [
        'waiting' => 'Bekliyor',
        'in_progress' => 'Devam ediyor',
        'review' => 'İncelemede',
        'completed' => 'Tamamlandı',
        'active' => 'Aktif',
        'paused' => 'Duraklatıldı',
    ][$status] ?? $status;
}

function priority_label(string $priority): string
{
    return ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'][$priority] ?? $priority;
}

function companies_for_user(array $user): array
{
    if ($user['role'] === 'client') {
        return [['id' => $user['company_id'], 'name' => $user['company_name']]];
    }
    if ($user['role'] === 'developer') {
        $statement = db()->prepare(
            'SELECT DISTINCT c.id, c.name FROM companies c
             JOIN projects p ON p.company_id = c.id
             JOIN project_members pm ON pm.project_id = p.id
             WHERE pm.user_id = ? ORDER BY c.name'
        );
        $statement->execute([$user['id']]);
        return $statement->fetchAll();
    }
    return db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();
}

function selected_company_id(array $user): ?int
{
    if ($user['role'] === 'client') {
        return $user['company_id'] ? (int)$user['company_id'] : null;
    }
    $selected = filter_var($_SESSION['workspace_company_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$selected) {
        return null;
    }
    foreach (companies_for_user($user) as $company) {
        if ((int)$company['id'] === $selected) {
            return $selected;
        }
    }
    unset($_SESSION['workspace_company_id']);
    return null;
}

function visible_projects(array $user): array
{
    $sql = 'SELECT p.*, c.name AS company_name, u.display_name AS manager_name,
                   COUNT(DISTINCT t.id) AS task_count,
                   COUNT(DISTINCT CASE WHEN t.status = "completed" THEN t.id END) AS completed_count
            FROM projects p
            JOIN companies c ON c.id = p.company_id
            LEFT JOIN users u ON u.id = p.manager_id
            LEFT JOIN tasks t ON t.project_id = p.id';
    $where = [];
    $params = [];

    if ($user['role'] === 'developer') {
        $sql .= ' JOIN project_members pm ON pm.project_id = p.id';
        $where[] = 'pm.user_id = ?';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'client') {
        $where[] = 'p.company_id = ? AND p.client_visible = 1';
        $params[] = $user['company_id'];
    }

    $companyId = selected_company_id($user);
    if ($companyId) {
        $where[] = 'p.company_id = ?';
        $params[] = $companyId;
    }
    if ($user['role'] === 'client') {
        $sql = str_replace('LEFT JOIN tasks t ON t.project_id = p.id', 'LEFT JOIN tasks t ON t.project_id = p.id AND t.client_visible = 1', $sql);
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY p.id, c.id, u.id ORDER BY p.updated_at DESC, p.id DESC';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function visible_tasks(array $user, string $search = '', string $status = ''): array
{
    $sql = 'SELECT t.*, p.name AS project_name, p.company_id, p.client_visible AS project_client_visible,
                   c.name AS company_name, u.display_name AS assignee_name
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            JOIN companies c ON c.id = p.company_id
            LEFT JOIN users u ON u.id = t.assignee_id';
    $where = [];
    $params = [];

    if ($user['role'] === 'developer') {
        $where[] = 't.assignee_id = ?';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'client') {
        $where[] = 'p.company_id = ? AND p.client_visible = 1 AND t.client_visible = 1';
        $params[] = $user['company_id'];
    }

    $companyId = selected_company_id($user);
    if ($companyId) {
        $where[] = 'p.company_id = ?';
        $params[] = $companyId;
    }
    if ($search !== '') {
        $where[] = '(t.title LIKE ? OR p.name LIKE ? OR c.name LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term);
    }
    if ($status !== '' && in_array($status, ['waiting', 'in_progress', 'review', 'completed'], true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY (t.due_date IS NULL), t.due_date, t.updated_at DESC';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function can_access_project(array $user, int $projectId): bool
{
    $sql = 'SELECT p.id FROM projects p WHERE p.id = ?';
    $params = [$projectId];
    if ($user['role'] === 'client') {
        $sql .= ' AND p.company_id = ? AND p.client_visible = 1';
        $params[] = $user['company_id'];
    } elseif ($user['role'] === 'developer') {
        $sql .= ' AND EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?)';
        $params[] = $user['id'];
    }
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return (bool)$statement->fetchColumn();
}

function can_access_task(array $user, int $taskId): bool
{
    $sql = 'SELECT t.id FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ?';
    $params = [$taskId];
    if ($user['role'] === 'client') {
        $sql .= ' AND p.company_id = ? AND p.client_visible = 1 AND t.client_visible = 1';
        $params[] = $user['company_id'];
    } elseif ($user['role'] === 'developer') {
        $sql .= ' AND t.assignee_id = ?';
        $params[] = $user['id'];
    }
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return (bool)$statement->fetchColumn();
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
