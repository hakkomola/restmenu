<?php
// index.php
session_start();
if (isset($_SESSION['restaurant_id'])) {
    header('Location: restaurants/dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Menü Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">Ücretsiz Restoran Menüsü</a>
        <div class="d-flex">
            <a href="restaurants/login.php" class="btn btn-light me-2">Giriş Yap</a>
            <a href="restaurants/register.php" class="btn btn-outline-light">Üye Ol</a>
        </div>
    </div>
</nav>

<header class="text-center py-5 bg-light">
    <h1 class="display-5 mb-3">Kendi Restoran Menünüzü Ücretsiz Oluşturun</h1>
    <p class="lead mb-4">Menülerinizi kolayca ekleyin, kategorilere ayırın ve müşterilerinize gösterin.</p>
    <a href="restaurants/register.php" class="btn btn-primary btn-lg me-2">Hemen Üye Ol</a>
    <a href="restaurants/login.php" class="btn btn-outline-primary btn-lg">Giriş Yap</a>
</header>

<section class="container py-5">
    <div class="row text-center">
        <div class="col-md-4 mb-4">
            <h3>Kolay Kullanım</h3>
            <p>Basit arayüz ile menülerinizi hızlıca oluşturabilirsiniz.</p>
        </div>
        <div class="col-md-4 mb-4">
            <h3>Ücretsiz</h3>
            <p>Herhangi bir ücret ödemeden restoran menünüzü oluşturun ve yönetin.</p>
        </div>
        <div class="col-md-4 mb-4">
            <h3>Responsive</h3>
            <p>Mobil ve tablet cihazlarda da mükemmel görünüm ve kullanım deneyimi.</p>
        </div>
    </div>
</section>

<footer class="bg-primary text-light text-center py-3">
    &copy; <?= date('Y') ?> Ücretsiz Restoran Menüsü
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
