<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MySQL kurulumu gerekli | DeliverDesk</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
<main class="setup-message">
    <span class="eyebrow">İLK KURULUM</span>
    <h1>MySQL bağlantısı henüz hazır değil</h1>
    <p>Önce <code>database/schema.sql</code> dosyasını MySQL’de çalıştırın. Ardından <code>.env.example</code> dosyasını web kökünün dışındaki <code>C:\laragon\etc\deliverdesk.env</code> konumuna kopyalayıp yerel MySQL bağlantı bilgilerinizi girin.</p>
    <p><code>database/app-user.sql</code> dosyasındaki parola yer tutucusunu değiştirerek sınırlı yetkili uygulama hesabını oluşturun. Aynı parolayı, kopyaladığınız <code>.env</code> dosyasındaki <code>DB_PASSWORD</code> alanına yazın. Şifreyi sohbete veya kaynak koda eklemeyin.</p>
    <a class="button button-primary" href="../login.php">Giriş sayfasına dön</a>
</main>
</body>
</html>
