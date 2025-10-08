<?php
session_start();
if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$restaurantId = $_SESSION['restaurant_id'];
$restaurantName = $_SESSION['restaurant_name'] ?? 'Restoran';

// Menü hashli linki oluştur
$hash = md5($restaurantId); // Restoran ID’sinden tek hash
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base = str_replace('/restaurants','', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')); // /restmenu

$menuLink = $scheme . '://' . $host . $base . '/menu.php?hash=' . $hash;
$qrImg = $scheme . '://' . $host . $base . '/generate_qr.php?hash=' . $hash;

// Şifre değiştir formu gönderildiyse işle
$changeMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $newPass2 = $_POST['new_password2'] ?? '';

    if ($newPass !== $newPass2) {
        $changeMsg = 'Yeni şifreler eşleşmiyor!';
    } else {
        // Mevcut şifre kontrolü
        $stmt = $pdo->prepare("SELECT PasswordHash FROM Restaurants WHERE RestaurantID = ?");
        $stmt->execute([$restaurantId]);
        $rest = $stmt->fetch();
        if (!$rest || !password_verify($oldPass, $rest['PasswordHash'])) {
            $changeMsg = 'Mevcut şifre yanlış!';
        } else {
            // Şifreyi güncelle
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt2 = $pdo->prepare("UPDATE Restaurants SET PasswordHash = ? WHERE RestaurantID = ?");
            $stmt2->execute([$newHash, $restaurantId]);
            $changeMsg = 'Şifreniz başarıyla değiştirildi!';
        }
    }
}
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
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">Dashboard</a>
        <div class="d-flex">
            <span class="navbar-text me-3">Hoşgeldin, <?= htmlspecialchars($restaurantName) ?></span>
            <a href="logout.php" class="btn btn-light">Çıkış Yap</a> 
        </div>
    </div>
</nav>

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
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Kategoriler</h5>
                    <p class="card-text">Kendi menü kategorilerinizi ekleyebilir, düzenleyebilir veya silebilirsiniz.</p>
                    <a href="../categories/list.php" class="btn btn-primary">Kategorileri Yönet</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Menü Öğeleri</h5>
                    <p class="card-text">Menü öğelerinizi ekleyin, düzenleyin veya silin. Birden fazla resim ekleyebilirsiniz.</p>
                    <a href="../items/list.php" class="btn btn-primary">Menü Öğelerini Yönet</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Şifre Değiştir</h5>
                    <p class="card-text">Mevcut şifrenizi girerek yeni şifrenizi değiştirebilirsiniz.</p>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#changePassModal">Şifre Değiştir</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Şifre Değiştir Modal -->
<div class="modal fade" id="changePassModal" tabindex="-1" aria-labelledby="changePassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePassModalLabel">Şifre Değiştir</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <?php if($changeMsg): ?>
            <div class="alert alert-info"><?= htmlspecialchars($changeMsg) ?></div>
        <?php endif; ?>
        <div class="mb-3">
            <label>Mevcut Şifre</label>
            <input type="password" name="old_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Yeni Şifre</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Yeni Şifre (Tekrar)</label>
            <input type="password" name="new_password2" class="form-control" required>
        </div>
        <input type="hidden" name="change_password" value="1">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Kaydet</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
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
