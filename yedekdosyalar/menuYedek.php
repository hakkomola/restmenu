<?php
require_once __DIR__ . '/db.php';

$hash = $_GET['hash'] ?? null;
$catId = $_GET['cat'] ?? null;

if (!$hash) die('Geçersiz link!');

// Hash ile restoranı bul
$stmt = $pdo->prepare("SELECT RestaurantID,Name FROM Restaurants WHERE MD5(RestaurantID) = ?");
$stmt->execute([$hash]);
$restaurant = $stmt->fetch();
if (!$restaurant) die('Geçersiz link!');

$restaurantId = $restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];

if ($catId) {
    // Belirli kategori seçilmiş -> menü öğelerini getir
    $stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE CategoryID = ? AND RestaurantID = ?");
    $stmt->execute([$catId, $restaurantId]);
    $category = $stmt->fetch();
    if (!$category) die('Kategori bulunamadı!');

    $stmt2 = $pdo->prepare("SELECT * FROM MenuItems WHERE CategoryID = ? ORDER BY MenuName");
    $stmt2->execute([$catId]);
    $items = $stmt2->fetchAll();



foreach ($items as $index => $item) {
    $stmt3 = $pdo->prepare("SELECT * FROM MenuImages WHERE MenuItemID = ?");
    $stmt3->execute([$item['MenuItemID']]);
    $images = $stmt3->fetchAll();

    // URL işlemleri: baştaki '/' kaldır, 'uploads/' yoksa ekle
    $fixedImages = [];
    foreach ($images as $img) {
        $url = ltrim($img['ImageURL'], '/');
        if (strpos($url, 'uploads/') !== 0) $url = 'uploads/' . $url;
        $img['ImageURL'] = $url;
        $fixedImages[] = $img;
    }

    $items[$index]['images'] = $fixedImages;
}


} else {
    // Ana sayfa -> kategorileri getir
    $stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName");
    $stmt->execute([$restaurantId]);
    $categories = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.category-img, .menu-img { height: 200px; object-fit: cover; border-radius: 8px; }
.card:hover { box-shadow: 0 8px 16px rgba(0,0,0,0.2); transition: 0.3s; cursor: pointer; }
.carousel-control-prev-icon, .carousel-control-next-icon {
    background-color: rgba(0,0,0,0.5);
    border-radius: 50%;
    padding: 12px;
}
.category-card { text-decoration: none; color: inherit; }
</style>
</head>
<body>
<div class="container mt-4">

<?php if (!$catId): ?>
    <h1 class="mb-4 text-center"><?=$restaurantName?></h1>
    <div class="row g-4">
        <?php foreach ($categories as $cat): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <a href="?hash=<?= htmlspecialchars($hash) ?>&cat=<?= $cat['CategoryID'] ?>" class="category-card">
                    <div class="card h-100 text-center">
                        <?php if($cat['ImageURL']): ?>
                            <img src="<?= htmlspecialchars(ltrim($cat['ImageURL'], '/')) ?>" class="card-img-top category-img" alt="Kategori Resmi">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($cat['CategoryName']) ?></h5>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <h1 class="mb-4 text-center"><?= htmlspecialchars($category['CategoryName']) ?> Menüsü</h1>
    <div class="mb-3 text-center">
        <a href="?hash=<?= htmlspecialchars($hash) ?>" class="btn btn-secondary">← Kategorilere Dön</a>
    </div>
    <div class="row g-4">
        <?php foreach ($items as $item): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100">
                    <?php if (!empty($item['images'])): ?>
                    <div class="position-relative">
                        <div id="carousel<?= $item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($item['images'] as $index => $img): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                        <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100 menu-img" alt="Menu Resmi">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if(count($item['images']) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?= $item['MenuItemID'] ?>" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carousel<?= $item['MenuItemID'] ?>" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($item['MenuName']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($item['Description']) ?></p>
                        <p class="fw-bold text-primary"><?= number_format($item['Price'], 2) ?>₺</p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
