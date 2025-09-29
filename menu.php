<?php
require_once __DIR__ . '/db.php';

$hash = $_GET['hash'] ?? null;
$catId = $_GET['cat'] ?? null;

if (!$hash) die('Geçersiz link!');

// Restoranı bul
$stmt = $pdo->prepare("SELECT RestaurantID,Name FROM Restaurants WHERE MD5(RestaurantID) = ?");
$stmt->execute([$hash]);
$restaurant = $stmt->fetch();
if (!$restaurant) die('Geçersiz link!');

$restaurantId = $restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];

if ($catId) {
    // Seçili kategori
    $stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE CategoryID = ? AND RestaurantID = ? ORDER BY SortOrder,CategoryName");
    $stmt->execute([$catId, $restaurantId]);
    $category = $stmt->fetch();
    if (!$category) die('Kategori bulunamadı!');

    // Alt kategoriler
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE CategoryID = ? ORDER BY SortOrder,SubCategoryName");
    $stmt->execute([$catId]);
    $allSubcategories = $stmt->fetchAll();

    // Menü öğeleri alt kategorilere göre gruplanıyor ve subcategory filtresi uygulanıyor
    $subcategories = [];
    $itemsBySub = [];
    foreach ($allSubcategories as $sub) {
        $stmt2 = $pdo->prepare("SELECT * FROM MenuItems WHERE SubCategoryID = ? ORDER BY MenuName");
        $stmt2->execute([$sub['SubCategoryID']]);
        $items = $stmt2->fetchAll();

        if (count($items) > 0) {
            foreach ($items as $index => $item) {
                $stmt3 = $pdo->prepare("SELECT * FROM MenuImages WHERE MenuItemID = ?");
                $stmt3->execute([$item['MenuItemID']]);
                $images = $stmt3->fetchAll();

                $fixedImages = [];
                foreach ($images as $img) {
                    $url = ltrim($img['ImageURL'], '/');
                    if (strpos($url, 'uploads/') !== 0) $url = 'uploads/' . $url;
                    $img['ImageURL'] = $url;
                    $fixedImages[] = $img;
                }
                $items[$index]['images'] = $fixedImages;
            }

            $subcategories[] = $sub; // sadece içinde menu item olan subcategory ekleniyor
            $itemsBySub[$sub['SubCategoryID']] = $items;
        }
    }

} else {
    // Ana kategori listesi
    $stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY SortOrder,CategoryName");
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
.subcategory-menu {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #fff;
    padding: 10px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.subcategory-menu .btn.active {
    background-color: #0d6efd;
    color: #fff;
}
</style>
</head>
<body data-bs-spy="scroll" data-bs-target="#subcategoryNav" data-bs-offset="80" tabindex="0">

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

    <?php if ($subcategories): ?>
    <div id="subcategoryNav" class="subcategory-menu text-center">
        <a href="?hash=<?= htmlspecialchars($hash) ?>" class="btn btn-success">Ana Menü</a> 
        <br><br>
        <?php foreach ($subcategories as $index => $sub): ?>
            <a href="#sub<?= $sub['SubCategoryID'] ?>" 
               class="btn btn-outline-primary <?= $index === 0 ? 'active' : '' ?>">
               <?= htmlspecialchars($sub['SubCategoryName']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div data-bs-spy="scroll" data-bs-target="#subcategoryNav" data-bs-offset="80" tabindex="0">
        <?php foreach ($subcategories as $sub): ?>
            <h3 id="sub<?= $sub['SubCategoryID'] ?>" class="mt-4"><?= htmlspecialchars($sub['SubCategoryName']) ?></h3>
            <div class="row g-4">
                <?php foreach ($itemsBySub[$sub['SubCategoryID']] as $item): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100">
                            <?php if (!empty($item['images'])): ?>
                                <div id="carousel<?= $item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner">
                                        <?php foreach ($item['images'] as $i => $img): ?>
                                            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                                <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100 menu-img" alt="Menü Resmi">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($item['images']) > 1): ?>
                                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?= $item['MenuItemID'] ?>" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#carousel<?= $item['MenuItemID'] ?>" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($item['MenuName']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($item['Description']) ?></p>
                                <p class="fw-bold"><?= number_format($item['Price'], 2) ?> ₺</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const firstBtn = document.querySelector("#subcategoryNav .btn");
    if (firstBtn) {
        firstBtn.classList.add("active");
    }
});
</script>
</body>
</html>
