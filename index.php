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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { font-family: Arial, sans-serif; background: #f8f9fa; }
    .hero {
        background: url('images/herobackground.jpeg') center/cover no-repeat;
        color: white;
        text-align: center;
        padding: 100px 20px;
        border-radius: 0 0 30px 30px;
        margin-bottom: 40px;
        text-shadow: 0 2px 6px rgba(0,0,0,0.5);
    }
    .hero h1 { font-weight: bold; font-size: 3rem; }
    .feature-card {
        border-radius: 20px;
        padding: 30px 20px;
        background: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: transform 0.2s;
        text-align: center;
    }
    .feature-card:hover { transform: scale(1.05); }
    .feature-card i { font-size: 3rem; color: #ff7e5f; margin-bottom: 15px; }
    .demo-card { border-radius: 20px; padding: 30px; background: #fff3e0; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    footer { margin-top: 50px; padding: 20px; text-align: center; background: #343a40; color: white; border-radius: 20px 20px 0 0; }
</style>
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

<!-- Hero Alanı -->
<div class="hero">
    <h1>Kendi Restoran Menünüzü Ücretsiz Oluşturun</h1>
    <p class="lead mb-4">Menülerinizi kolayca ekleyin, kategorilere ayırın ve müşterilerinize gösterin.</p>
    <a href="restaurants/register.php" class="btn btn-light btn-lg me-2">Hemen Üye Ol</a>
    <a href="restaurants/login.php" class="btn btn-outline-light btn-lg">Giriş Yap</a>
</div>

<!-- Özellikler -->
<section class="container py-5">
    <div class="row text-center g-4">
        <div class="col-md-4">
            <div class="feature-card">
                <i class="bi bi-speedometer2"></i>
                <h3>Kolay Kullanım</h3>
                <p>Basit arayüz ile menülerinizi hızlıca oluşturabilirsiniz.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <i class="bi bi-cash-stack"></i>
                <h3>Ücretsiz</h3>
                <p>Herhangi bir ücret ödemeden restoran menünüzü oluşturun ve yönetin.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <i class="bi bi-phone"></i>
                <h3>Responsive</h3>
                <p>Mobil ve tablet cihazlarda da mükemmel görünüm ve kullanım deneyimi.</p>
            </div>
        </div>
    </div>
</section>

<!-- Demo Menü Linki -->
<section class="container text-center py-5">
    <div class="demo-card mx-auto" style="max-width: 500px;">
        <h4 class="card-title mb-3"><i class="bi bi-book"></i> Örnek Menü</h4>
        <p class="card-text mb-4">Bu site kullanılarak oluşturulmuş örnek menüyü inceleyebilirsiniz.</p>
        <a href="menu.php?hash=c81e728d9d4c2f636f067f89cc14862c" class="btn btn-success btn-lg" target="_blank">Menüyü Gör</a>
    </div>
</section>

<footer>
    &copy; <?= date('Y') ?> Ücretsiz Restoran Menüsü
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
