<?php
require_once __DIR__ . '/db.php';

$hash = $_GET['hash'] ?? null;
$itemId = $_GET['id'] ?? null;
$theme = $_GET['theme'] ?? 'light';

if (!$hash || !$itemId) die('Geçersiz link!');

// Restoran bilgisi
$stmt = $pdo->prepare("SELECT RestaurantID, Name FROM Restaurants WHERE MD5(RestaurantID) = ?");
$stmt->execute([$hash]);
$restaurant = $stmt->fetch();
if (!$restaurant) die('Geçersiz restoran bağlantısı!');

$restaurantId = $restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];

// Menü öğesi bilgisi
$stmt = $pdo->prepare("
    SELECT m.*, s.SubCategoryName, c.CategoryName
    FROM MenuItems m
    LEFT JOIN SubCategories s ON m.SubCategoryID = s.SubCategoryID
    LEFT JOIN MenuCategories c ON s.CategoryID = c.CategoryID
    WHERE m.MenuItemID = ? AND c.RestaurantID = ?
");
$stmt->execute([$itemId, $restaurantId]);
$item = $stmt->fetch();
if (!$item) die('Ürün bulunamadı!');

// Görseller
$stmt2 = $pdo->prepare("SELECT * FROM MenuImages WHERE MenuItemID = ?");
$stmt2->execute([$itemId]);
$images = $stmt2->fetchAll();

// Seçenekler
$stmt3 = $pdo->prepare("SELECT * FROM MenuItemOptions WHERE MenuItemID = ? ORDER BY SortOrder, OptionName");
$stmt3->execute([$itemId]);
$options = $stmt3->fetchAll();

// Görsel yollarını düzelt
$fixedImages = [];
foreach ($images as $img) {
    $url = ltrim($img['ImageURL'], '/');
    if (strpos($url, 'uploads/') !== 0) $url = 'uploads/' . $url;
    $img['ImageURL'] = $url;
    $fixedImages[] = $img;
}
$images = $fixedImages;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['MenuName']) ?> - <?= htmlspecialchars($restaurantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  font-family: "Poppins", sans-serif;
  <?= $theme === 'dark'
      ? 'background-color:#121212;color:#f1f1f1;'
      : 'background-color:#f8f9fa;color:#222;'
  ?>
}
.container { max-width: 700px; }
.page-header {
  text-align: center;
  margin-bottom: 20px;
  padding-top: 10px;
}
.page-header h1 {
  font-size: clamp(22px, 5vw, 34px);
  font-weight: 700;
  margin-bottom: 6px;
}
.page-header h3 {
  font-size: clamp(18px, 4vw, 26px);
  opacity: 0.85;
  font-weight: 500;
}
.card {
  border: none;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px <?= $theme === 'dark' ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.1)' ?>;
  background: <?= $theme === 'dark' ? '#1e1e1e' : '#fff' ?>;
  transition: all 0.25s ease-in-out;
}
.card:hover {
  <?= $theme === 'dark'
      ? 'background-color:#252525;box-shadow:0 6px 14px rgba(255,255,255,0.1);'
      : 'box-shadow:0 6px 14px rgba(0,0,0,0.15);'
  ?>
}
.carousel-item img {
  height: 300px;
  object-fit: cover;
  width: 100%;
  <?= $theme === 'dark' ? 'filter:brightness(0.85);' : '' ?>
}
.carousel-control-prev-icon,
.carousel-control-next-icon {
  background-color: <?= $theme === 'dark' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.4)' ?>;
  border-radius: 50%;
  padding: 10px;
}
.carousel-control-prev-icon:hover,
.carousel-control-next-icon:hover {
  background-color: <?= $theme === 'dark' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)' ?>;
}
.card-body { padding: 20px; }
.card-body h3 {
  color: <?= $theme === 'dark' ? '#ffffff' : '#222' ?>;
  font-weight: 600;
}
.card-body p {
  color: <?= $theme === 'dark' ? '#cccccc' : '#555' ?>;
  font-size: 0.95rem;
}
.price {
  font-weight: 700;
  font-size: 1.1rem;
  color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?> !important;
}
.back-btn {
  text-decoration: none;
  font-weight: 500;
  color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
}
.back-btn:hover { text-decoration: underline; }

/* 🔸 Seçenekler Alanı */
.option-list {
  margin-top: 20px;
  border-top: 1px solid <?= $theme === 'dark' ? '#333' : '#ddd' ?>;
  padding-top: 15px;
}
.option-list h4 {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 10px;
  color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
}
.option-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px dashed <?= $theme === 'dark' ? '#444' : '#ccc' ?>;
}
.option-item:last-child { border-bottom: none; }
.option-item span {
  font-size: 0.95rem;
  <?= $theme === 'dark'
      ? 'color:#ffcc80;'
      : 'color:#333;'
  ?>
}
</style>
</head>
<body>

<div class="container my-4">

  <!-- Üst Bilgi -->
  <div class="page-header">
    <h1><?= htmlspecialchars($restaurantName) ?></h1>
    <h3><?= htmlspecialchars($item['MenuName']) ?></h3>
  </div>

  <a href="#" onclick="goBack()" class="back-btn mb-3 d-inline-block">&larr; Geri Dön</a>

  <div class="card">
    <?php if (!empty($images)): ?>
      <div id="carouselItem<?= $item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($images as $i => $img): ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
              <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100" alt="Menü Görseli">
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <button class="carousel-control-prev" type="button" data-bs-target="#carouselItem<?= $item['MenuItemID'] ?>" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#carouselItem<?= $item['MenuItemID'] ?>" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card-body">
      <?php if (!empty($item['Description'])): ?>
        <p class="mt-2"><?= nl2br(htmlspecialchars($item['Description'])) ?></p>
      <?php endif; ?>

      <p class="price mt-3"><?= number_format($item['Price'], 2) ?> ₺</p>

      <!-- 🔸 Seçenekler -->
      <?php if (!empty($options)): ?>
        <div class="option-list">
          <h4>Seçenekler</h4>
          <?php foreach ($options as $opt): ?>
            <div class="option-item">
              <span><?= htmlspecialchars($opt['OptionName']) ?></span>
              <span><?= number_format($opt['Price'], 2) ?> ₺</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function goBack() {
  if (document.referrer && document.referrer !== window.location.href) {
    history.back();
  } else {
    // Tarayıcı geçmişi yoksa ana menüye yönlendir
    const params = new URLSearchParams(window.location.search);
    const hash = params.get("hash");
    const theme = params.get("theme") || "light";
    if (hash) {
      window.location.href = "menu.php?hash=" + hash + "&theme=" + theme;
    } else {
      window.location.href = "index.php";
    }
  }
}
</script>
</body>
</html>
