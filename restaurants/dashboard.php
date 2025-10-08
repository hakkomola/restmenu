<?php
session_start();
if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';


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

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';



?>



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

<?php include __DIR__ . '/../includes/footer.php'; ?>