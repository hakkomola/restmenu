<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/db.php';

$hash   = $_GET['hash']  ?? null;
$itemId = $_GET['id']    ?? null;
$theme  = $_GET['theme'] ?? 'light';
$lang   = $_GET['lang']  ?? null;
$catId  = $_GET['cat']   ?? null; // 🔸 geri için kategori bilgisini koru

if (!$hash || !$itemId) die('Geçersiz link!');

/* ====== HASH çözümü (masa + restoran + şube tespiti) ====== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

function resolve_table_by_hash(PDO $pdo, string $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, BranchID, Code, Name, IsActive FROM RestaurantTables");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $calc = substr(hash('sha256', $r['RestaurantID'].'|'.(int)$r['BranchID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 32);
        if (hash_equals($calc, $hash)) {
            return $r;
        }
    }
    return null;
}

$tableRow = resolve_table_by_hash($pdo, $hash);
if (!$tableRow) {
    die('Geçersiz veya tanınmayan bağlantı!');
}
if (!$tableRow['IsActive']) {
    die('Bu masa şu anda pasif durumda.');
}

$tableName   = $tableRow['Name'];
$restaurantId = (int)$tableRow['RestaurantID'];
$branchId     = (int)$tableRow['BranchID'];

/* ====== Restoran bilgisi ====== */
$stmt = $pdo->prepare("SELECT RestaurantID, Name, DefaultLanguage FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Geçersiz restoran bağlantısı!');
$restaurantName = $restaurant['Name'];
if (!$lang) $lang = $restaurant['DefaultLanguage'] ?: 'tr';

/* ====== Diller ====== */
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
    'tr' => ['back'=>'Geri Dön','options'=>'Seçenekler'],
    'en' => ['back'=>'Back','options'=>'Options'],
];
$tx = $uiText[strtolower($lang)] ?? $uiText['tr'];

/* ====== MenuItemTranslations FK ====== */
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
} catch (Exception $e) {}

/* ====== Menü öğesi ====== */
$stmt = $pdo->prepare("
    SELECT m.MenuItemID,m.RestaurantID,m.MenuName,m.Description,mo.Price,m.SortOrder,m.SubCategoryID,
           s.SubCategoryID, s.SubCategoryName,
           c.CategoryID, c.CategoryName,
           COALESCE(mt.Name, m.MenuName) AS MenuNameDisp,
           COALESCE(mt.Description, m.Description) AS DescriptionDisp
    FROM MenuItems m
    LEFT JOIN SubCategories s ON m.SubCategoryID = s.SubCategoryID
    LEFT JOIN MenuCategories c ON s.CategoryID = c.CategoryID
    LEFT JOIN MenuItemTranslations mt ON mt.$itemFkCol = m.MenuItemID AND mt.LangCode = ?
    LEFT JOIN MenuItemOptions mo ON m.MenuItemID = mo.MenuItemID AND IsDefault = 1
    WHERE m.MenuItemID = ? AND m.RestaurantID = ?
      AND (m.BranchID = ?)
");
$stmt->execute([$lang, $itemId, $restaurantId, $branchId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) die('Ürün bulunamadı!');

if (!$catId && !empty($item['CategoryID'])) {
    $catId = $item['CategoryID'];
}

/* ====== Görseller ====== */
$stmt2 = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID = ? ");
$stmt2->execute([$itemId]);
$images = [];
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $img) {
    $url = ltrim($img['ImageURL'], '/');
    if (strpos($url, 'uploads/') !== 0) $url = 'uploads/' . $url;
    $images[] = ['ImageURL' => $url];
}

/* ====== Seçenekler ====== */
$stmt3 = $pdo->prepare("
    SELECT o.*, COALESCE(ot.Name, o.OptionName) AS OptionNameDisp
    FROM MenuItemOptions o
    LEFT JOIN MenuItemOptionTranslations ot ON ot.OptionID = o.OptionID AND ot.LangCode = ?
    WHERE o.MenuItemID = ? 
    ORDER BY o.SortOrder, OptionNameDisp
");
$stmt3->execute([$lang, $itemId]);
$options = $stmt3->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['MenuNameDisp']) ?> - <?= htmlspecialchars($restaurantName) ?></title>
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

/* Üst bar: dil bayrakları */
.topbar { display:flex; justify-content:flex-end; align-items:center; gap:8px; padding-top:10px; }
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
  height: 300px; object-fit: cover; width: 100%;
  <?= $theme === 'dark' ? 'filter:brightness(0.85);' : '' ?>
}
.carousel-control-prev-icon,
.carousel-control-next-icon {
  background-color: <?= $theme === 'dark' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.4)' ?>;
  border-radius: 50%; padding: 10px;
}
.carousel-control-prev-icon:hover,
.carousel-control-next-icon:hover {
  background-color: <?= $theme === 'dark' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)' ?>;
}
.card-body { padding: 20px; }
.card-body h3 { color: <?= $theme === 'dark' ? '#ffffff' : '#222' ?>; font-weight: 600; }
.card-body p  { color: <?= $theme === 'dark' ? '#cccccc' : '#555' ?>; font-size: 0.95rem; }
.price { font-weight: 700; font-size: 1.1rem; color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?> !important; }
.back-btn { text-decoration: none; font-weight: 500; color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>; }
.back-btn:hover { text-decoration: underline; }

/* Seçenekler */
.option-list { margin-top: 20px; border-top: 1px solid <?= $theme === 'dark' ? '#333' : '#ddd' ?>; padding-top: 15px; }
.option-list h4 {
  font-size: 1.1rem; font-weight: 600; margin-bottom: 10px;
  color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
}
.option-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed <?= $theme === 'dark' ? '#444' : '#ccc' ?>; }
.option-item:last-child { border-bottom: none; }
.option-item span { font-size: 0.95rem; <?= $theme === 'dark' ? 'color:#ffcc80;' : 'color:#333;' ?> }
</style>
</head>
<body>

<div class="container my-4">

  <div class="topbar">
    <div class="lang-switch">
      <?php foreach ($supportedLangs as $L): 
          $lc = strtolower($L['LangCode']);
          $flag = flag_code_from_lang($lc);
          $isActive = ($lc === strtolower($lang));
          $qs = "hash=".urlencode($hash)."&id=".urlencode($itemId)
              ."&theme=".urlencode($theme)."&lang=".urlencode($lc);
          if (!empty($catId)) $qs .= "&cat=".urlencode($catId);
      ?>
        <a class="<?= $isActive ? 'active' : '' ?>" href="?<?= $qs ?>">
          <img src="https://flagcdn.com/w20/<?= htmlspecialchars($flag) ?>.png" alt="<?= htmlspecialchars($L['LangName']) ?>">
          <span><?= strtoupper($lc) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="page-header">
    <h1><?= htmlspecialchars($restaurantName) ?></h1>
    <h3><?= htmlspecialchars($item['MenuNameDisp']) ?></h3>
  </div>

  <a href="#" onclick="goBack()" class="back-btn mb-3 d-inline-block">&larr; <?= htmlspecialchars($tx['back']) ?></a>

  <div class="card">
    <?php if (!empty($images)): ?>
      <div id="carouselItem<?= (int)$item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($images as $i => $img): ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
              <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100" alt="Menü Görseli">
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <button class="carousel-control-prev" type="button" data-bs-target="#carouselItem<?= (int)$item['MenuItemID'] ?>" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#carouselItem<?= (int)$item['MenuItemID'] ?>" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card-body">
      <?php if (!empty($item['DescriptionDisp'])): ?>
        <p class="mt-2"><?= nl2br(htmlspecialchars($item['DescriptionDisp'])) ?></p>
      <?php endif; ?>
      <p class="price mt-3"><?= number_format((float)$item['Price'], 2) ?> ₺</p>

      <?php if (!empty($options)): ?>
        <div class="option-list">
          <h4><?= htmlspecialchars($tx['options']) ?></h4>
          <?php foreach ($options as $opt): ?>
            <div class="option-item">
              <span><?= htmlspecialchars($opt['OptionNameDisp']) ?></span>
              <span><?= number_format((float)$opt['Price'], 2) ?> ₺</span>
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
  const params = new URLSearchParams(window.location.search);
  const hash  = params.get("hash");
  const theme = params.get("theme") || "light";
  const lang  = params.get("lang")  || "tr";
  const cat   = params.get("cat");
  let url = "menu.php?hash=" + encodeURIComponent(hash)
          + "&theme=" + encodeURIComponent(theme)
          + "&lang=" + encodeURIComponent(lang);
  if (cat) url += "&cat=" + encodeURIComponent(cat);
  window.location.href = url;
}
</script>
</body>
</html>
