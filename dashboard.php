<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
$activePage = 'dashboard';
$pageTitle = 'Genel bakış';
$projects = visible_projects($user);
$tasks = visible_tasks($user);
$done = count(array_filter($tasks, static fn(array $task): bool => $task['status'] === 'completed'));
$active = count($tasks) - $done;
$recentTasks = array_slice($tasks, 0, 5);
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">ÇALIŞMA ALANINIZ</span><h1>Merhaba, <?= e($user['display_name']) ?></h1><p>Projelerin ve teslimlerin güncel durumuna buradan göz at.</p></div>
    <?php if (in_array($user['role'], ['admin', 'manager'], true)): ?><a class="button button-primary" href="projects.php#new-project">+ Yeni proje</a><?php endif; ?>
</section>
<section class="stats-grid" aria-label="Özet bilgiler">
    <article class="stat-card"><div class="stat-top"><span>Görüntülenen projeler</span><span class="stat-icon stat-violet">▧</span></div><strong><?= count($projects) ?></strong><small>Yetkin olan projeler</small></article>
    <article class="stat-card"><div class="stat-top"><span>Açık teslim kalemleri</span><span class="stat-icon stat-blue">☷</span></div><strong><?= $active ?></strong><small>Tamamlanmayı bekleyen işler</small></article>
    <article class="stat-card"><div class="stat-top"><span>Tamamlanan</span><span class="stat-icon stat-green">✓</span></div><strong><?= $done ?></strong><small>Teslim edilen işler</small></article>
</section>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">PROJE TAKİBİ</span><h2>Projeler</h2></div><a class="text-link" href="projects.php">Tüm projeler →</a></div>
    <?php if (!$projects): ?>
        <div class="empty-state"><strong>Henüz görüntülenecek proje yok</strong><p>Yetkin olan projeler eklendiğinde burada listelenir.</p></div>
    <?php else: ?>
        <div class="project-grid">
            <?php foreach (array_slice($projects, 0, 3) as $project): ?>
                <?php $progress = (int)$project['task_count'] > 0 ? (int)round((int)$project['completed_count'] / (int)$project['task_count'] * 100) : 0; ?>
                <a class="project-card" href="project.php?id=<?= (int)$project['id'] ?>">
                    <div class="project-art project-art-violet"><span class="art-orbit orbit-a"></span><span class="art-orbit orbit-b"></span><span class="art-symbol">✦</span></div>
                    <div class="project-body">
                        <div class="project-title-line"><h3><?= e($project['name']) ?></h3><span class="project-status"><?= e(status_label($project['status'])) ?></span></div>
                        <p class="project-company"><?= e($project['company_name']) ?> · <?= (int)$project['task_count'] ?> teslim</p>
                        <p class="project-description"><?= e($project['description']) ?></p>
                        <div class="progress-label"><span>İlerleme</span><strong><?= $progress ?>%</strong></div>
                        <div class="progress-track"><span style="width: <?= $progress ?>%"></span></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">YAKINDAKİ İŞLER</span><h2>Son teslim kalemleri</h2></div><a class="text-link" href="tasks.php">Tüm teslimler →</a></div>
    <?php if (!$recentTasks): ?>
        <div class="empty-state"><strong>Henüz teslim kalemi yok</strong><p>Yeni işler eklendiğinde bu bölüm güncellenir.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>İş kalemi</th><th>Proje</th><th>Atanan</th><th>Öncelik</th><th>Teslim tarihi</th><th>Durum</th></tr></thead><tbody>
        <?php foreach ($recentTasks as $task): ?>
            <tr><td><a class="table-link" href="tasks.php?search=<?= urlencode($task['title']) ?>"><?= e($task['title']) ?></a></td><td><?= e($task['project_name']) ?></td><td><?= e($task['assignee_name'] ?: 'Atanmadı') ?></td><td><span class="priority-pill priority-<?= e($task['priority']) ?>"><?= e(priority_label($task['priority'])) ?></span></td><td><?= $task['due_date'] ? e(date('d.m.Y', strtotime($task['due_date']))) : '—' ?></td><td><span class="status-pill status-<?= e($task['status']) ?>"><?= e(status_label($task['status'])) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
