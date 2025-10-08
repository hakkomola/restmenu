<?php
require_once __DIR__ . '/db.php';

$hash  = $_GET['hash']  ?? null;
$catId = $_GET['cat']   ?? null;
$theme = $_GET['theme'] ?? 'modern'; // modern, gallery, darklux
$color = $_GET['color'] ?? 'orange'; // orange, green, red, blue, blackgold
$view  = $_GET['view']  ?? 'photo';  // photo, text
$lang  = $_GET['lang']  ?? null;

if (!$hash) die('Geçersiz link!');

$stmt = $pdo->prepare("SELECT RestaurantID, Name, BackgroundImage, DefaultLanguage FROM Restaurants WHERE MD5(RestaurantID) = ?");
$stmt->execute([$hash]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Geçersiz link!');

$restaurantId   = (int)$restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];
if (!$lang) $lang = $restaurant['DefaultLanguage'] ?: 'tr';

$langStmt = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, l.LangName ASC
");
$langStmt->execute([$restaurantId]);
$supportedLangs = $langStmt->fetchAll(PDO::FETCH_ASSOC);

function flag_code_from_lang($lc) {
    $lc = strtolower($lc);
    $map = [
        'tr'=>'tr','en'=>'gb','de'=>'de','fr'=>'fr','es'=>'es','it'=>'it','nl'=>'nl','ru'=>'ru',
        'ar'=>'sa','fa'=>'ir','zh'=>'cn','ja'=>'jp','ko'=>'kr','el'=>'gr','he'=>'il','pt'=>'pt','az'=>'az'
    ];
    return $map[$lc] ?? $lc;
}

$uiText = [
    'tr' => ['home' => 'Ana Menü'],
    'en' => ['home' => 'Main Menu'],
];
$tx = $uiText[strtolower($lang)] ?? $uiText['tr'];

$itemFkCol = 'MenuItemID';
try {
    $colCheck = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MenuItemTranslations'
          AND COLUMN_NAME IN ('MenuItemID','ItemID') LIMIT 1
    ");
    $colCheck->execute();
    $found = $colCheck->fetchColumn();
    if ($found) $itemFkCol = $found;
} catch (Exception $e) {}

if ($catId) {
    $stmt = $pdo->prepare("
        SELECT c.*, COALESCE(ct.Name, c.CategoryName) AS CategoryNameDisp
        FROM MenuCategories c
        LEFT JOIN MenuCategoryTranslations ct ON ct.CategoryID = c.CategoryID AND ct.LangCode = ?
        WHERE c.CategoryID = ? AND c.RestaurantID = ? LIMIT 1
    ");
    $stmt->execute([$lang, $catId, $restaurantId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$category) die('Kategori bulunamadı!');

    $stmt = $pdo->prepare("
        SELECT sc.*, COALESCE(sct.Name, sc.SubCategoryName) AS SubCategoryNameDisp
        FROM SubCategories sc
        LEFT JOIN SubCategoryTranslations sct ON sct.SubCategoryID = sc.SubCategoryID AND sct.LangCode = ?
        WHERE sc.CategoryID = ?
        ORDER BY sc.SortOrder, SubCategoryNameDisp
    ");
    $stmt->execute([$lang, $catId]);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsBySub = [];
    $sqlItems = "
        SELECT mi.*, COALESCE(mt.Name, mi.MenuName) AS MenuNameDisp, COALESCE(mt.Description, mi.Description) AS DescriptionDisp
        FROM MenuItems mi
        LEFT JOIN MenuItemTranslations mt ON mt.$itemFkCol = mi.MenuItemID AND mt.LangCode = ?
        WHERE mi.SubCategoryID = ?
        ORDER BY MenuNameDisp
    ";
    $stmtItems = $pdo->prepare($sqlItems);
    foreach ($subcategories as $sub) {
        $stmtItems->execute([$lang, $sub['SubCategoryID']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $stmtImg = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID = ?");
            $stmtImg->execute([$item['MenuItemID']]);
            $item['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
        }
        $itemsBySub[$sub['SubCategoryID']] = $items;
    }
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, COALESCE(ct.Name, c.CategoryName) AS CategoryNameDisp
        FROM MenuCategories c
        LEFT JOIN MenuCategoryTranslations ct ON ct.CategoryID = c.CategoryID AND ct.LangCode = ?
        WHERE c.RestaurantID = ?
        ORDER BY c.SortOrder, CategoryNameDisp
    ");
    $stmt->execute([$lang, $restaurantId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-theme="<?= htmlspecialchars($theme) ?>" data-color="<?= htmlspecialchars($color) ?>" data-view="<?= htmlspecialchars($view) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($restaurantName) ?> - Menü</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ---- Renk Paletleri ---- */
:root[data-color="orange"] { --primary:#FF6600; --accent:#FFA033; --bg:#F8F9FA; --text:#222; }
:root[data-color="green"]  { --primary:#27AE60; --accent:#2ECC71; --bg:#F8F9FA; --text:#222; }
:root[data-color="red"]    { --primary:#E74C3C; --accent:#C0392B; --bg:#F8F9FA; --text:#222; }
:root[data-color="blue"]   { --primary:#3498DB; --accent:#2E86C1; --bg:#F8F9FA; --text:#222; }
:root[data-color="blackgold"] { --primary:#C4A661; --accent:#E4D4A1; --bg:#111; --text:#EEE; }

/* ---- Genel ---- */
body {
  font-family:'Poppins',sans-serif;
  background:var(--bg);
  color:var(--text);
  margin:0; padding:0;
}
.menu-header {
  text-align:center;
  font-weight:600;
  font-size:1.6rem;
  padding:10px 0 20px;
  color:var(--primary);
}
.card {
  border:none;
  transition:all .3s ease;
  border-radius:12px;
  overflow:hidden;
}
.card:hover { transform:translateY(-3px); }
.card-title { font-size:1rem; font-weight:600; color:var(--text); }
.price { color:var(--primary); font-weight:700; }
.category-img, .menu-img { width:100%; height:200px; object-fit:cover; border-radius:8px; }

/* ---- MODERN TEMA ---- */
[data-theme="modern"] .card { background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
[data-theme="modern"] .subcategory-menu a.active { background:var(--primary); color:#fff; }

/* ---- GALLERY TEMA ---- */
[data-theme="gallery"] .category-banner { position:relative; height:160px; border-radius:10px; overflow:hidden; margin-bottom:10px; }
[data-theme="gallery"] .category-banner img { width:100%; height:100%; object-fit:cover; filter:brightness(0.7); }
[data-theme="gallery"] .category-banner h2 { position:absolute; bottom:10px; left:20px; color:#fff; font-size:1.4rem; font-weight:700; }

/* ---- DARKLUX TEMA ---- */
[data-theme="darklux"] body { background:#111; color:#EEE; }
[data-theme="darklux"] .card { background:#1C1C1C; border:1px solid rgba(255,255,255,0.08); }
[data-theme="darklux"] .price { color:var(--primary); }
[data-theme="darklux"] .subcategory-menu { background:#000; }

/* ---- Görünüm Ayarları ---- */
[data-view="photo"] .card-text { display:none; }
[data-view="text"] .menu-img { opacity:0.6; height:150px; }

/* ---- Subcategory Menu ---- */
.subcategory-menu {
  position:sticky; top:0; z-index:1000;
  background:rgba(255,255,255,0.95);
  border-bottom:1px solid #ddd;
  padding:6px; white-space:nowrap; overflow-x:auto;
}
.subcategory-menu a {
  display:inline-block; margin:0 5px; padding:6px 12px;
  border-radius:20px; border:1px solid #ccc;
  color:var(--text); text-decoration:none; font-weight:500;
}
.subcategory-menu a.active { background:var(--primary); color:#fff; border-color:var(--primary); }

@media (max-width:576px){
  .category-grid { display:grid!important; grid-template-columns:repeat(2,1fr); gap:10px; }
}
</style>
</head>
<body>

<div class="container py-3">
  <div class="menu-header"><?= htmlspecialchars($restaurantName) ?></div>

<?php if (!$catId): ?>
  <?php if ($theme === 'gallery'): ?>
    <?php foreach ($categories as $cat): ?>
      <div class="category-banner mb-4">
        <?php if(!empty($cat['ImageURL'])): ?>
          <img src="<?= htmlspecialchars(ltrim($cat['ImageURL'],'/')) ?>" alt="">
        <?php endif; ?>
        <h2><?= htmlspecialchars($cat['CategoryNameDisp']) ?></h2>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="row g-3 category-grid">
      <?php foreach ($categories as $cat): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a href="?hash=<?= htmlspecialchars($hash) ?>&cat=<?= $cat['CategoryID'] ?>&theme=<?= $theme ?>&color=<?= $color ?>&lang=<?= $lang ?>" class="text-decoration-none text-reset">
            <div class="card h-100 text-center p-2">
              <?php if(!empty($cat['ImageURL'])): ?>
                <img src="<?= htmlspecialchars(ltrim($cat['ImageURL'],'/')) ?>" class="category-img mb-2" alt="Kategori">
              <?php endif; ?>
              <div class="card-body py-2">
                <h6 class="card-title mb-0"><?= htmlspecialchars($cat['CategoryNameDisp']) ?></h6>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="page-header text-center mb-3">
    <h3><?= htmlspecialchars($category['CategoryNameDisp']) ?></h3>
  </div>

  <?php if (!empty($subcategories)): ?>
  <div class="subcategory-menu mb-3">
    <a href="?hash=<?= htmlspecialchars($hash) ?>&theme=<?= $theme ?>&color=<?= $color ?>&lang=<?= $lang ?>" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars($tx['home']) ?></a>
    <?php foreach ($subcategories as $sub): ?>
      <a href="#sub<?= (int)$sub['SubCategoryID'] ?>"><?= htmlspecialchars($sub['SubCategoryNameDisp']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($subcategories as $sub): ?>
    <section id="sub<?= (int)$sub['SubCategoryID'] ?>" class="mt-3 mb-4">
      <h5 class="mb-2"><?= htmlspecialchars($sub['SubCategoryNameDisp']) ?></h5>
      <div class="row g-3">
        <?php foreach ($itemsBySub[$sub['SubCategoryID']] as $item): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
              <?php if (!empty($item['images'])): ?>
                <img src="<?= htmlspecialchars(ltrim($item['images'][0]['ImageURL'],'/')) ?>" class="menu-img" alt="">
              <?php endif; ?>
              <div class="card-body">
                <h6 class="card-title"><?= htmlspecialchars($item['MenuNameDisp']) ?></h6>
                <p class="card-text small"><?= htmlspecialchars($item['DescriptionDisp']) ?></p>
                <p class="price"><?= number_format((float)$item['Price'],2) ?> ₺</p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
