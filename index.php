<?php
declare(strict_types=1);

session_start();

$demoPassword = 'demo123';
$users = [
    'admin@deliverdesk.test' => ['name' => 'Deniz Yılmaz', 'role' => 'admin', 'company' => null],
    'pm@deliverdesk.test' => ['name' => 'Ece Demir', 'role' => 'manager', 'company' => null],
    'developer@deliverdesk.test' => ['name' => 'Mert Kaya', 'role' => 'developer', 'company' => null],
    'client@deliverdesk.test' => ['name' => 'Selin Arslan', 'role' => 'client', 'company' => 'MaviKare'],
];
$roleNames = [
    'admin' => 'Yönetici',
    'manager' => 'Proje yöneticisi',
    'developer' => 'Uzman',
    'client' => 'Müşteri temsilcisi',
];

if (!isset($_SESSION['projects'])) {
    $_SESSION['projects'] = [
        1 => ['name' => 'Aurora E-ticaret', 'company' => 'MaviKare', 'description' => 'Yeni nesil alışveriş deneyimi ve mağaza yönetim paneli.', 'client_visible' => true, 'note' => 'Marka kılavuzundaki güncel renk paleti kullanılmalı.'],
        2 => ['name' => 'Atlas Kurumsal Site', 'company' => 'Kuzey Lojistik', 'description' => 'Kurumsal web sitesi ve içerik yönetim altyapısı.', 'client_visible' => true, 'note' => 'Yayın öncesi içerik ekibiyle son kontrol yapılacak.'],
        3 => ['name' => 'MaviKare Mobil Uygulama', 'company' => 'MaviKare', 'description' => 'Sadakat programı için iOS ve Android uygulaması.', 'client_visible' => true, 'note' => 'Bildirim entegrasyonu için teknik ekipten yanıt bekleniyor.'],
    ];
    $_SESSION['tasks'] = [
        101 => ['project_id' => 1, 'title' => 'Ürün listeleme ekranı', 'assignee' => 'developer@deliverdesk.test', 'status' => 'Devam ediyor', 'priority' => 'Yüksek', 'due' => '2026-12-03', 'client_visible' => true, 'internal_note' => 'Filtre performansı için sorgu indeksleri gözden geçirilecek.', 'revisions' => []],
        102 => ['project_id' => 1, 'title' => 'Ödeme akışı entegrasyonu', 'assignee' => 'pm@deliverdesk.test', 'status' => 'İncelemede', 'priority' => 'Orta', 'due' => '2026-12-08', 'client_visible' => false, 'internal_note' => 'Test ortamı erişimi ekip içinde paylaşılacak.', 'revisions' => []],
        103 => ['project_id' => 1, 'title' => 'Mobil görünüm teslimi', 'assignee' => 'developer@deliverdesk.test', 'status' => 'Bekliyor', 'priority' => 'Orta', 'due' => '2026-12-12', 'client_visible' => true, 'internal_note' => '', 'revisions' => []],
        201 => ['project_id' => 2, 'title' => 'Ana sayfa tasarım onayı', 'assignee' => 'developer@deliverdesk.test', 'status' => 'Tamamlandı', 'priority' => 'Düşük', 'due' => '2026-11-28', 'client_visible' => true, 'internal_note' => '', 'revisions' => []],
        202 => ['project_id' => 2, 'title' => 'İç ekip içerik aktarımı', 'assignee' => 'pm@deliverdesk.test', 'status' => 'Devam ediyor', 'priority' => 'Yüksek', 'due' => '2026-12-06', 'client_visible' => false, 'internal_note' => 'İçerik aktarımı için kısa bir bakım aralığı planlandı.', 'revisions' => []],
        301 => ['project_id' => 3, 'title' => 'Sadakat puanı ekranı', 'assignee' => 'developer@deliverdesk.test', 'status' => 'Devam ediyor', 'priority' => 'Yüksek', 'due' => '2026-12-10', 'client_visible' => true, 'internal_note' => '', 'revisions' => []],
    ];
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectToSelf(): never
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function canSeeTask(array $task, array $project, array $user, string $email): bool
{
    if ($user['role'] === 'admin' || $user['role'] === 'manager') {
        return true;
    }

    if ($user['role'] === 'developer') {
        return $task['assignee'] === $email;
    }

    return $user['role'] === 'client'
        && $project['company'] === $user['company']
        && $project['client_visible']
        && $task['client_visible'];
}

$loginError = '';
$actionError = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $actionError = 'Oturum doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.';
    } elseif (($_POST['action'] ?? '') === 'login') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if (isset($users[$email]) && hash_equals($demoPassword, $password)) {
            session_regenerate_id(true);
            $_SESSION['user_email'] = $email;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            redirectToSelf();
        }
        $loginError = 'E-posta veya şifre hatalı.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        unset($_SESSION['user_email']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        redirectToSelf();
    } elseif (!isset($_SESSION['user_email']) || !isset($users[$_SESSION['user_email']])) {
        $actionError = 'Bu işlem için önce giriş yapmalısınız.';
    } else {
        $email = $_SESSION['user_email'];
        $user = $users[$email];
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $task = $taskId !== false && $taskId !== null ? ($_SESSION['tasks'][$taskId] ?? null) : null;
        $project = $task ? ($_SESSION['projects'][$task['project_id']] ?? null) : null;
        $action = (string)($_POST['action'] ?? '');

        if (!$task || !$project || !canSeeTask($task, $project, $user, $email)) {
            $actionError = 'Bu teslim kalemine erişim yetkiniz yok.';
        } elseif ($action === 'update_status') {
            $allowed = $user['role'] === 'manager'
                || ($user['role'] === 'developer' && $task['assignee'] === $email);
            $status = (string)($_POST['status'] ?? '');
            if (!$allowed || !in_array($status, ['Bekliyor', 'Devam ediyor', 'İncelemede', 'Tamamlandı'], true)) {
                $actionError = 'Görev durumu güncellenemedi.';
            } else {
                $_SESSION['tasks'][$taskId]['status'] = $status;
                $_SESSION['flash'] = 'Görev durumu güncellendi.';
                redirectToSelf();
            }
        } elseif ($action === 'update_task' && $user['role'] === 'manager') {
            $priority = (string)($_POST['priority'] ?? '');
            $due = (string)($_POST['due'] ?? '');
            $date = DateTime::createFromFormat('Y-m-d', $due);
            if (!in_array($priority, ['Düşük', 'Orta', 'Yüksek'], true) || !$date || $date->format('Y-m-d') !== $due) {
                $actionError = 'Öncelik veya teslim tarihi geçersiz.';
            } else {
                $_SESSION['tasks'][$taskId]['priority'] = $priority;
                $_SESSION['tasks'][$taskId]['due'] = $due;
                $_SESSION['flash'] = 'Teslim kalemi güncellendi.';
                redirectToSelf();
            }
        } elseif ($action === 'save_internal_note'
            && in_array($user['role'], ['manager', 'developer'], true)
            && ($user['role'] === 'manager' || $task['assignee'] === $email)) {
            $note = trim((string)($_POST['internal_note'] ?? ''));
            if (mb_strlen($note) > 500) {
                $actionError = 'İç not en fazla 500 karakter olabilir.';
            } else {
                $_SESSION['tasks'][$taskId]['internal_note'] = $note;
                $_SESSION['flash'] = 'İç not kaydedildi.';
                redirectToSelf();
            }
        } elseif ($action === 'add_revision' && $user['role'] === 'client' && $task['client_visible']) {
            $revision = trim((string)($_POST['revision'] ?? ''));
            if ($revision === '' || mb_strlen($revision) > 500) {
                $actionError = 'Revizyon notu 1–500 karakter arasında olmalıdır.';
            } else {
                $_SESSION['tasks'][$taskId]['revisions'][] = [
                    'author' => $user['name'],
                    'text' => $revision,
                    'created_at' => date('d.m.Y H:i'),
                ];
                $_SESSION['flash'] = 'Revizyon notunuz ekibe iletildi.';
                redirectToSelf();
            }
        } else {
            $actionError = 'Bu işlem için yetkiniz yok.';
        }
    }
}

$email = $_SESSION['user_email'] ?? '';
$user = isset($users[$email]) ? $users[$email] : null;
$visibleTasks = [];
$visibleProjects = [];

if ($user) {
    foreach ($_SESSION['tasks'] as $id => $task) {
        $project = $_SESSION['projects'][$task['project_id']];
        if (canSeeTask($task, $project, $user, $email)) {
            $visibleTasks[$id] = $task;
            $visibleProjects[$task['project_id']] = $project;
        }
    }
    if (in_array($user['role'], ['admin', 'manager'], true)) {
        $visibleProjects = $_SESSION['projects'];
    }
}

$taskCount = count($visibleTasks);
$completedCount = count(array_filter($visibleTasks, function (array $task): bool {
    return $task['status'] === 'Tamamlandı';
}));
$activeCount = count(array_filter($visibleTasks, function (array $task): bool {
    return $task['status'] !== 'Tamamlandı';
}));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= $user ? 'Genel Bakış · DeliverDesk' : 'Giriş Yap · DeliverDesk' ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php if (!$user): ?>
    <main class="login-layout">
        <section class="login-story">
            <a class="brand brand-light" href="./" aria-label="DeliverDesk ana sayfa">
                <span class="brand-mark">D</span><span>deliver<span class="brand-accent">desk</span></span>
            </a>
            <div class="story-copy">
                <span class="eyebrow light-eyebrow">PROJELERİNİZ, TEK BİR YERDE</span>
                <h1>İyi işler,<br>iyi organize edilir.</h1>
                <p>Projeleri, teslimleri ve müşteri geri bildirimlerini ekibinizle aynı akışta yönetin.</p>
            </div>
            <div class="story-footer"><span class="story-dot"></span> Ekibinizle birlikte, her adımda.</div>
            <span class="decor decor-one"></span><span class="decor decor-two"></span>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <div class="mobile-brand brand">
                    <span class="brand-mark">D</span><span>deliver<span class="brand-accent">desk</span></span>
                </div>
                <span class="eyebrow">ÇALIŞMA ALANINIZA DÖNÜN</span>
                <h2>Tekrar hoş geldiniz</h2>
                <p class="muted login-intro">Devam etmek için hesabınızla giriş yapın.</p>
                <?php if ($loginError !== ''): ?><div class="alert alert-error" role="alert"><?= escape($loginError) ?></div><?php endif; ?>
                <form method="post" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="login">
                    <label for="email">E-posta adresi</label>
                    <input id="email" name="email" type="email" placeholder="ornek@sirket.com" autocomplete="username" required>
                    <label for="password">Şifre</label>
                    <input id="password" name="password" type="password" placeholder="Şifrenizi girin" autocomplete="current-password" required>
                    <button class="button button-primary button-wide" type="submit">Giriş yap <span aria-hidden="true">→</span></button>
                </form>
                <div class="demo-box">
                    <div class="demo-heading"><span class="demo-icon">✦</span><div><strong>Demo çalışma alanı</strong><small>Önizleme için demo hesaplarından birini seçin.</small></div></div>
                    <div class="demo-accounts">
                        <button type="button" class="demo-account" data-email="admin@deliverdesk.test"><span class="avatar avatar-purple">DY</span><span><strong>Yönetici</strong><small>admin@deliverdesk.test</small></span><span class="account-arrow">↗</span></button>
                        <button type="button" class="demo-account" data-email="pm@deliverdesk.test"><span class="avatar avatar-blue">ED</span><span><strong>Proje yöneticisi</strong><small>pm@deliverdesk.test</small></span><span class="account-arrow">↗</span></button>
                        <button type="button" class="demo-account" data-email="developer@deliverdesk.test"><span class="avatar avatar-orange">MK</span><span><strong>Uzman</strong><small>developer@deliverdesk.test</small></span><span class="account-arrow">↗</span></button>
                        <button type="button" class="demo-account" data-email="client@deliverdesk.test"><span class="avatar avatar-green">SA</span><span><strong>Müşteri temsilcisi</strong><small>client@deliverdesk.test</small></span><span class="account-arrow">↗</span></button>
                    </div>
                    <p class="demo-password">Tüm demo hesaplarının şifresi: <strong>demo123</strong></p>
                </div>
                <p class="login-legal">Giriş yaparak kullanım koşullarını kabul etmiş olursunuz.</p>
            </div>
        </section>
    </main>
<?php else: ?>
    <?php
    $initials = implode('', array_map(function (string $part): string {
        return mb_substr($part, 0, 1);
    }, explode(' ', $user['name'])));
    $avatarClasses = ['admin' => 'avatar-purple', 'manager' => 'avatar-blue', 'developer' => 'avatar-orange', 'client' => 'avatar-green'];
    ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="./"><span class="brand-mark">D</span><span>deliver<span class="brand-accent">desk</span></span></a>
            <div class="workspace-switch"><span class="workspace-icon">✦</span><span><strong>Studio North</strong><small>Çalışma alanı</small></span><span class="switch-chevron">⌄</span></div>
            <span class="nav-caption">MENÜ</span>
            <nav class="main-nav" aria-label="Ana menü">
                <a class="nav-link active" href="#overview"><span class="nav-icon">▦</span> Genel bakış</a>
                <a class="nav-link" href="#projects"><span class="nav-icon">▧</span> Projeler <span class="nav-count"><?= count($visibleProjects) ?></span></a>
                <a class="nav-link" href="#tasks"><span class="nav-icon">☷</span> Teslim kalemleri <span class="nav-count"><?= $taskCount ?></span></a>
                <?php if (in_array($user['role'], ['manager', 'admin'], true)): ?>
                    <a class="nav-link" href="#team"><span class="nav-icon">♧</span> Ekip</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="help-card"><span class="help-symbol">?</span><strong>Bir şeye mi takıldınız?</strong><p>Ekibinizden destek isteyin.</p><a href="mailto:destek@deliverdesk.test">Destek merkezi <span>→</span></a></div>
                <div class="sidebar-user">
                    <span class="avatar <?= escape($avatarClasses[$user['role']]) ?>"><?= escape($initials) ?></span>
                    <span class="user-meta"><strong><?= escape($user['name']) ?></strong><small><?= escape($roleNames[$user['role']]) ?></small></span>
                    <form method="post" class="logout-form">
                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="icon-button" type="submit" aria-label="Çıkış yap" title="Çıkış yap">↗</button>
                    </form>
                </div>
            </div>
        </aside>
        <main class="main-content" id="overview">
            <header class="topbar">
                <div class="breadcrumbs">Çalışma alanı <span>/</span> <strong>Genel bakış</strong></div>
                <div class="topbar-actions"><span class="today-label"><?= date('d.m.Y') ?></span><button class="icon-button notification-button" type="button" aria-label="Bildirimler">♧<i></i></button><span class="avatar avatar-small <?= escape($avatarClasses[$user['role']]) ?>"><?= escape($initials) ?></span></div>
            </header>
            <div class="page-content">
                <?php if ($flash !== ''): ?><div class="alert alert-success" role="status"><?= escape($flash) ?></div><?php endif; ?>
                <?php if ($actionError !== ''): ?><div class="alert alert-error" role="alert"><?= escape($actionError) ?></div><?php endif; ?>
                <section class="welcome-row">
                    <div><span class="eyebrow">SALI, 24 KASIM 2026</span><h1>Merhaba, <?= escape(explode(' ', $user['name'])[0]) ?> <span class="wave">✦</span></h1><p class="muted"><?= $user['role'] === 'client' ? 'Projelerinizdeki güncel teslimleri ve ekipten gelen notları buradan takip edin.' : 'Projelerinizde neler olup bittiğine bir göz atın.' ?></p></div>
                    <span class="period-pill"><span class="period-dot"></span> Bu dönem</span>
                </section>
                <section class="stats-grid" aria-label="Özet bilgiler">
                    <article class="stat-card"><div class="stat-top"><span>Görüntülenen projeler</span><span class="stat-icon stat-violet">▧</span></div><strong><?= count($visibleProjects) ?></strong><small><span class="stat-neutral">Aktif çalışma alanınız</span></small></article>
                    <article class="stat-card"><div class="stat-top"><span><?= $user['role'] === 'client' ? 'Teslim kalemleri' : 'Aktif teslim kalemleri' ?></span><span class="stat-icon stat-blue">☷</span></div><strong><?= $activeCount ?></strong><small><span class="stat-neutral">Takip edilen işler</span></small></article>
                    <article class="stat-card"><div class="stat-top"><span>Tamamlanan</span><span class="stat-icon stat-green">✓</span></div><strong><?= $completedCount ?></strong><small><span class="stat-neutral">Teslim edilen kalemler</span></small></article>
                </section>
                <section class="section-block" id="projects">
                    <div class="section-heading"><div><span class="eyebrow">ÇALIŞMA ALANINIZ</span><h2>Projeler</h2></div><a class="text-link" href="#tasks">Tüm teslimleri gör <span>→</span></a></div>
                    <div class="project-grid">
                        <?php foreach ($visibleProjects as $projectId => $project): ?>
                            <?php
                            $projectTasks = array_filter($visibleTasks, function (array $task) use ($projectId): bool {
                                return (int)$task['project_id'] === (int)$projectId;
                            });
                            $projectDone = count(array_filter($projectTasks, function (array $task): bool {
                                return $task['status'] === 'Tamamlandı';
                            }));
                            $progress = count($projectTasks) > 0 ? (int)round($projectDone / count($projectTasks) * 100) : 0;
                            $projectColors = ['1' => 'project-art-violet', '2' => 'project-art-blue', '3' => 'project-art-orange'];
                            ?>
                            <article class="project-card">
                                <div class="project-art <?= escape($projectColors[(string)$projectId] ?? 'project-art-violet') ?>"><span class="art-orbit orbit-a"></span><span class="art-orbit orbit-b"></span><span class="art-symbol"><?= (int)$projectId === 1 ? '✳' : ((int)$projectId === 2 ? '◈' : '✦') ?></span><span class="project-menu">···</span></div>
                                <div class="project-body">
                                    <div class="project-title-line"><h3><?= escape($project['name']) ?></h3><span class="project-status"><i></i> Aktif</span></div>
                                    <p class="project-company"><?= escape($project['company']) ?> <span>·</span> <?= count($projectTasks) ?> teslim kalemi</p>
                                    <p class="project-description"><?= escape($project['description']) ?></p>
                                    <div class="progress-label"><span>İlerleme</span><strong><?= $progress ?>%</strong></div>
                                    <div class="progress-track"><span style="width: <?= $progress ?>%"></span></div>
                                    <?php if (in_array($user['role'], ['manager', 'developer'], true) && $project['note'] !== ''): ?>
                                        <div class="project-note"><span>İç ekip notu</span><?= escape($project['note']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="section-block tasks-section" id="tasks">
                    <div class="section-heading"><div><span class="eyebrow"><?= $user['role'] === 'client' ? 'SİZİN İÇİN PAYLAŞILANLAR' : 'EKİP TAKİBİ' ?></span><h2><?= $user['role'] === 'client' ? 'Teslimler ve revizyonlar' : 'Son teslim kalemleri' ?></h2></div><span class="table-count"><?= $taskCount ?> kalem</span></div>
                    <div class="task-list">
                        <?php foreach ($visibleTasks as $taskId => $task): ?>
                            <?php
                            $project = $_SESSION['projects'][$task['project_id']];
                            $statusClass = ['Tamamlandı' => 'status-done', 'Devam ediyor' => 'status-progress', 'İncelemede' => 'status-review', 'Bekliyor' => 'status-waiting'][$task['status']];
                            $priorityClass = ['Yüksek' => 'priority-high', 'Orta' => 'priority-medium', 'Düşük' => 'priority-low'][$task['priority']];
                            ?>
                            <article class="task-card">
                                <div class="task-main">
                                    <span class="task-check <?= $task['status'] === 'Tamamlandı' ? 'checked' : '' ?>"><?= $task['status'] === 'Tamamlandı' ? '✓' : '' ?></span>
                                    <div class="task-title-group"><h3><?= escape($task['title']) ?></h3><p><?= escape($project['name']) ?> <span>·</span> <?= $task['client_visible'] ? 'Müşteriyle paylaşılıyor' : 'İç ekip işi' ?></p></div>
                                </div>
                                <div class="task-meta">
                                    <span class="priority-pill <?= escape($priorityClass) ?>"><i></i><?= escape($task['priority']) ?></span>
                                    <span class="task-date"><?= escape(date('d.m.Y', strtotime($task['due']))) ?></span>
                                    <span class="status-pill <?= escape($statusClass) ?>"><i></i><?= escape($task['status']) ?></span>
                                </div>
                                <?php if ($user['role'] === 'manager'): ?>
                                    <form method="post" class="task-controls">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="update_task"><input type="hidden" name="task_id" value="<?= (int)$taskId ?>">
                                        <label class="sr-only" for="priority-<?= (int)$taskId ?>">Öncelik</label><select id="priority-<?= (int)$taskId ?>" name="priority"><option <?= $task['priority'] === 'Düşük' ? 'selected' : '' ?>>Düşük</option><option <?= $task['priority'] === 'Orta' ? 'selected' : '' ?>>Orta</option><option <?= $task['priority'] === 'Yüksek' ? 'selected' : '' ?>>Yüksek</option></select>
                                        <label class="sr-only" for="due-<?= (int)$taskId ?>">Teslim tarihi</label><input id="due-<?= (int)$taskId ?>" type="date" name="due" value="<?= escape($task['due']) ?>"><button class="button button-small" type="submit">Kaydet</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'manager' || ($user['role'] === 'developer' && $task['assignee'] === $email)): ?>
                                    <form method="post" class="status-form">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="task_id" value="<?= (int)$taskId ?>">
                                        <label class="sr-only" for="status-<?= (int)$taskId ?>">Görev durumunu değiştir</label><select id="status-<?= (int)$taskId ?>" name="status"><option <?= $task['status'] === 'Bekliyor' ? 'selected' : '' ?>>Bekliyor</option><option <?= $task['status'] === 'Devam ediyor' ? 'selected' : '' ?>>Devam ediyor</option><option <?= $task['status'] === 'İncelemede' ? 'selected' : '' ?>>İncelemede</option><option <?= $task['status'] === 'Tamamlandı' ? 'selected' : '' ?>>Tamamlandı</option></select><button class="button button-small button-quiet" type="submit">Durumu güncelle</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($user['role'], ['manager', 'developer'], true) && ($user['role'] === 'manager' || $task['assignee'] === $email)): ?>
                                    <form method="post" class="note-form">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save_internal_note"><input type="hidden" name="task_id" value="<?= (int)$taskId ?>">
                                        <label for="note-<?= (int)$taskId ?>">Ekip içi not</label><textarea id="note-<?= (int)$taskId ?>" name="internal_note" maxlength="500" rows="2" placeholder="Yalnızca ekip tarafından görülebilir"><?= escape($task['internal_note']) ?></textarea><button class="button button-small button-quiet" type="submit">Notu kaydet</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'client'): ?>
                                    <?php foreach ($task['revisions'] as $revision): ?>
                                        <div class="revision-item"><strong><?= escape($revision['author']) ?> <span>· <?= escape($revision['created_at']) ?></span></strong><p><?= escape($revision['text']) ?></p></div>
                                    <?php endforeach; ?>
                                    <form method="post" class="revision-form">
                                        <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="add_revision"><input type="hidden" name="task_id" value="<?= (int)$taskId ?>">
                                        <label class="sr-only" for="revision-<?= (int)$taskId ?>">Revizyon notu</label><input id="revision-<?= (int)$taskId ?>" name="revision" maxlength="500" placeholder="Bu teslim için bir revizyon notu yazın..." required><button class="button button-small button-primary" type="submit">Not gönder <span>→</span></button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($taskCount === 0): ?><div class="empty-state"><span>☷</span><strong>Henüz görüntülenecek teslim yok</strong><p>Size atanmış işler olduğunda burada görünecek.</p></div><?php endif; ?>
                    </div>
                </section>
                <?php if (in_array($user['role'], ['manager', 'admin'], true)): ?>
                    <section class="section-block team-section" id="team">
                        <div class="section-heading"><div><span class="eyebrow">ÇALIŞMA ALANI</span><h2>Ekip erişimleri</h2></div><span class="table-count">4 demo hesap</span></div>
                        <div class="team-strip">
                            <?php foreach ($users as $memberEmail => $member): ?>
                                <?php $memberInitials = implode('', array_map(function (string $part): string { return mb_substr($part, 0, 1); }, explode(' ', $member['name']))); ?>
                                <div class="team-member"><span class="avatar <?= escape($avatarClasses[$member['role']]) ?>"><?= escape($memberInitials) ?></span><span><strong><?= escape($member['name']) ?></strong><small><?= escape($roleNames[$member['role']]) ?></small></span><span class="member-state"><i></i> Aktif</span></div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                <footer class="page-footer"><span>© 2026 DeliverDesk</span><span>İşlerin akışında.</span></footer>
            </div>
        </main>
    </div>
<?php endif; ?>
<script>
document.querySelectorAll('.demo-account').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('email').value = button.dataset.email;
        document.getElementById('password').value = 'demo123';
        document.querySelector('.login-form').requestSubmit();
    });
});
</script>
</body>
</html>
