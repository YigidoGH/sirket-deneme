<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
$activePage = 'tasks';
$pageTitle = 'Teslim kalemleri';
$errors = [];
$projectIdFilter = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT);
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'create_task') {
        require_role($user, ['admin', 'manager']);
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
        $assigneeId = filter_var($_POST['assignee_id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'medium');
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $clientVisible = isset($_POST['client_visible']) ? 1 : 0;
        if (!$projectId || !can_access_project($user, $projectId)) {
            $errors[] = 'Bir yetkili proje seçin.';
        }
        if ($title === '' || mb_strlen($title) > 180) {
            $errors[] = 'İş kalemi adı 1–180 karakter arasında olmalıdır.';
        }
        if (mb_strlen($description) > 4000) {
            $errors[] = 'Açıklama en fazla 4000 karakter olabilir.';
        }
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $errors[] = 'Öncelik değeri geçersiz.';
        }
        if ($dueDate !== '' && (!DateTime::createFromFormat('Y-m-d', $dueDate) || DateTime::createFromFormat('Y-m-d', $dueDate)->format('Y-m-d') !== $dueDate)) {
            $errors[] = 'Teslim tarihi geçersiz.';
        }
        if ($assigneeId) {
            $assigneeCheck = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'developer' AND is_active = 1");
            $assigneeCheck->execute([$assigneeId]);
            if (!$assigneeCheck->fetchColumn()) {
                $errors[] = 'Atanacak uzman bulunamadı.';
            }
        }
        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT INTO tasks (project_id, assignee_id, created_by, title, description, priority, due_date, client_visible)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$projectId, $assigneeId ?: null, $user['id'], $title, $description, $priority, $dueDate ?: null, $clientVisible]);
            if ($assigneeId) {
                $member = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');
                $member->execute([$projectId, $assigneeId]);
            }
            $pdo->commit();
            set_flash('Teslim kalemi oluşturuldu.');
            redirect('tasks.php?project_id=' . $projectId);
        }
    } elseif ($action === 'update_task') {
        require_role($user, ['admin', 'manager']);
        $priority = (string)($_POST['priority'] ?? '');
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $clientVisible = isset($_POST['client_visible']) ? 1 : 0;
        if (!$taskId || !can_access_task($user, $taskId)) {
            $errors[] = 'Teslim kalemi bulunamadı.';
        } elseif (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $errors[] = 'Öncelik değeri geçersiz.';
        } elseif ($dueDate !== '' && (!DateTime::createFromFormat('Y-m-d', $dueDate) || DateTime::createFromFormat('Y-m-d', $dueDate)->format('Y-m-d') !== $dueDate)) {
            $errors[] = 'Teslim tarihi geçersiz.';
        } else {
            $update = db()->prepare('UPDATE tasks SET priority = ?, due_date = ?, client_visible = ? WHERE id = ?');
            $update->execute([$priority, $dueDate ?: null, $clientVisible, $taskId]);
            set_flash('Teslim kalemi güncellendi.');
            redirect('tasks.php');
        }
    } elseif ($action === 'update_status') {
        $status = (string)($_POST['status'] ?? '');
        if (!$taskId || !can_access_task($user, $taskId)
            || !in_array($user['role'], ['admin', 'manager', 'developer'], true)
            || !in_array($status, ['waiting', 'in_progress', 'review', 'completed'], true)) {
            $errors[] = 'Bu teslim kaleminin durumunu değiştirme yetkiniz yok.';
        } else {
            $update = db()->prepare('UPDATE tasks SET status = ? WHERE id = ?');
            $update->execute([$status, $taskId]);
            set_flash('Görev durumu güncellendi.');
            redirect('tasks.php');
        }
    } elseif ($action === 'save_internal_note') {
        if (!$taskId || !can_access_task($user, $taskId)
            || !in_array($user['role'], ['admin', 'manager', 'developer'], true)) {
            $errors[] = 'Bu iş için iç not düzenleme yetkiniz yok.';
        } else {
            $note = trim((string)($_POST['internal_note'] ?? ''));
            if (mb_strlen($note) > 2000) {
                $errors[] = 'İç not en fazla 2000 karakter olabilir.';
            } else {
                $update = db()->prepare('UPDATE tasks SET internal_note = ? WHERE id = ?');
                $update->execute([$note, $taskId]);
                set_flash('Ekip içi not kaydedildi.');
                redirect('tasks.php');
            }
        }
    } elseif ($action === 'add_revision') {
        if ($user['role'] !== 'client' || !$taskId || !can_access_task($user, $taskId)) {
            $errors[] = 'Bu teslim kalemi için revizyon gönderme yetkiniz yok.';
        } else {
            $revision = trim((string)($_POST['revision'] ?? ''));
            if ($revision === '' || mb_strlen($revision) > 1000) {
                $errors[] = 'Revizyon notu 1–1000 karakter arasında olmalıdır.';
            } else {
                $insert = db()->prepare('INSERT INTO task_revisions (task_id, author_id, body) VALUES (?, ?, ?)');
                $insert->execute([$taskId, $user['id'], $revision]);
                set_flash('Revizyon notunuz ekibe iletildi.');
                redirect('tasks.php');
            }
        }
    } else {
        http_response_code(400);
        exit('Bilinmeyen işlem.');
    }
}

$tasks = visible_tasks($user, $search, $statusFilter);
if ($projectIdFilter) {
    $tasks = array_values(array_filter($tasks, static fn(array $task): bool => (int)$task['project_id'] === $projectIdFilter));
}
$projects = visible_projects($user);
$developers = in_array($user['role'], ['admin', 'manager'], true)
    ? db()->query("SELECT id, display_name FROM users WHERE role = 'developer' AND is_active = 1 ORDER BY display_name")->fetchAll()
    : [];
$revisionsByTask = [];
if ($tasks) {
    $taskIds = array_map(static fn(array $task): int => (int)$task['id'], $tasks);
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $revisionQuery = db()->prepare(
        "SELECT r.task_id, r.body, r.created_at, u.display_name
         FROM task_revisions r JOIN users u ON u.id = r.author_id
         WHERE r.task_id IN ($placeholders) ORDER BY r.created_at"
    );
    $revisionQuery->execute($taskIds);
    foreach ($revisionQuery->fetchAll() as $revision) {
        $revisionsByTask[$revision['task_id']][] = $revision;
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">İŞ TAKİBİ</span><h1>Teslim kalemleri</h1><p>Durumları, sorumluları ve müşteri revizyonlarını tek listeden yönet.</p></div>
    <?php if (in_array($user['role'], ['admin', 'manager'], true)): ?><a class="button button-primary" href="#new-task">+ İş kalemi ekle</a><?php endif; ?>
</section>
<?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<?php if (in_array($user['role'], ['admin', 'manager'], true)): ?>
<section class="section-block create-panel" id="new-task">
    <div class="section-heading"><div><span class="eyebrow">YENİ TESLİM</span><h2>İş kalemi oluştur</h2></div></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_task">
        <label>Proje<select name="project_id" required><option value="">Proje seçin</option><?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>" <?= $projectIdFilter === (int)$project['id'] ? 'selected' : '' ?>><?= e($project['company_name'] . ' · ' . $project['name']) ?></option><?php endforeach; ?></select></label>
        <label>Atanacak uzman<select name="assignee_id"><option value="">Henüz atama</option><?php foreach ($developers as $developer): ?><option value="<?= (int)$developer['id'] ?>"><?= e($developer['display_name']) ?></option><?php endforeach; ?></select></label>
        <label>İş kalemi adı<input name="title" maxlength="180" placeholder="İş kalemi 7" required></label>
        <label>Öncelik<select name="priority"><option value="medium">Orta</option><option value="high">Yüksek</option><option value="low">Düşük</option></select></label>
        <label>Teslim tarihi<input name="due_date" type="date"></label>
        <label class="checkbox-label"><input type="checkbox" name="client_visible" value="1"> Müşteriyle paylaş</label>
        <label class="form-full">Açıklama<textarea name="description" rows="2" maxlength="4000" placeholder="İşin kapsamı ve teslim ölçütleri." required></textarea></label>
        <div class="form-full"><button class="button button-primary" type="submit">İş kalemini oluştur</button></div>
    </form>
</section>
<?php endif; ?>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">TESLİMLER</span><h2><?= count($tasks) ?> kalem</h2></div></div>
    <form class="filter-bar" method="get" action="tasks.php">
        <label class="search-control"><span>⌕</span><input type="search" name="search" value="<?= e($search) ?>" placeholder="İş kalemi veya proje ara"></label>
        <label class="filter-select"><span class="sr-only">Duruma göre filtrele</span><select name="status"><option value="">Tüm durumlar</option><option value="waiting" <?= $statusFilter === 'waiting' ? 'selected' : '' ?>>Bekliyor</option><option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Devam ediyor</option><option value="review" <?= $statusFilter === 'review' ? 'selected' : '' ?>>İncelemede</option><option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Tamamlandı</option></select></label>
        <?php if ($projectIdFilter): ?><input type="hidden" name="project_id" value="<?= $projectIdFilter ?>"><?php endif; ?>
        <button class="button" type="submit">Filtrele</button>
        <?php if ($search !== '' || $statusFilter !== ''): ?><a class="button button-quiet" href="tasks.php">Temizle</a><?php endif; ?>
    </form>
    <?php if (!$tasks): ?>
        <div class="empty-state"><strong>Bu filtreyle teslim bulunamadı</strong><p>Aramanı veya durum filtresini değiştirebilirsin.</p></div>
    <?php else: ?>
        <div class="task-list">
        <?php foreach ($tasks as $task): ?>
            <article class="task-card">
                <div class="task-card-heading">
                    <div class="task-title-group"><h3><?= e($task['title']) ?></h3><p><a href="project.php?id=<?= (int)$task['project_id'] ?>"><?= e($task['company_name']) ?> · <?= e($task['project_name']) ?></a></p></div>
                    <span class="status-pill status-<?= e($task['status']) ?>"><?= e(status_label($task['status'])) ?></span>
                </div>
                <?php if ($task['description'] !== ''): ?><p class="task-description"><?= e($task['description']) ?></p><?php endif; ?>
                <div class="task-meta">
                    <span class="priority-pill priority-<?= e($task['priority']) ?>"><?= e(priority_label($task['priority'])) ?> öncelik</span>
                    <span class="task-date"><?= $task['due_date'] ? 'Teslim: ' . e(date('d.m.Y', strtotime($task['due_date']))) : 'Tarih belirlenmedi' ?></span>
                    <span class="assignee-label">Atanan: <?= e($task['assignee_name'] ?: 'Atanmadı') ?></span>
                    <?php if ((int)$task['client_visible'] && (int)$task['project_client_visible']): ?><span class="visibility-pill">Müşteriye açık</span><?php endif; ?>
                </div>
                <?php if (in_array($user['role'], ['admin', 'manager', 'developer'], true) && ($user['role'] !== 'developer' || (int)$task['assignee_id'] === (int)$user['id'])): ?>
                    <div class="task-actions">
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><label class="sr-only" for="status-<?= (int)$task['id'] ?>">Durum</label><select id="status-<?= (int)$task['id'] ?>" name="status"><option value="waiting" <?= $task['status'] === 'waiting' ? 'selected' : '' ?>>Bekliyor</option><option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>Devam ediyor</option><option value="review" <?= $task['status'] === 'review' ? 'selected' : '' ?>>İncelemede</option><option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Tamamlandı</option></select><button class="button button-small" type="submit">Durumu kaydet</button></form>
                        <details class="action-details"><summary>İç not</summary><form method="post" class="note-form"><?= csrf_field() ?><input type="hidden" name="action" value="save_internal_note"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><textarea name="internal_note" rows="2" maxlength="2000" placeholder="Yalnızca ekip tarafından görülür"><?= e($task['internal_note'] ?? '') ?></textarea><button class="button button-small" type="submit">Notu kaydet</button></form></details>
                        <?php if (in_array($user['role'], ['admin', 'manager'], true)): ?>
                            <details class="action-details"><summary>Düzenle</summary><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_task"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><select name="priority"><option value="low" <?= $task['priority'] === 'low' ? 'selected' : '' ?>>Düşük</option><option value="medium" <?= $task['priority'] === 'medium' ? 'selected' : '' ?>>Orta</option><option value="high" <?= $task['priority'] === 'high' ? 'selected' : '' ?>>Yüksek</option></select><input name="due_date" type="date" value="<?= e($task['due_date'] ?? '') ?>"><label class="checkbox-label"><input type="checkbox" name="client_visible" value="1" <?= (int)$task['client_visible'] ? 'checked' : '' ?>> Müşteriye açık</label><button class="button button-small" type="submit">Kaydet</button></form></details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($revisionsByTask[$task['id']] ?? [] as $revision): ?>
                    <div class="revision-item"><strong><?= e($revision['display_name']) ?> <span>· <?= e(date('d.m.Y H:i', strtotime($revision['created_at']))) ?></span></strong><p><?= e($revision['body']) ?></p></div>
                <?php endforeach; ?>
                <?php if ($user['role'] === 'client'): ?>
                    <form method="post" class="revision-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_revision"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><label class="sr-only" for="revision-<?= (int)$task['id'] ?>">Revizyon notu</label><input id="revision-<?= (int)$task['id'] ?>" name="revision" maxlength="1000" placeholder="Bu teslim için bir revizyon notu yaz..." required><button class="button button-small button-primary" type="submit">Not gönder →</button></form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
