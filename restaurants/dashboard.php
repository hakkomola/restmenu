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

// Menü hashli linki oluştur
$hash = md5($restaurantId); // Restoran ID’den tek hash
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base = str_replace('/restaurants','', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')); // /restmenu

$menuLink = $scheme . '://' . $host . $base . '/menu.php?hash=' . $hash;
$qrImg = $scheme . '://' . $host . $base . '/generate_qr.php?hash=' . $hash;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?= htmlspecialchars($restaurantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.qr-img { height: 150px; }
</style>
</head>
<body>

<div class="container mt-5">
    <h2>Public Menü Linki ve QR Kod</h2>

    <div class="card mb-4 text-center">
        <div class="card-body">
            <p>Menünüze herkesin erişebilmesi için aşağıdaki linki veya QR kodu kullanabilirsiniz:</p>
            <a href="<?= htmlspecialchars($menuLink) ?>" target="_blank" class="btn btn-outline-primary mb-3">Menüyü Aç</a>
            <div>
                <img src="<?= htmlspecialchars($qrImg) ?>" class="qr-img" alt="QR Kod">
            </div>
            <p class="mt-2"><small class="text-muted"><?= htmlspecialchars($menuLink) ?></small></p>
        </div>
    </div>

    <div class="row g-4 mt-4">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Kategoriler</h5>
                    <p class="card-text">Kendi menü kategorilerinizi ekleyebilir, düzenleyebilir veya silebilirsiniz.</p>
                    <a href="../categories/list.php" class="btn btn-primary">Kategorileri Yönet</a>
                </div>
            </div>
        </div>
                <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Alt Kategoriler</h5>
                    <p class="card-text">Her kategori için alt kategoriler oluşturabilir, düzenleyebilir veya silebilirsiniz.</p>
                    <a href="../subcategories/list.php" class="btn btn-primary">Alt Kategorileri Yönet</a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Menü Öğeleri</h5>
                    <p class="card-text">Menü öğelerinizi ekleyin, düzenleyin veya silin. Birden fazla resim ekleyebilirsiniz.</p>
                    <a href="../items/list.php" class="btn btn-primary">Menü Öğelerini Yönet</a>
                </div>
            </div>
        </div>

 <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Şifre Değiştirme</h5>
                    <p class="card-text">Şifrenizi değiştirebilirsiniz.</p>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#changePassModal">
            Şifre Değiştir
        </button>
                </div>
            </div>
        </div>

    <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Restoran Bilgileri</h5>
                    <p class="card-text">Restoran Bilgilerinizi düzenleyin.</p>
                    <a href="../restaurants/profile.php" class="btn btn-warning">Restoran Bilgilerim</a>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Şifre Değiştir Modal -->
<div class="modal fade" id="changePassModal" tabindex="-1" aria-labelledby="changePassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="change_password.php" method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePassModalLabel">Şifre Değiştir</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label for="oldPassword" class="form-label">Mevcut Şifre</label>
              <input type="password" class="form-control" name="oldPassword" id="oldPassword" required>
          </div>
          <div class="mb-3">
              <label for="newPassword" class="form-label">Yeni Şifre</label>
              <input type="password" class="form-control" name="newPassword" id="newPassword" required>
          </div>
          <div class="mb-3">
              <label for="confirmPassword" class="form-label">Yeni Şifre (Tekrar)</label>
              <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" required>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
        <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
      </div>
    </form>
  </div>
</div>


<footer class="bg-light text-center py-3 mt-5">
    &copy; <?= date('Y') ?> Restoran Menü Uygulaması
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
