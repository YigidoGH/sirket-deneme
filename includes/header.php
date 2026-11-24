<?php
$user = $user ?? require_auth();
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'DeliverDesk';
$companies = companies_for_user($user);
$selectedCompanyId = selected_company_id($user);
$revisionCountSql = 'SELECT COUNT(*) FROM task_revisions r
                     JOIN tasks t ON t.id = r.task_id
                     JOIN projects p ON p.id = t.project_id';
$revisionConditions = [];
$revisionParams = [];
if ($user['role'] === 'developer') {
    $revisionConditions[] = 't.assignee_id = ?';
    $revisionParams[] = $user['id'];
} elseif ($user['role'] === 'client') {
    $revisionConditions[] = 'p.company_id = ? AND p.client_visible = 1 AND t.client_visible = 1';
    $revisionParams[] = $user['company_id'];
}
if ($selectedCompanyId) {
    $revisionConditions[] = 'p.company_id = ?';
    $revisionParams[] = $selectedCompanyId;
}
if ($revisionConditions) {
    $revisionCountSql .= ' WHERE ' . implode(' AND ', $revisionConditions);
}
$revisionCountStatement = db()->prepare($revisionCountSql);
$revisionCountStatement->execute($revisionParams);
$revisionCount = (int)$revisionCountStatement->fetchColumn();
$flashMessage = take_flash();
$initials = implode('', array_map(static fn(string $part): string => mb_substr($part, 0, 1), explode(' ', $user['display_name'])));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title><?= e($pageTitle) ?> | DeliverDesk</title>
    <link rel="stylesheet" href="styles.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php"><span class="brand-mark">D</span><span>deliver<span class="brand-accent">desk</span></span></a>
        <form class="workspace-switch" action="workspace.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e(basename($_SERVER['PHP_SELF'])) ?>">
            <label class="sr-only" for="workspace-company">Çalışma alanı</label>
            <span class="workspace-icon">✦</span>
            <select id="workspace-company" name="company_id" onchange="this.form.submit()" <?= $user['role'] === 'client' ? 'disabled' : '' ?>>
                <?php if ($user['role'] !== 'client'): ?><option value="all" <?= !$selectedCompanyId ? 'selected' : '' ?>>Tüm şirketler</option><?php endif; ?>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int)$company['id'] ?>" <?= $selectedCompanyId === (int)$company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="switch-chevron">⌄</span>
        </form>
        <span class="nav-caption">MENÜ</span>
        <nav class="main-nav" aria-label="Ana menü">
            <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><span class="nav-icon">▦</span> Genel bakış</a>
            <a class="nav-link <?= $activePage === 'projects' ? 'active' : '' ?>" href="projects.php"><span class="nav-icon">▧</span> Projeler</a>
            <a class="nav-link <?= $activePage === 'tasks' ? 'active' : '' ?>" href="tasks.php"><span class="nav-icon">☷</span> Teslim kalemleri</a>
            <?php if (in_array($user['role'], ['admin', 'manager'], true)): ?>
                <a class="nav-link <?= $activePage === 'team' ? 'active' : '' ?>" href="team.php"><span class="nav-icon">♧</span> Ekip</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="help-card"><span class="help-symbol">?</span><strong>Yardım merkezi</strong><p>Takıldığınız yerde destek alın.</p><a href="mailto:destek@deliverdesk.test">Destek iste <span>→</span></a></div>
            <div class="sidebar-user">
                <span class="avatar avatar-purple"><?= e($initials) ?></span>
                <span class="user-meta"><strong><?= e($user['display_name']) ?></strong><small><?= e(role_label($user['role'])) ?></small></span>
                <a class="icon-button" href="profile.php" aria-label="Profil ayarları" title="Profil ayarları">⚙</a>
            </div>
        </div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumbs"><a href="dashboard.php">DeliverDesk</a><span>/</span><strong><?= e($pageTitle) ?></strong></div>
            <div class="topbar-actions">
                <span class="today-label"><?= date('d.m.Y') ?></span>
                <details class="top-popover">
                    <summary class="icon-button notification-button" aria-label="Bildirimler">♧<?php if ($revisionCount > 0): ?><i><?= min($revisionCount, 9) ?></i><?php endif; ?></summary>
                    <div class="popover-menu notification-menu">
                        <strong>Bildirimler</strong>
                        <p><?= $revisionCount > 0 ? $revisionCount . ' revizyon notu görüntülenebilir.' : 'Yeni bildirim yok.' ?></p>
                        <a href="notifications.php">Tüm bildirimleri gör →</a>
                    </div>
                </details>
                <details class="top-popover profile-popover">
                    <summary class="profile-trigger"><span class="avatar avatar-small avatar-purple"><?= e($initials) ?></span><span class="profile-trigger-name"><?= e($user['display_name']) ?></span><span class="switch-chevron">⌄</span></summary>
                    <div class="popover-menu profile-menu">
                        <strong><?= e($user['display_name']) ?></strong><small><?= e(role_label($user['role'])) ?></small>
                        <a href="profile.php">Profil ayarları</a>
                        <form action="logout.php" method="post"><?= csrf_field() ?><button type="submit">Güvenli çıkış yap</button></form>
                    </div>
                </details>
            </div>
        </header>
        <div class="page-content">
            <?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
