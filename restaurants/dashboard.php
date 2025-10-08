<?php
session_start();
if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

$restaurantId = $_SESSION['restaurant_id'];
$restaurantName = $_SESSION['restaurant_name'] ?? 'Restoran';

// Menü hashli linkleri oluştur
$hash = md5($restaurantId);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base = str_replace('/restaurants', '', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

// menu.php
$menuLink = $scheme . '://' . $host . $base . '/restaurant_info.php?hash=' . $hash . '&theme=light&lang=tr';
$qrImg = $scheme . '://' . $host . $base . '/generate_qr.php?hash=' . $hash;

// menu2.php
$menu2Link = $scheme . '://' . $host . $base . '/restaurant_info.php?hash=' . $hash . '&theme=dark&lang=tr';
$qr2Img = $scheme . '://' . $host . $base . '/generate_qr.php?hash=' . $hash . '&menu=2';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?= htmlspecialchars($restaurantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    background-color: #f7f8fa;
}
.navbar {
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.restaurant-header {
    background: #fff;
    border-radius: 12px;
    padding: 1.2rem;
    text-align: center;
    margin-top: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.restaurant-header h4 {
    margin: 0;
    color: #333;
    font-weight: 600;
}
.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}
.card .bi {
    font-size: 2.2rem;
    color: #0d6efd;
    margin-bottom: 10px;
}
footer {
    background: #fff;
    box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
}
.qr-img {
    height: 140px;
    border-radius: 10px;
}
@media (max-width: 768px) {
    .card .bi {
        font-size: 1.8rem;
    }
}
</style>
</head>
<body>

<div class="container">

    <div class="restaurant-header">
        <h4>Restoran Adı: <?= htmlspecialchars($restaurantName) ?></h4>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <div class="card text-center p-3">
                <div class="card-body">
                    <i class="bi bi-list-nested"></i>
                    <h5 class="card-title">Kategoriler</h5>
                    <p class="card-text text-muted">Menü kategorilerinizi ekleyin, düzenleyin veya silin.</p>
                    <a href="../categories/list.php" class="btn btn-outline-primary">Kategorileri Yönet</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center p-3">
                <div class="card-body">
                    <i class="bi bi-diagram-3"></i>
                    <h5 class="card-title">Alt Kategoriler</h5>
                    <p class="card-text text-muted">Her kategoriye ait alt kategoriler oluşturabilirsiniz.</p>
                    <a href="../subcategories/list.php" class="btn btn-outline-primary">Alt Kategorileri Yönet</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center p-3">
                <div class="card-body">
                    <i class="bi bi-card-text"></i>
                    <h5 class="card-title">Menü Öğeleri</h5>
                    <p class="card-text text-muted">Menü öğelerinizi yönetin, açıklama ve görsel ekleyin.</p>
                    <a href="../items/list.php" class="btn btn-outline-primary">Menü Öğelerini Yönet</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center p-3">
                <div class="card-body">
                    <i class="bi bi-lightning-charge"></i>
                    <h5 class="card-title">Kolay Menü</h5>
                    <p class="card-text text-muted">Hazır örnek menü üzerinden hızlıca düzenleme yapın.</p>
                    <a href="menu_tree.php" class="btn btn-outline-primary">Kolay Menü Oluştur</a>
                </div>
            </div>
        </div>
       <div class="col-md-6 col-lg-4">
    <div class="card text-center p-3">
        <div class="card-body">
            <i class="bi bi-lock"></i>
            <h5 class="card-title">Şifre Değiştirme</h5>
            <p class="card-text text-muted">Hesap şifrenizi güvenli şekilde değiştirebilirsiniz.</p>
            <a href="../restaurants/change_password.php" class="btn btn-outline-warning">
                Şifre Değiştir
            </a>
        </div>
    </div>
</div>

        <div class="col-md-6 col-lg-4">
            <div class="card text-center p-3">
                <div class="card-body">
                    <i class="bi bi-building"></i>
                    <h5 class="card-title">Restoran Bilgileri</h5>
                    <p class="card-text text-muted">Restoran detay bilgilerinizi düzenleyin.</p>
                    <a href="../restaurants/profile.php" class="btn btn-outline-warning">Restoran Bilgilerim</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center p-3">
                <div class="card-body">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <h5 class="card-title">Restoran Masaları</h5>
                    <p class="card-text text-muted">Masalarınızı oluşturun ve QR kodlarını yönetin.</p>
                    <a href="../restaurants/tables.php" class="btn btn-outline-warning">Restoran Masaları</a>
                </div>
            </div>
        </div>
    </div>
</div>


<footer class="text-center py-3 mt-5">
    &copy; <?= date('Y') ?> Restoran Menü Uygulaması
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
