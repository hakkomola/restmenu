<?php
session_start();
if (isset($_SESSION['restaurant_id'])) {
    header('Location: restaurants/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <link rel="icon" type="image/png" href="images/menufav.ico">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VovMenu - Restoran Menü Oluştur</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { font-family: Arial, sans-serif; background: #f8f9fa; }

    /* Hero alanı ve overlay */
    .hero {
        position: relative;
        background: url('images/herobackground.jpeg') center/cover no-repeat;
        color: white;
        text-align: center;
        padding: 100px 20px;
        border-radius: 0 0 30px 30px;
        margin-bottom: 40px;
    }

    /* Overlay ekleniyor */
    .hero::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0,0,0,0.4); /* yarı saydam karartma */
        border-radius: 0 0 30px 30px;
        z-index: 0;
    }

    .hero h1, .hero p, .hero a {
        position: relative;
        z-index: 1; /* overlay üstünde görünmesi için */
        text-shadow: 0 2px 6px rgba(0,0,0,0.5);
    }

    .hero h1 { font-weight: bold; font-size: 3rem; }

    /* Özellik kartları */
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

    /* Demo Menü Kartı */
    .demo-card { 
        border-radius: 20px; 
        padding: 30px; 
        background: #fff3e0; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
    }

    footer { 
        margin-top: 50px; 
        padding: 20px; 
        text-align: center; 
        background: #343a40; 
        color: white; 
        border-radius: 20px 20px 0 0; 
    }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">Vov Menu</a>
        <div class="d-flex">
            <a href="restaurants/login.php" class="btn btn-light me-2">Giriş Yap</a>
            <a href="restaurants/register.php" class="btn btn-outline-light">Üye Ol</a>
        </div>
    </div>
</nav>

<!-- Hero Alanı -->
<div class="hero">
    <h1>Kendi Restoran Menünüzü VovMenu ile Ücretsiz Oluşturun</h1>
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
        <p class="card-text mb-4">Vov Menu kullanılarak oluşturulmuş örnek menüyü inceleyebilirsiniz.</p>
        <a href="restaurant_info.php?hash=c4ca4238a0b923820dcc509a6f75849b&theme=dark&lang=tr" class="btn btn-success btn-lg" target="_blank">Menüyü Gör</a>
    </div>
</section>

<footer>
    &copy; <?= date('Y') ?> VovMenu
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
