<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
require_role($user, ['admin', 'manager']);
$activePage = 'team';
$pageTitle = 'Ekip';
$errors = [];
$companies = companies_for_user($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create_user') {
        require_role($user, ['admin']);
        $name = trim((string)($_POST['display_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role = (string)($_POST['role'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
        $allowedRoles = ['admin', 'manager', 'developer', 'client'];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = 'İsim 1–120 karakter arasında olmalıdır.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Bir kullanıcı rolü seçin.';
        }
        if (mb_strlen($password) < 10) {
            $errors[] = 'Şifre en az 10 karakter olmalıdır.';
        }
        if ($role === 'client' && (!$companyId || !in_array($companyId, array_map(static fn(array $company): int => (int)$company['id'], $companies), true))) {
            $errors[] = 'Müşteri hesabı için şirket seçin.';
        }
        if (!$errors) {
            $insert = db()->prepare(
                'INSERT INTO users (company_id, display_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)'
            );
            try {
                $insert->execute([$role === 'client' ? $companyId : null, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                set_flash('Kullanıcı oluşturuldu.');
                redirect('team.php');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'Bu e-posta adresi zaten kullanılıyor.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'assign_member') {
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
        $memberId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$projectId || !can_access_project($user, $projectId) || !$memberId) {
            $errors[] = 'Proje veya kullanıcı seçimi geçersiz.';
        } else {
            $memberQuery = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
            $memberQuery->execute([$memberId]);
            if (!$memberQuery->fetchColumn()) {
                $errors[] = 'Yalnızca aktif uzmanlar projeye eklenebilir.';
            } else {
                $insert = db()->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');
                $insert->execute([$projectId, $memberId]);
                set_flash('Uzman projeye eklendi.');
                redirect('team.php');
            }
        }
    } else {
        http_response_code(400);
        exit('Bilinmeyen işlem.');
    }
}

$usersSql = 'SELECT u.id, u.display_name, u.email, u.role, u.is_active, c.name AS company_name
             FROM users u LEFT JOIN companies c ON c.id = u.company_id';
if ($user['role'] !== 'admin') {
    $usersSql .= " WHERE u.role IN ('manager', 'developer')";
}
$usersSql .= ' ORDER BY u.role, u.display_name';
$members = db()->query($usersSql)->fetchAll();
$projects = visible_projects($user);
$developers = db()->query("SELECT id, display_name FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY display_name")->fetchAll();
$assignmentQuery = db()->prepare(
    'SELECT p.name AS project_name, c.name AS company_name, u.display_name, u.email
     FROM project_members pm JOIN projects p ON p.id = pm.project_id
     JOIN companies c ON c.id = p.company_id JOIN users u ON u.id = pm.user_id
     ORDER BY p.name, u.display_name'
);
$assignmentQuery->execute();
$assignments = $assignmentQuery->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">KİŞİLER VE ERİŞİM</span><h1>Ekip</h1><p>Kullanıcı rollerini ve proje atamalarını yönet.</p></div>
</section>
<?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($user['role'] === 'admin'): ?>
<section class="section-block create-panel">
    <div class="section-heading"><div><span class="eyebrow">YÖNETİCİ İŞLEMİ</span><h2>Kullanıcı oluştur</h2></div></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_user">
        <label>Ad soyad<input name="display_name" maxlength="120" placeholder="İsim 7" required></label>
        <label>E-posta<input name="email" type="email" maxlength="190" placeholder="isim7@example.test" required></label>
        <label>Rol<select name="role" required><option value="developer">Uzman</option><option value="manager">Proje yöneticisi</option><option value="client">Müşteri temsilcisi</option><option value="admin">Yönetici</option></select></label>
        <label>İlk şifre<input name="password" type="password" minlength="10" autocomplete="new-password" required></label>
        <label>Şirket<select name="company_id"><option value="">İç ekip kullanıcısı</option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select><small>Müşteri hesabında şirket seçmek zorunludur.</small></label>
        <div class="form-full"><button class="button button-primary" type="submit">Kullanıcıyı ekle</button></div>
    </form>
</section>
<?php endif; ?>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">ÇALIŞMA ALANI</span><h2>Kullanıcılar</h2></div><span class="table-count"><?= count($members) ?> kişi</span></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>İsim</th><th>E-posta</th><th>Rol</th><th>Şirket</th><th>Durum</th></tr></thead><tbody>
    <?php foreach ($members as $member): ?><tr><td class="person-cell"><span class="avatar avatar-purple"><?= e(mb_substr($member['display_name'], 0, 1)) ?></span><strong><?= e($member['display_name']) ?></strong></td><td><?= e($member['email']) ?></td><td><?= e(role_label($member['role'])) ?></td><td><?= e($member['company_name'] ?: 'İç ekip') ?></td><td><span class="status-pill <?= (int)$member['is_active'] ? 'status-completed' : 'status-waiting' ?>"><?= (int)$member['is_active'] ? 'Aktif' : 'Pasif' ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">PROJE EKİPLERİ</span><h2>Proje atamaları</h2></div></div>
    <div class="create-panel">
        <form method="post" class="inline-form assignment-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="assign_member">
            <label>Proje<select name="project_id" required><option value="">Proje seçin</option><?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>"><?= e($project['company_name'] . ' · ' . $project['name']) ?></option><?php endforeach; ?></select></label>
            <label>Uzman<select name="user_id" required><option value="">Ekip üyesi seçin</option><?php foreach ($developers as $developer): ?><option value="<?= (int)$developer['id'] ?>"><?= e($developer['display_name']) ?></option><?php endforeach; ?></select></label>
            <button class="button button-primary" type="submit">Projeye ata</button>
        </form>
    </div>
    <div class="table-wrap assignment-table"><table class="data-table"><thead><tr><th>Proje</th><th>Şirket</th><th>Ekip üyesi</th><th>E-posta</th></tr></thead><tbody>
    <?php foreach ($assignments as $assignment): ?><tr><td><?= e($assignment['project_name']) ?></td><td><?= e($assignment['company_name']) ?></td><td><?= e($assignment['display_name']) ?></td><td><?= e($assignment['email']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
