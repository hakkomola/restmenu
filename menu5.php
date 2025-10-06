<?php
require_once __DIR__ . '/db.php';

$hash = $_GET['hash'] ?? null;
$catId = $_GET['cat'] ?? null;
$theme = 'light'; // Bu dosya açık tema

if (!$hash) die('Geçersiz link!');

$stmt = $pdo->prepare("SELECT RestaurantID,Name,BackgroundImage FROM Restaurants WHERE MD5(RestaurantID) = ?");
$stmt->execute([$hash]);
$restaurant = $stmt->fetch();
if (!$restaurant) die('Geçersiz link!');

$restaurantId = $restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];
$backgroundImage = $restaurant['BackgroundImage'];

if ($catId) {
    $stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE CategoryID = ? AND RestaurantID = ? ORDER BY SortOrder,CategoryName");
    $stmt->execute([$catId, $restaurantId]);
    $category = $stmt->fetch();
    if (!$category) die('Kategori bulunamadı!');

    $stmt = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID = ? ORDER BY SortOrder,SubCategoryName");
    $stmt->execute([$catId]);
    $allSubcategories = $stmt->fetchAll();

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

            $subcategories[] = $sub;
            $itemsBySub[$sub['SubCategoryID']] = $items;
        }
    }
} else {
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
<title>Menü - <?= htmlspecialchars($restaurantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background-color: #f8f9fa;
  font-family: "Poppins", sans-serif;
  color: #333;
  scroll-behavior: smooth;
}
<?php if ($backgroundImage): ?>
body {
  background: url('<?= htmlspecialchars(ltrim($backgroundImage, '/')) ?>') no-repeat center center fixed;
  background-size: cover;
}
<?php endif; ?>

h1, h3, h5, h6 { color: #222; }
p, span, small { color: #555; }

.card {
  background-color: #fff;
  border: none;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  transition: all 0.25s ease-in-out;
}
.card:hover {
  transform: translateY(-3px);
  background-color: #ffffff;
  box-shadow: 0 6px 14px rgba(0,0,0,0.12);
}
.category-card img, .menu-img {
  height: 220px;
  object-fit: cover;
  width: 100%;
  border-bottom: 1px solid #eee;
}
.carousel-control-prev-icon, .carousel-control-next-icon {
  background-color: rgba(0,0,0,0.4);
  border-radius: 50%;
  padding: 10px;
}
.carousel-control-prev-icon:hover, .carousel-control-next-icon:hover {
  background-color: rgba(0,0,0,0.6);
}
.card-body {
  padding: 15px;
}
.card-title {
  color: #222;
  font-weight: 600;
  font-size: 1.1rem;
}
.card-text {
  color: #555;
  font-size: 0.95rem;
}
.price {
  font-weight: 700;
  color: #007bff;
  font-size: 1rem;
}
.subcategory-menu {
  position: sticky;
  top: 0;
  z-index: 999;
  background-color: rgba(255,255,255,0.95);
  padding: 8px 0;
  border-bottom: 1px solid #ddd;
  overflow-x: auto;
  white-space: nowrap;
  scrollbar-width: none;
}
.subcategory-menu::-webkit-scrollbar { display: none; }
.subcategory-menu a {
  display: inline-block;
  margin: 0 4px;
  border-radius: 50px;
  padding: 6px 14px;
  font-size: .9rem;
  color: #333;
  border: 1px solid #ccc;
  background: #f8f9fa;
  white-space: nowrap;
}
.subcategory-menu a.active {
  background-color: #007bff;
  border-color: #007bff;
  color: #fff;
}
section { scroll-margin-top: 80px; }

@media (max-width: 576px) {
  .category-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
  .category-grid img { height: 130px; }
}
</style>
</head>
<body data-bs-spy="scroll" data-bs-target="#subcategoryNav" data-bs-offset="100" tabindex="0">

<div class="container py-4">
<?php if (!$catId): ?>
  <h1 class="mb-4 text-center"><?= htmlspecialchars($restaurantName) ?></h1>
  <div class="row g-4 category-grid">
    <?php foreach ($categories as $cat): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <a href="?hash=<?= htmlspecialchars($hash) ?>&cat=<?= $cat['CategoryID'] ?>" class="text-decoration-none text-dark">
          <div class="card h-100 text-center">
            <?php if($cat['ImageURL']): ?>
              <img src="<?= htmlspecialchars(ltrim($cat['ImageURL'], '/')) ?>" class="category-img" alt="Kategori">
            <?php endif; ?>
            <div class="card-body">
              <h6 class="card-title mb-0"><?= htmlspecialchars($cat['CategoryName']) ?></h6>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <h1 class="text-center mb-3"><?= htmlspecialchars($category['CategoryName']) ?> Menüsü</h1>

  <?php if ($subcategories): ?>
  <div id="subcategoryNav" class="subcategory-menu">
    <a href="?hash=<?= htmlspecialchars($hash) ?>" class="btn btn-outline-secondary">Ana Menü</a>
    <?php foreach ($subcategories as $sub): ?>
      <a href="#sub<?= $sub['SubCategoryID'] ?>" class="btn"><?= htmlspecialchars($sub['SubCategoryName']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($subcategories as $sub): ?>
    <section id="sub<?= $sub['SubCategoryID'] ?>" class="mt-4">
      <h3 class="mb-3"><?= htmlspecialchars($sub['SubCategoryName']) ?></h3>
      <div class="row g-4">
<?php foreach ($itemsBySub[$sub['SubCategoryID']] as $item): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="menu_item.php?id=<?= $item['MenuItemID'] ?>&hash=<?= htmlspecialchars($hash) ?>&theme=light" class="text-decoration-none text-dark">
      <div class="card h-100">
        <?php if (!empty($item['images'])): ?>
          <div id="carousel<?= $item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
              <?php foreach ($item['images'] as $i => $img): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                  <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100 menu-img" alt="Menü Görseli">
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
          <p class="price"><?= number_format($item['Price'], 2) ?> ₺</p>
        </div>
      </div>
    </a>
  </div>
<?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const scrollSpy = new bootstrap.ScrollSpy(document.body, { target: '#subcategoryNav', offset: 100 });
document.addEventListener('activate.bs.scrollspy', function () {
  const active = document.querySelector('#subcategoryNav .active');
  if (active) active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
});
</script>
</body>
</html>
