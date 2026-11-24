<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_auth();
$pageTitle = 'Profil ayarları';
$activePage = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['display_name'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'İsim 1–120 karakter arasında olmalıdır.';
    }
    if ($newPassword !== '' && mb_strlen($newPassword) < 10) {
        $errors[] = 'Yeni şifre en az 10 karakter olmalıdır.';
    }
    if (!$errors && $newPassword !== '') {
        $passwordQuery = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $passwordQuery->execute([$user['id']]);
        if (!password_verify($currentPassword, (string)$passwordQuery->fetchColumn())) {
            $errors[] = 'Mevcut şifre doğru değil.';
        }
    }
    if (!$errors) {
        if ($newPassword !== '') {
            $update = db()->prepare('UPDATE users SET display_name = ?, password_hash = ? WHERE id = ?');
            $update->execute([$name, password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        } else {
            $update = db()->prepare('UPDATE users SET display_name = ? WHERE id = ?');
            $update->execute([$name, $user['id']]);
        }
        set_flash('Profil ayarları kaydedildi.');
        redirect('profile.php');
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">HESAP</span><h1>Profil ayarları</h1><p>Adını ve giriş şifreni güncelle.</p></div>
</section>
<section class="section-block create-panel profile-panel">
    <?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>Ad soyad<input name="display_name" value="<?= e($_POST['display_name'] ?? $user['display_name']) ?>" maxlength="120" required></label>
        <label>E-posta<input value="<?= e($user['email']) ?>" disabled></label>
        <label>Rol<input value="<?= e(role_label($user['role'])) ?>" disabled></label>
        <label>Şirket<input value="<?= e($user['company_name'] ?: 'İç ekip') ?>" disabled></label>
        <div class="form-divider form-full"><strong>Şifre değiştir</strong><span>Şifreyi değiştirmiyorsan bu alanları boş bırak.</span></div>
        <label>Mevcut şifre<input name="current_password" type="password" autocomplete="current-password"></label>
        <label>Yeni şifre<input name="new_password" type="password" minlength="10" autocomplete="new-password"><small>En az 10 karakter.</small></label>
        <div class="form-full"><button class="button button-primary" type="submit">Değişiklikleri kaydet</button></div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
