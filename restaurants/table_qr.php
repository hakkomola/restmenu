<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

$restaurantId   = $_SESSION['restaurant_id'];
$restaurantName = $_SESSION['restaurant_name'] ?? 'Restoran';

$hash = $_GET['hash'] ?? null;
if (!$hash) {
    die('Hash parametresi bulunamadı.');
}

/**
 * tables.php dosyasındakiyle aynı hash çözümleme formülü
 */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}
function table_code_from_hash($pdo, $restaurantId, $hash) {
    $stmt = $pdo->prepare("SELECT Code FROM RestaurantTables WHERE RestaurantID = ?");
    $stmt->execute([$restaurantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $generated = substr(hash('sha256', $restaurantId . '|' . $r['Code'] . '|' . RESTMENU_HASH_PEPPER), 0, 24);
        if (hash_equals($generated, $hash)) {
            return $r['Code'];
        }
    }
    return null;
}

$code = table_code_from_hash($pdo, $restaurantId, $hash);
if (!$code) {
    die('<div style="margin-top:3rem;text-align:center;font-family:sans-serif;color:red;">
        <h2>⚠️ Geçersiz veya tanımsız masa kodu.</h2>
        <a href="tables.php" style="display:inline-block;margin-top:1rem;text-decoration:none;color:#007bff;">Geri dön</a>
    </div>');
}

// Masa bilgisi
$stmt = $pdo->prepare("SELECT * FROM RestaurantTables WHERE Code = ? AND RestaurantID = ?");
$stmt->execute([$code, $restaurantId]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    die('Masa bulunamadı.');
}

// Eğer masa pasifse QR sayfası açılmaz
if (!$table['IsActive']) {
    die('<div style="margin-top:3rem;text-align:center;font-family:sans-serif;color:red;">
        <h2>⚠️ Bu masa şu anda pasif durumda.</h2>
        <p>Lütfen yöneticinizle iletişime geçin.</p>
        <a href="tables.php" style="display:inline-block;margin-top:1rem;text-decoration:none;color:#007bff;">Geri dön</a>
    </div>');
}

// Bağlantı oluştur
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = str_replace('/restaurants','', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

// Menü linki (müşteri QR'dan buraya yönlenecek)
$tableLink = $scheme . '://' . $host . $base . '/restaurant_info.php?hash=' . urlencode($hash);

// Dashboard’takiyle aynı QR üretim mantığı
$qrImg = $scheme . '://' . $host . $base . '/generate_qr.php?hash=' . urlencode($hash);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($table['Name']) ?> | QR Kod</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .qr-card {
      max-width: 480px;
      margin: 3rem auto;
      text-align: center;
      background: #fff;
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .qr-card img {
      width: 280px;
      height: 280px;
    }
    .qr-card h1 {
      font-size: 1.25rem;
      font-weight: 600;
    }
    .qr-card h2 {
      font-size: 1rem;
      color: #666;
    }
    @media print {
      body * { visibility: hidden; }
      .qr-card, .qr-card * { visibility: visible; }
      .qr-card { box-shadow: none; border: none; }
      .btn, a { display: none !important; }
    }
  </style>
</head>
<body>

<div class="qr-card">
  <h1><?= htmlspecialchars($restaurantName) ?></h1>
  <h2 class="mb-4"><?= htmlspecialchars($table['Name']) ?></h2>

  <img src="<?= htmlspecialchars($qrImg) ?>" alt="QR Kod">
  <div class="mt-3 small text-muted"><?= htmlspecialchars($tableLink) ?></div>

  <div class="mt-4">
    <button class="btn btn-primary" onclick="window.print()">Yazdır</button>
    <a href="tables.php" class="btn btn-outline-secondary">Geri Dön</a>
  </div>
</div>

</body>
</html>
