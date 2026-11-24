<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$showDemoAccounts = (getenv('APP_ENV') ?: 'production') === 'local';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $statement = db()->prepare(
        'SELECT id, password_hash, is_active FROM users WHERE email = ? LIMIT 1'
    );
    $statement->execute([$email]);
    $account = $statement->fetch();

    if ($account && (int)$account['is_active'] === 1 && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$account['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        redirect('dashboard.php');
    }

    $error = 'E-posta veya şifre hatalı.';
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş | DeliverDesk</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
<main class="login-layout">
    <section class="login-story">
        <a class="brand brand-light" href="login.php"><span class="brand-mark">D</span><span>deliver<span class="brand-accent">desk</span></span></a>
        <div class="story-copy">
            <span class="eyebrow light-eyebrow">PROJELERİNİZ, TEK BİR YERDE</span>
            <h1>İyi işler,<br>iyi organize edilir.</h1>
            <p>Projeleri, teslimleri ve müşteri geri bildirimlerini ekibinizle aynı akışta yönetin.</p>
        </div>
        <div class="story-footer"><span class="story-dot"></span> Ekibinizle birlikte, her adımda.</div>
    </section>
    <section class="login-panel">
        <form class="login-card" method="post">
            <span class="eyebrow">ÇALIŞMA ALANINIZA DÖNÜN</span>
            <h2>Hoş geldiniz</h2>
            <p class="muted">Devam etmek için hesabınızla giriş yapın.</p>
            <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="email">E-posta adresi</label>
            <input id="email" name="email" type="email" autocomplete="username" required>
            <label for="password">Şifre</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="button button-primary button-wide" type="submit">Giriş yap <span aria-hidden="true">→</span></button>
            <?php if ($showDemoAccounts): ?><div class="demo-box">
                <strong>Demo hesapları</strong>
                <p>Örnek veritabanı kullanıcıları için şifre: <code>demo123</code></p>
                <small>Yönetici: admin@deliverdesk.test<br>Proje yöneticisi: pm1@deliverdesk.test<br>Uzman: uzman1@deliverdesk.test<br>Müşteri: musteri1@deliverdesk.test</small>
            </div><?php endif; ?>
        </form>
    </section>
</main>
</body>
</html>
