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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VovMenu - Restoran Menü Oluştur</title>
<link rel="icon" type="image/png" href="images/menufav.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f8f9fa;
}

/* HERO */
.hero {
    position: relative;
    background: linear-gradient(to bottom right, rgba(13,110,253,0.85), rgba(0,0,0,0.55)), 
                url('images/herobackground.jpeg') center/cover no-repeat;
    color: #fff;
    text-align: center;
    padding: 110px 25px;
    border-radius: 0 0 40px 40px;
    margin-bottom: 60px;
}
.hero h1 {
    font-weight: 700;
    font-size: 2.6rem;
}
.hero p {
    font-size: 1.2rem;
    color: #f1f1f1;
}
.hero .btn {
    margin: 0.3rem;
}

/* Feature Cards */
.feature-card {
    border-radius: 16px;
    padding: 30px 20px;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.25s ease-in-out;
    text-align: center;
}
.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
}
.feature-card i {
    font-size: 2.8rem;
    color: #0d6efd;
    margin-bottom: 15px;
}

/* Demo Card */
.demo-card {
    border-radius: 20px;
    padding: 40px 30px;
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    transition: 0.3s;
}
.demo-card:hover {
    transform: translateY(-3px);
}
.demo-card .btn {
    font-size: 1.1rem;
    padding: 12px 28px;
}

/* Footer */
footer {
    margin-top: 60px;
    padding: 25px 15px;
    text-align: center;
    background: #fff;
    color: #444;
    box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
    border-top: 1px solid #e5e5e5;
}
</style>
</head>

<body>

<?php include __DIR__ . '/includes/mainnavbar.php'; ?>

<!-- HERO -->
<div class="hero">
    <div class="container">
        <h1>Kendi Restoran Menünüzü Ücretsiz Oluşturun</h1>
        <p class="lead mb-4">Kategoriler ekleyin, menülerinizi düzenleyin ve müşterilerinize dijital menü sunun.</p>
        <a href="restaurants/register.php" class="btn btn-light btn-lg">
            <i class="bi bi-person-plus me-1"></i> Hemen Üye Ol
        </a>
        <a href="restaurants/login.php" class="btn btn-outline-light btn-lg">
            <i class="bi bi-box-arrow-in-right me-1"></i> Giriş Yap
        </a>
    </div>
</div>

<!-- Özellikler -->
<section class="container py-5">
    <div class="row text-center g-4">
        <div class="col-md-4">
            <div class="feature-card">
                <i class="bi bi-speedometer2"></i>
                <h4 class="fw-semibold">Kolay Kullanım</h4>
                <p>Basit arayüz sayesinde menülerinizi saniyeler içinde oluşturabilirsiniz.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <i class="bi bi-cash-stack"></i>
                <h4 class="fw-semibold">Ücretsiz</h4>
                <p>Herhangi bir ücret ödemeden restoran menünüzü dijital hale getirin.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <i class="bi bi-phone"></i>
                <h4 class="fw-semibold">Mobil Uyumlu</h4>
                <p>Telefon, tablet ve bilgisayarda mükemmel görünüm.</p>
            </div>
        </div>
    </div>
</section>

<!-- Demo Menü -->
<section class="container text-center py-5">
    <div class="demo-card mx-auto" style="max-width: 520px;">
        <h4 class="mb-3"><i class="bi bi-book"></i> Örnek Menü</h4>
        <p class="text-muted mb-4">VovMenu ile hazırlanmış örnek menüyü inceleyin.</p>
        <a href="restaurant_info.php?hash=65a7e0bc3485b8738c6d7387&theme=dark" 
           class="btn btn-success btn-lg" target="_blank">
           Menüyü Gör
        </a>
    </div>
</section>

<!-- Demo Menü menu_order.php?hash=5478d80090511813af5bff4a&theme=dark&lang=tr -->
<section class="container text-center py-5">
    <div class="demo-card mx-auto" style="max-width: 520px;">
        <h4 class="mb-3"><i class="bi bi-book"></i> Örnek Menü</h4>
        <p class="text-muted mb-4">VovMenu ile hazırlanmış örnek menüyü inceleyin.</p>
        <a href="restaurant_info.php?hash=65a7e0bc3485b8738c6d7387&theme=dark" 
           class="btn btn-success btn-lg" target="_blank">
           Menüyü Gör
        </a>
    </div>
</section>




<footer>
    &copy; <?= date('Y') ?> VovMenu — Tüm Hakları Saklıdır
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
