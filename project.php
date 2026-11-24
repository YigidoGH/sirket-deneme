<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
$projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$projectId || !can_access_project($user, $projectId)) {
    http_response_code(404);
    exit('Proje bulunamadı.');
}

$projectStatement = db()->prepare(
    'SELECT p.*, c.name AS company_name FROM projects p
     JOIN companies c ON c.id = p.company_id WHERE p.id = ? LIMIT 1'
);
$projectStatement->execute([$projectId]);
$project = $projectStatement->fetch();
if (!$project) {
    http_response_code(404);
    exit('Proje bulunamadı.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role($user, ['admin', 'manager']);
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $internalNote = trim((string)($_POST['internal_note'] ?? ''));
    $status = (string)($_POST['status'] ?? '');
    $clientVisible = isset($_POST['client_visible']) ? 1 : 0;
    if ($name === '' || mb_strlen($name) > 160) {
        $errors[] = 'Proje adı 1–160 karakter arasında olmalıdır.';
    }
    if ($description === '' || mb_strlen($description) > 4000) {
        $errors[] = 'Açıklama 1–4000 karakter arasında olmalıdır.';
    }
    if (mb_strlen($internalNote) > 2000) {
        $errors[] = 'Ekip içi not en fazla 2000 karakter olabilir.';
    }
    if (!in_array($status, ['active', 'paused', 'completed'], true)) {
        $errors[] = 'Proje durumu geçersiz.';
    }
    if (!$errors) {
        try {
            $update = db()->prepare('UPDATE projects SET name = ?, description = ?, internal_note = ?, status = ?, client_visible = ? WHERE id = ?');
            $update->execute([$name, $description, $internalNote ?: null, $status, $clientVisible, $projectId]);
            set_flash('Proje ayarları güncellendi.');
            redirect('project.php?id=' . $projectId);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Bu şirket için aynı isimde başka bir proje var.';
            } else {
                throw $exception;
            }
        }
    }
    $project['name'] = $name;
    $project['description'] = $description;
    $project['internal_note'] = $internalNote;
    $project['status'] = $status;
    $project['client_visible'] = $clientVisible;
}

$taskSql = 'SELECT t.*, u.display_name AS assignee_name FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.project_id = ?';
$taskParams = [$projectId];
if ($user['role'] === 'developer') {
    $taskSql .= ' AND t.assignee_id = ?';
    $taskParams[] = $user['id'];
} elseif ($user['role'] === 'client') {
    $taskSql .= ' AND t.client_visible = 1';
}
$taskSql .= ' ORDER BY t.due_date, t.id';
$taskStatement = db()->prepare($taskSql);
$taskStatement->execute($taskParams);
$projectTasks = $taskStatement->fetchAll();

$activePage = 'projects';
$pageTitle = $project['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="breadcrumbs page-breadcrumb"><a href="projects.php">← Projelere dön</a><span>/</span><strong><?= e($project['company_name']) ?></strong></div>
<section class="page-heading">
    <div><span class="eyebrow"><?= e($project['company_name']) ?></span><h1><?= e($project['name']) ?></h1><p><?= e($project['description']) ?></p></div>
    <?php if (in_array($user['role'], ['admin', 'manager'], true)): ?><a class="button button-primary" href="tasks.php?project_id=<?= (int)$projectId ?>#new-task">+ İş kalemi ekle</a><?php endif; ?>
</section>
<?php if (in_array($user['role'], ['admin', 'manager'], true)): ?>
<section class="section-block create-panel">
    <div class="section-heading"><div><span class="eyebrow">PROJE AYARLARI</span><h2>Görünürlük ve durum</h2></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>Proje adı<input name="name" maxlength="160" value="<?= e($project['name']) ?>" required></label>
        <label>Durum<select name="status"><option value="active" <?= $project['status'] === 'active' ? 'selected' : '' ?>>Aktif</option><option value="paused" <?= $project['status'] === 'paused' ? 'selected' : '' ?>>Duraklatıldı</option><option value="completed" <?= $project['status'] === 'completed' ? 'selected' : '' ?>>Tamamlandı</option></select></label>
        <label class="form-full">Açıklama<textarea name="description" rows="3" maxlength="4000" required><?= e($project['description']) ?></textarea></label>
        <label class="form-full">Ekip içi not<textarea name="internal_note" rows="2" maxlength="2000" placeholder="Yalnızca iç ekip görür."><?= e($project['internal_note'] ?? '') ?></textarea></label>
        <label class="checkbox-label form-full"><input type="checkbox" name="client_visible" value="1" <?= (int)$project['client_visible'] ? 'checked' : '' ?>> Müşteri bu projeyi ve paylaşıma açık teslimleri görebilsin</label>
        <div class="form-full"><button class="button button-primary" type="submit">Ayarları kaydet</button></div>
    </form>
</section>
<?php elseif (in_array($user['role'], ['manager', 'developer'], true) && !empty($project['internal_note'])): ?>
<section class="section-block">
    <div class="project-note-panel"><strong>Ekip içi proje notu</strong><p><?= e($project['internal_note']) ?></p></div>
</section>
<?php endif; ?>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">PROJE İÇERİĞİ</span><h2>Teslim kalemleri</h2></div><a class="text-link" href="tasks.php?project_id=<?= (int)$projectId ?>">Tüm teslimleri gör →</a></div>
    <?php if (!$projectTasks): ?><div class="empty-state"><strong>Bu projede teslim kalemi yok</strong><p>Yeni bir iş eklendiğinde burada görüntülenir.</p></div>
    <?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>İş kalemi</th><th>Atanan</th><th>Öncelik</th><th>Teslim tarihi</th><th>Durum</th></tr></thead><tbody>
        <?php foreach ($projectTasks as $task): ?><tr><td><?= e($task['title']) ?></td><td><?= e($task['assignee_name'] ?: 'Atanmadı') ?></td><td><span class="priority-pill priority-<?= e($task['priority']) ?>"><?= e(priority_label($task['priority'])) ?></span></td><td><?= $task['due_date'] ? e(date('d.m.Y', strtotime($task['due_date']))) : '—' ?></td><td><span class="status-pill status-<?= e($task['status']) ?>"><?= e(status_label($task['status'])) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
