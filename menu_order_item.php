<?php
session_start();
require_once __DIR__ . '/db.php';

$hash   = $_GET['hash']  ?? null;
$itemId = $_GET['id']    ?? null;
$theme  = $_GET['theme'] ?? 'light';
$lang   = $_GET['lang']  ?? null;
$catId  = $_GET['cat']   ?? null;

if (!$hash || !$itemId) die('Geçersiz link!');

/* ====== HASH çözümü (masa + restoran tespiti) ====== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

function resolve_table_by_hash(PDO $pdo, string $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $candidates = [
            substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 24),
            md5($r['RestaurantID'].'-'.$r['Code']),
            md5($r['RestaurantID'].$r['Code']),
            md5($r['Code']),
            $r['Code']
        ];
        foreach ($candidates as $cand) {
            if (hash_equals($cand, $hash)) return $r;
        }
    }
    return null;
}

$tableRow = resolve_table_by_hash($pdo, $hash);
if (!$tableRow) die('Geçersiz veya tanınmayan bağlantı!');
if (!$tableRow['IsActive']) die('Bu masa şu anda pasif durumda.');

/* ====== Restoran bilgisi ====== */
$stmt = $pdo->prepare("SELECT RestaurantID, Name, DefaultLanguage FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$tableRow['RestaurantID']]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Geçersiz restoran bağlantısı!');
$restaurantId = (int)$restaurant['RestaurantID'];
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
    $map = [
        'tr'=>'tr','en'=>'gb','de'=>'de','fr'=>'fr','es'=>'es','it'=>'it','nl'=>'nl','ru'=>'ru',
        'ar'=>'sa','fa'=>'ir','zh'=>'cn','ja'=>'jp','ko'=>'kr','el'=>'gr','he'=>'il','pt'=>'pt','az'=>'az'
    ];
    return $map[strtolower($lc)] ?? strtolower($lc);
}

$uiText = [
    'tr' => ['back'=>'Geri Dön','options'=>'Seçenekler'],
    'en' => ['back'=>'Back','options'=>'Options'],
];
$tx = $uiText[strtolower($lang)] ?? $uiText['tr'];

/* ====== Ürün bilgisi ====== */
$itemFkCol = 'MenuItemID';
$colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MenuItemTranslations' 
AND COLUMN_NAME IN ('MenuItemID','ItemID') LIMIT 1");
$colCheck->execute();
$found = $colCheck->fetchColumn();
if ($found) $itemFkCol = $found;

$stmt = $pdo->prepare("
    SELECT m.MenuItemID,m.RestaurantID,m.MenuName,m.Description,mo.Price,
           s.SubCategoryID,c.CategoryID,
           COALESCE(mt.Name, m.MenuName) AS MenuNameDisp,
           COALESCE(mt.Description, m.Description) AS DescriptionDisp
    FROM MenuItems m
    LEFT JOIN SubCategories s ON m.SubCategoryID = s.SubCategoryID
    LEFT JOIN MenuCategories c ON s.CategoryID = c.CategoryID
    LEFT JOIN MenuItemTranslations mt
           ON mt.$itemFkCol = m.MenuItemID AND mt.LangCode = ?
    LEFT JOIN MenuItemOptions mo 
           ON m.MenuItemID=mo.MenuItemID AND mo.IsDefault=1
    WHERE m.MenuItemID = ? AND c.RestaurantID = ?
");
$stmt->execute([$lang, $itemId, $restaurantId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) die('Ürün bulunamadı!');
if (!$catId && !empty($item['CategoryID'])) $catId = $item['CategoryID'];

/* ====== Görseller ====== */
$stmt2 = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID = ?");
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
    LEFT JOIN MenuItemOptionTranslations ot
           ON ot.OptionID = o.OptionID AND ot.LangCode = ?
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
  <?= $theme==='dark' ? 'background:#121212;color:#f1f1f1;' : 'background:#f8f9fa;color:#222;' ?>
}
.container { max-width: 700px; }
.topbar { display:flex; justify-content:space-between; align-items:center; padding-top:10px; }
.lang-switch { display:flex; gap:8px; flex-wrap:wrap; }
.lang-switch a {
  display:inline-flex; align-items:center; gap:6px;
  padding:2px 4px; border-radius:10px; font-weight:600; text-decoration:none;
  border:1px solid <?= $theme==='dark'?'#555':'#ccc' ?>;
  <?= $theme==='dark'?'background:#1e1e1e;color:#eee;':'background:#fff;color:#333;' ?>
}
.lang-switch a.active {
  <?= $theme==='dark'
    ? 'background:#ff9800;color:#000;border-color:#ff9800;'
    : 'background:#007bff;color:#fff;border-color:#007bff;' ?>
}
.lang-switch img { width:22px;height:15px;border-radius:3px;object-fit:cover; }
.page-header { text-align:center;margin-bottom:20px;padding-top:10px; }
.page-header h1 { font-size:clamp(22px,5vw,34px);font-weight:700;margin-bottom:6px; }
.page-header h3 { font-size:clamp(18px,4vw,26px);opacity:0.85;font-weight:500; }
.card { border:none;border-radius:12px;overflow:hidden;
  box-shadow:0 2px 8px <?= $theme==='dark'?'rgba(255,255,255,0.06)':'rgba(0,0,0,0.1)' ?>;
  background:<?= $theme==='dark'?'#1e1e1e':'#fff' ?>;transition:all 0.25s;
}
.card:hover {
  <?= $theme==='dark'
    ? 'background-color:#252525;box-shadow:0 6px 14px rgba(255,255,255,0.1);'
    : 'box-shadow:0 6px 14px rgba(0,0,0,0.15);' ?>
}
.carousel-item img { height:300px;object-fit:cover;width:100%; }
.card-body p { font-size:0.95rem; }
.price { font-weight:700;font-size:1.1rem;color:<?= $theme==='dark'?'#ff9800':'#007bff' ?> !important; }
.option-list { margin-top:20px;border-top:1px solid <?= $theme==='dark'?'#333':'#ddd' ?>;padding-top:15px; }
.option-item {
  display:flex;justify-content:space-between;align-items:center;
  padding:8px 0;border-bottom:1px dashed <?= $theme==='dark'?'#444':'#ccc' ?>;
}
.option-item:last-child { border-bottom:none; }
.qty-controls { display:flex;align-items:center;gap:6px; }
.qty { min-width:20px;text-align:center;font-weight:600; }
.btn-minus,.btn-plus { width:30px;height:30px;line-height:1;padding:0; }

/* Sağ alt sepet butonu */
.cart-fab {
  position: fixed; right:18px; bottom:18px;
  background-color: <?= $theme==='dark' ? '#ff9800' : '#007bff' ?>;
  color:white; border-radius:50%; width:58px; height:58px;
  display:flex; align-items:center; justify-content:center;
  font-size:22px; font-weight:bold; text-decoration:none;
  box-shadow:0 4px 12px rgba(0,0,0,0.25);
  transition:all 0.2s ease-in-out; z-index:9999;
}
.cart-fab:hover { transform:scale(1.05);text-decoration:none; }
#cart-count {
  position:absolute; top:4px; right:6px; background:red; color:white;
  border-radius:50%; font-size:12px; font-weight:700;
  width:18px; height:18px; display:flex; align-items:center; justify-content:center;
}
</style>
</head>
<body>
<div class="container my-4">
  <div class="topbar">
    <div class="lang-switch">
      <?php foreach ($supportedLangs as $L):
        $lc = strtolower($L['LangCode']); $flag = flag_code_from_lang($lc);
        $isActive = ($lc === strtolower($lang));
        $qs = "hash=$hash&id=$itemId&theme=$theme&lang=$lc";
        if ($catId) $qs .= "&cat=$catId";
      ?>
        <a class="<?= $isActive?'active':'' ?>" href="?<?= htmlspecialchars($qs) ?>">
          <img src="https://flagcdn.com/w20/<?= htmlspecialchars($flag) ?>.png">
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
    <?php if ($images): ?>
      <div id="carouselItem<?= $item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($images as $i=>$img): ?>
            <div class="carousel-item <?= $i===0?'active':'' ?>">
              <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100" alt="">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card-body">
      <?php if ($item['DescriptionDisp']): ?>
        <p><?= nl2br(htmlspecialchars($item['DescriptionDisp'])) ?></p>
      <?php endif; ?>
      <p class="price"><?= number_format((float)$item['Price'],2) ?> ₺</p>

      <?php if ($options): ?>
        <div class="option-list">
          <h4><?= htmlspecialchars($tx['options']) ?></h4>
          <?php foreach ($options as $opt): ?>
            <div class="option-item" data-id="<?= $opt['OptionID'] ?>">
              <div>
                <strong><?= htmlspecialchars($opt['OptionNameDisp']) ?></strong><br>
                <small><?= number_format((float)$opt['Price'],2) ?> ₺</small>
              </div>
              <div class="qty-controls">
                <button class="btn btn-sm btn-outline-secondary btn-minus">−</button>
                <span class="qty">0</span>
                <button class="btn btn-sm btn-outline-primary btn-plus">+</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Sağ alt sabit sepet butonu -->
<a href="menu_order.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>"
   class="cart-fab shadow-lg">
  🛒 <span id="cart-count"></span>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function goBack() {
  const params = new URLSearchParams(window.location.search);
  const hash=params.get("hash"), theme=params.get("theme")||"light", lang=params.get("lang")||"tr", cat=params.get("cat");
  let url="menu.php?hash="+encodeURIComponent(hash)+"&theme="+encodeURIComponent(theme)+"&lang="+encodeURIComponent(lang);
  if(cat) url+="&cat="+encodeURIComponent(cat);
  window.location.href=url;
}

document.addEventListener('DOMContentLoaded',()=>{
  const hash="<?= htmlspecialchars($hash) ?>";

  // + / - butonları
  document.querySelectorAll('.option-item').forEach(opt=>{
    const optId=opt.dataset.id;
    const minus=opt.querySelector('.btn-minus');
    const plus=opt.querySelector('.btn-plus');
    const qtyEl=opt.querySelector('.qty');
    minus.addEventListener('click',()=>updateQty(optId,-1,qtyEl));
    plus.addEventListener('click',()=>updateQty(optId,+1,qtyEl));
  });

  function updateQty(optId,delta,qtyEl){
    let qty=parseInt(qtyEl.textContent)||0;
    qty=Math.max(0,qty+delta);
    qtyEl.textContent=qty;
    fetch('ajax_cart_update.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({hash:hash,option_id:optId,qty:qty})
    })
    .then(r=>r.json())
    .then(d=>{
      if(d.status!=='ok') alert(d.message||'Bir hata oluştu');
      updateCartCount();
    })
    .catch(console.error);
  }

  // sepet sayısını çek
  function updateCartCount(){
    fetch('ajax_cart_count.php?hash='+encodeURIComponent(hash))
      .then(r=>r.json())
      .then(d=>{
        document.getElementById('cart-count').textContent = d.count>0 ? d.count : '';
      });
  }

  updateCartCount();
});
</script>
</body>
</html>
