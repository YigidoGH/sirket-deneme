<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
$activePage = '';
$pageTitle = 'Bildirimler';
$sql = 'SELECT r.id, r.body, r.created_at, u.display_name, t.title AS task_title, t.id AS task_id,
               p.name AS project_name, p.company_id
        FROM task_revisions r
        JOIN users u ON u.id = r.author_id
        JOIN tasks t ON t.id = r.task_id
        JOIN projects p ON p.id = t.project_id';
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
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 100';
$statement = db()->prepare($sql);
$statement->execute($params);
$notifications = $statement->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">GÜNCEL AKIŞ</span><h1>Bildirimler</h1><p>Projelerdeki müşteri revizyon notları.</p></div>
</section>
<section class="section-block">
    <?php if (!$notifications): ?><div class="empty-state"><strong>Yeni bildirim yok</strong><p>Revizyon notları geldiğinde burada görebilirsin.</p></div>
    <?php else: ?><div class="notification-list">
        <?php foreach ($notifications as $notification): ?><article class="notification-item"><span class="notification-mark">↗</span><div><p><strong><?= e($notification['display_name']) ?></strong> · <?= e($notification['task_title']) ?></p><blockquote><?= e($notification['body']) ?></blockquote><small><?= e($notification['project_name']) ?> · <?= e(date('d.m.Y H:i', strtotime($notification['created_at']))) ?></small></div><a class="button button-small" href="tasks.php?search=<?= urlencode($notification['task_title']) ?>">Teslime git</a></article><?php endforeach; ?>
    </div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
