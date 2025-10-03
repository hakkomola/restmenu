<?php
require_once __DIR__ . '/db.php';

$id = $_GET['id'] ?? null;
$hash = $_GET['hash'] ?? null;

if (!$id || !$hash) die('Geçersiz link!');

// Restoranı bul
$stmt = $pdo->prepare("SELECT RestaurantID,Name,BackgroundImage FROM Restaurants WHERE MD5(RestaurantID) = ?");
$stmt->execute([$hash]);
$restaurant = $stmt->fetch();
if (!$restaurant) die('Geçersiz link!');

$restaurantId = $restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];
$backgroundImage = $restaurant['BackgroundImage'];

// Menü öğesini bul
$stmt = $pdo->prepare("SELECT * FROM MenuItems WHERE MenuItemID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);
$item = $stmt->fetch();
if (!$item) die('Menü öğesi bulunamadı!');

// Menü öğesi resimleri
$stmt = $pdo->prepare("SELECT * FROM MenuImages WHERE MenuItemID = ?");
$stmt->execute([$id]);
$images = $stmt->fetchAll();

$fixedImages = [];
foreach ($images as $img) {
    $url = ltrim($img['ImageURL'], '/');
    if (strpos($url, 'uploads/') !== 0) $url = 'uploads/' . $url;
    $img['ImageURL'] = $url;
    $fixedImages[] = $img;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['MenuName']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { 
    background-color: #f8f9fa; 
    <?php if ($backgroundImage): ?>
    background: url('<?= htmlspecialchars(ltrim($backgroundImage, '/')) ?>') no-repeat center center fixed;
    background-size: cover;
    <?php endif; ?>
}
.menu-img { max-height: 400px; object-fit: cover; border-radius: 8px; }
.card { margin-bottom: 20px; }
</style>
</head>
<body>
<div class="container mt-4">
    <h1 class="mb-4"><?= htmlspecialchars($item['MenuName']) ?></h1>

    <?php if ($fixedImages): ?>
    <div id="carouselItem" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php foreach ($fixedImages as $i => $img): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100 menu-img" alt="Menü Resmi">
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($fixedImages) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselItem" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselItem" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p><?= htmlspecialchars($item['Description']) ?></p>
    <p class="fw-bold"><?= number_format($item['Price'], 2) ?> ₺</p>

    <a href="menu.php?hash=<?= htmlspecialchars($hash) ?>" class="btn btn-primary">Geri</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
