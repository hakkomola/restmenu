<?php
require_once __DIR__ . '/db.php';

$hash  = $_GET['hash']  ?? null;
$catId = $_GET['cat']   ?? null;
$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang']  ?? null;

if (!$hash) die('Geçersiz link!');

if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING'); // restaurant_info.php ile aynı olsun
}
function table_public_hash_local(int $rid, string $code): string {
    return substr(hash('sha256', $rid . '|' . $code . '|' . RESTMENU_HASH_PEPPER), 0, 24);
}

$tableName = null;
$foundRestaurantId = null;

// Masaları tek geçişte tara ve hash ile eşle
$__t = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables");
while ($__r = $__t->fetch(PDO::FETCH_ASSOC)) {
    if (hash_equals(table_public_hash_local((int)$__r['RestaurantID'], $__r['Code']), $hash)) {
        if (empty($__r['IsActive'])) { die('Bu masa şu anda pasif durumda.'); }
        $tableName = $__r['Name'];
        $foundRestaurantId = (int)$__r['RestaurantID'];
        break;
    }
}
if ($foundRestaurantId === null) { die('Geçersiz bağlantı (masa bulunamadı)!'); }

/* ====== YENİ: hash'ten MASA + RESTORAN bul (restaurant_info.php ile uyumlu) ====== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    // restaurant_info.php / tables.php ile aynı sabit olmalı
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

function resolve_table_by_hash(PDO $pdo, string $hash) {
    // Tüm masaları hafifçe dolaşarak birden fazla olası hash şemasını dener.
    // Böylece restaurant_info.php veya QR üretimindeki şeman neyse ona uyum sağlar.
    $stmt = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $candidates = [];

        // 1) sha256 + pepper, 24 karakter (bizim yeni önerdiğimiz şema)
        $candidates[] = substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 24);

        // 2) md5(RestaurantID-Code) (önceki mesajda anlattığımız alternatif)
        $candidates[] = md5($r['RestaurantID'].'-'.$r['Code']);

        // 3) md5(RestaurantIDCode) (bazı projelerde böyle kullanılmış olabilir)
        $candidates[] = md5($r['RestaurantID'].$r['Code']);

        // 4) md5(Code)
        $candidates[] = md5($r['Code']);

        // 5) ham Code (bazı eski kurulumlarda direkt code kullanılmış olabilir)
        $candidates[] = $r['Code'];

        foreach ($candidates as $cand) {
            if (hash_equals($cand, $hash)) {
                return $r; // doğru masa bulundu
            }
        }
    }
    return null;
}

$tableRow = resolve_table_by_hash($pdo, $hash);
if (!$tableRow) {
    die('Geçersiz link!');
}
if (!$tableRow['IsActive']) {
    die('Bu masa şu anda pasif durumda.');
}

// Seçilen masanın restoranını getir
$stmt = $pdo->prepare("SELECT RestaurantID, Name, BackgroundImage, DefaultLanguage FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$tableRow['RestaurantID']]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Geçersiz link!');

$restaurantId     = (int)$restaurant['RestaurantID'];
$restaurantName   = $restaurant['Name'];
$backgroundImage  = $restaurant['BackgroundImage'];
if (!$lang) $lang = $restaurant['DefaultLanguage'] ?: 'tr';
/* ====== /YENİ KISIM ====== */

// Desteklenen diller (RestaurantLanguages + Languages)
$langStmt = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, l.LangName ASC
");
$langStmt->execute([$restaurantId]);
$supportedLangs = $langStmt->fetchAll(PDO::FETCH_ASSOC);

// Basit bayrak kodu eşlemesi (flagcdn için)
function flag_code_from_lang($lc) {
    $lc = strtolower($lc);
    $map = [
        'tr'=>'tr','en'=>'gb','de'=>'de','fr'=>'fr','es'=>'es','it'=>'it','nl'=>'nl','ru'=>'ru',
        'ar'=>'sa','fa'=>'ir','zh'=>'cn','ja'=>'jp','ko'=>'kr','el'=>'gr','he'=>'il','pt'=>'pt','az'=>'az'
    ];
    return $map[$lc] ?? $lc;
}

// UI metinleri (yalnızca sayfa içi küçük metinler)
$uiText = [
    'tr' => ['home' => 'Ana Menü'],
    'en' => ['home' => 'Main Menu'],
];
$tx = $uiText[strtolower($lang)] ?? $uiText['tr'];

// MenuItemTranslations FK kolon adını tespit et (MenuItemID / ItemID)
$itemFkCol = 'MenuItemID';
try {
    $colCheck = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'MenuItemTranslations'
          AND COLUMN_NAME IN ('MenuItemID','ItemID')
        LIMIT 1
    ");
    $colCheck->execute();
    $found = $colCheck->fetchColumn();
    if ($found) $itemFkCol = $found;
} catch (Exception $e) { /* varsayılan kalsın */ }

// ----- VERİLERİ ÇEK -----
if ($catId) {
    // Kategori (çeviri ile)
    $stmt = $pdo->prepare("
        SELECT c.*, COALESCE(ct.Name, c.CategoryName) AS CategoryNameDisp
        FROM MenuCategories c
        LEFT JOIN MenuCategoryTranslations ct
               ON ct.CategoryID = c.CategoryID AND ct.LangCode = ?
        WHERE c.CategoryID = ? AND c.RestaurantID = ?
        LIMIT 1
    ");
    $stmt->execute([$lang, $catId, $restaurantId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$category) die('Kategori bulunamadı!');

    // Alt kategoriler (çeviri ile)
    $stmt = $pdo->prepare("
        SELECT sc.*, COALESCE(sct.Name, sc.SubCategoryName) AS SubCategoryNameDisp
        FROM SubCategories sc
        LEFT JOIN SubCategoryTranslations sct
               ON sct.SubCategoryID = sc.SubCategoryID AND sct.LangCode = ?
        WHERE sc.CategoryID = ?
        ORDER BY sc.SortOrder, SubCategoryNameDisp
    ");
    $stmt->execute([$lang, $catId]);
    $allSubcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Alt kategori + ürünler
    $subcategories = [];
    $itemsBySub = [];

    // Ürünleri dil bazlı çeviriyle çekecek statement
    $sqlItems = "
        SELECT mi.MenuItemID,mi.RestaurantID,mi.MenuName,mi.Description,mo.Price,mi.SortOrder,mi.SubCategoryID,
               COALESCE(mt.Name, mi.MenuName)         AS MenuNameDisp,
               COALESCE(mt.Description, mi.Description) AS DescriptionDisp
        FROM MenuItems mi
        LEFT JOIN MenuItemTranslations mt
               ON mt.$itemFkCol = mi.MenuItemID AND mt.LangCode = ?
        LEFT JOIN MenuItemOptions mo 
			   ON mi.MenuItemID=mo.MenuItemID and IsDefault  = 1  
        WHERE mi.SubCategoryID = ?
        ORDER BY MenuNameDisp
    ";



    $stmtItems = $pdo->prepare($sqlItems);

    foreach ($allSubcategories as $sub) {
        // Bu alt kategoriye ait ürünler
        $stmtItems->execute([$lang, $sub['SubCategoryID']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Görselleri düzelt
        if ($items) {
            foreach ($items as $index => $item) {
                $stmt3 = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID = ?");
                $stmt3->execute([$item['MenuItemID']]);
                $images = $stmt3->fetchAll(PDO::FETCH_ASSOC);

                $fixed = [];
                foreach ($images as $img) {
                    $url = ltrim($img['ImageURL'], '/');
                    if (strpos($url, 'uploads/') !== 0) $url = 'uploads/' . $url;
                    $fixed[] = ['ImageURL' => $url];
                }
                $items[$index]['images'] = $fixed;
            }
            $subcategories[] = $sub;
            $itemsBySub[$sub['SubCategoryID']] = $items;
        }
    }

} else {
    // Kategori listesi (çeviri ile)
    $stmt = $pdo->prepare("
        SELECT c.*, COALESCE(ct.Name, c.CategoryName) AS CategoryNameDisp
        FROM MenuCategories c
        LEFT JOIN MenuCategoryTranslations ct
               ON ct.CategoryID = c.CategoryID AND ct.LangCode = ?
        WHERE c.RestaurantID = ?
        ORDER BY c.SortOrder, CategoryNameDisp
    ");
    $stmt->execute([$lang, $restaurantId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü - <?= htmlspecialchars($restaurantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  font-family: "Poppins", sans-serif;
  scroll-behavior: smooth;
  <?= $theme === 'dark'
      ? 'background-color:#121212;color:#f1f1f1;'
      : 'background-color:#f8f9fa;color:#333;'
  ?>
}
.topbar {
  display:flex; justify-content:space-between; align-items:center;
  gap:12px; padding:10px 0;
}
.lang-switch { display:flex; gap:8px; flex-wrap:wrap; }
.lang-switch a {
  display:inline-flex; align-items:center; gap:6px;
  padding:2px 4px; border-radius:10px; font-weight:600; text-decoration:none;
  border:1px solid <?= $theme === 'dark' ? '#555' : '#ccc' ?>;
  <?= $theme === 'dark' ? 'background:#1e1e1e;color:#eee;' : 'background:#fff;color:#333;' ?>
}
.lang-switch a.active {
  <?= $theme === 'dark'
    ? 'background:#ff9800;color:#000;border-color:#ff9800;'
    : 'background:#007bff;color:#fff;border-color:#007bff;'
  ?>
}
.lang-switch img { width:22px; height:15px; border-radius:3px; object-fit:cover; }

.page-header {
  text-align: center;
  margin-bottom: 25px;
  padding-top: 10px;
}
.page-header h1 {
  font-size: clamp(22px, 5vw, 34px);
  font-weight: 700;
  margin-bottom: 5px;
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
  box-shadow: 0 2px 8px <?= $theme === 'dark' ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.08)' ?>;
  background: <?= $theme === 'dark' ? '#1e1e1e' : '#fff' ?>;
  transition: all 0.25s ease-in-out;
}
.card:hover {
  <?= $theme === 'dark'
      ? 'background-color:#252525;box-shadow:0 6px 14px rgba(255,255,255,0.1);'
      : 'box-shadow:0 6px 14px rgba(0,0,0,0.15);'
  ?>
}
.card-title { color: <?= $theme === 'dark' ? '#fff' : '#222' ?>; font-weight:600; }
.card-text { color: <?= $theme === 'dark' ? '#ccc' : '#555' ?>; }
.price {
  font-weight:700; font-size:1rem; color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
}
.category-img, .menu-img {
  height: 220px; object-fit: cover; width: 100%;
  <?= $theme === 'dark' ? 'filter:brightness(0.9);' : '' ?>
}
.subcategory-menu {
  position: sticky; top: 0; z-index: 999;
  background-color: <?= $theme === 'dark' ? 'rgba(18,18,18,0.95)' : 'rgba(255,255,255,0.95)' ?>;
  padding: 8px 0; border-bottom: 1px solid <?= $theme === 'dark' ? '#333' : '#ddd' ?>;
  overflow-x: auto; white-space: nowrap; scrollbar-width: none;
}
.subcategory-menu::-webkit-scrollbar { display: none; }
.subcategory-menu a {
  display: inline-block; margin: 0 4px; border-radius: 50px; padding: 6px 14px; font-size: .9rem;
  border: 1px solid <?= $theme === 'dark' ? '#555' : '#ccc' ?>;
  color: <?= $theme === 'dark' ? '#ddd' : '#333' ?>;
  background: <?= $theme === 'dark' ? '#1e1e1e' : '#f8f9fa' ?>;
}
.subcategory-menu a.active {
  background-color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
  border-color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
  color: <?= $theme === 'dark' ? '#000' : '#fff' ?>;
}
section { scroll-margin-top: 80px; }

@media (max-width: 576px) {
  .category-grid { display: grid !important; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .category-grid img { height:130px; }
}
</style>
</head>
<body data-bs-spy="scroll" data-bs-target="#subcategoryNav" data-bs-offset="100" tabindex="0">

<div class="container py-4">

  <!-- Üst bar: Sol: başlık (ana sayfada), Sağ: dil bayrakları -->
  <div class="topbar">
    <div></div>
    <div class="lang-switch">
      <?php if (!empty($supportedLangs)): ?>
        <?php foreach ($supportedLangs as $L): 
            $lc   = strtolower($L['LangCode']);
            $flag = flag_code_from_lang($lc);
            $isActive = ($lc === strtolower($lang));
            $qs = "hash=".urlencode($hash);
            if ($catId) $qs .= "&cat=".urlencode($catId);
            $qs .= "&theme=".urlencode($theme)."&lang=".urlencode($lc);
        ?>
          <a class="<?= $isActive ? 'active' : '' ?>" href="?<?= $qs ?>">
            <img src="https://flagcdn.com/w20/<?= htmlspecialchars($flag) ?>.png" alt="<?= htmlspecialchars($L['LangName']) ?>">
            <span><?= strtoupper($lc) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
     <!-- 
        <br>
        <?php if (!empty($tableName)): ?>
        <h5 class="text-decoration-none <?= $theme === 'dark' ? 'text-light' : 'text-dark' ?>">Masa: <?= htmlspecialchars($tableName) ?></h5>
        <?php endif; ?>
        -->
    </div>
  </div>

<?php if (!$catId): ?>
  <!-- Ana Sayfa (Kategori listesi) -->
  <h1 class="mb-4 text-center"><?= htmlspecialchars($restaurantName) ?></h1>

  <div class="row g-4 category-grid">
    <?php foreach ($categories as $cat): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <a href="?hash=<?= htmlspecialchars($hash) ?>&cat=<?= $cat['CategoryID'] ?>&theme=<?= htmlspecialchars($theme) ?>&lang=<?= htmlspecialchars($lang) ?>"
           class="text-decoration-none <?= $theme === 'dark' ? 'text-light' : 'text-dark' ?>">
          <div class="card h-100 text-center">
            <?php if(!empty($cat['ImageURL'])): ?>
              <img src="<?= htmlspecialchars(ltrim($cat['ImageURL'], '/')) ?>" class="category-img" alt="Kategori">
            <?php endif; ?>
            <div class="card-body">
              <h6 class="card-title mb-0"><?= htmlspecialchars($cat['CategoryNameDisp']) ?></h6>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <!-- Kategori Sayfası -->
  <div class="page-header">
    <h1><?= htmlspecialchars($restaurantName) ?></h1>
    <h3><?= htmlspecialchars($category['CategoryNameDisp']) ?></h3>
  </div>

  <?php if (!empty($subcategories)): ?>
  <div id="subcategoryNav" class="subcategory-menu">
    <a href="?hash=<?= htmlspecialchars($hash) ?>&theme=<?= htmlspecialchars($theme) ?>&lang=<?= htmlspecialchars($lang) ?>"
       class="btn <?= $theme === 'dark' ? 'btn-outline-light' : 'btn-outline-secondary' ?>">
       <?= htmlspecialchars($tx['home']) ?>
    </a>
    <?php foreach ($subcategories as $sub): ?>
      <a href="#sub<?= (int)$sub['SubCategoryID'] ?>" class="btn"><?= htmlspecialchars($sub['SubCategoryNameDisp']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($subcategories as $sub): ?>
    <section id="sub<?= (int)$sub['SubCategoryID'] ?>" class="mt-4">
      <h3 class="mb-3"><?= htmlspecialchars($sub['SubCategoryNameDisp']) ?></h3>
      <div class="row g-4">
        <?php foreach ($itemsBySub[$sub['SubCategoryID']] as $item): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <a href="menu_item.php?id=<?= (int)$item['MenuItemID'] ?>&hash=<?= htmlspecialchars($hash) ?>&theme=<?= htmlspecialchars($theme) ?>&lang=<?= htmlspecialchars($lang) ?>"
               class="text-decoration-none <?= $theme === 'dark' ? 'text-light' : 'text-dark' ?>">
              <div class="card h-100">
                <?php if (!empty($item['images'])): ?>
                  <div id="carousel<?= (int)$item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                      <?php foreach ($item['images'] as $i => $img): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                          <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100 menu-img" alt="Menü Görseli">
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <?php if (count($item['images']) > 1): ?>
                      <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?= (int)$item['MenuItemID'] ?>" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                      </button>
                      <button class="carousel-control-next" type="button" data-bs-target="#carousel<?= (int)$item['MenuItemID'] ?>" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                      </button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="card-body">
                  <h5 class="card-title"><?= htmlspecialchars($item['MenuNameDisp']) ?></h5>
                  <p class="card-text"><?= htmlspecialchars($item['DescriptionDisp']) ?></p>
                  <p class="price"><?= number_format((float)$item['Price'], 2) ?> ₺</p>
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
