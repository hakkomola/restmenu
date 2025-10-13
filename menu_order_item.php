<?php
// menu_order_item.php
session_start();
require_once __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$hash   = $_GET['hash']  ?? null;
$itemId = $_GET['id']    ?? null;
$theme  = $_GET['theme'] ?? 'light';
$lang   = $_GET['lang']  ?? 'tr';
$catId  = $_GET['cat']   ?? null;

if (!$hash || !$itemId) die('Geçersiz bağlantı');

/* ===================
   Sabit / HASH çözümü
   =================== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}
function resolve_table(PDO $pdo, string $hash) {
    $rows = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $variants = [
            substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 24),
            md5($r['RestaurantID'].'-'.$r['Code']),
            md5($r['RestaurantID'].$r['Code']),
            md5($r['Code']),
            $r['Code']
        ];
        foreach ($variants as $cand) {
            if (hash_equals($cand, $hash)) return $r;
        }
    }
    return null;
}
$table = resolve_table($pdo, $hash);
if (!$table || !$table['IsActive']) die('Masa bulunamadı veya pasif.');

/* ===================
   Restoran bilgisi
   =================== */
$stmt = $pdo->prepare("SELECT RestaurantID, Name, DefaultLanguage FROM Restaurants WHERE RestaurantID=?");
$stmt->execute([$table['RestaurantID']]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Restoran bulunamadı.');
$restaurantId = (int)$restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];
if (!$lang) $lang = $restaurant['DefaultLanguage'] ?: 'tr';

/* ===================
   Diller
   =================== */
$langStmt = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID=?
    ORDER BY rl.IsDefault DESC, l.LangName
");
$langStmt->execute([$restaurantId]);
$supportedLangs = $langStmt->fetchAll(PDO::FETCH_ASSOC);

function flag_code_from_lang($lc) {
    $map = ['tr'=>'tr','en'=>'gb','de'=>'de','fr'=>'fr','es'=>'es','it'=>'it','ru'=>'ru','ar'=>'sa','fa'=>'ir','zh'=>'cn','ja'=>'jp','el'=>'gr','pt'=>'pt'];
    return $map[strtolower($lc)] ?? strtolower($lc);
}

/* ===================
   UI Metinleri
   =================== */
$tx = [
  'tr' => ['back'=>'Geri','options'=>'Seçenekler','add'=>'Sepete Ekle','home'=>'Ana Menü','cart'=>'Sepet','orders'=>'Siparişlerim'],
  'en' => ['back'=>'Back','options'=>'Options','add'=>'Add to Cart','home'=>'Home','cart'=>'Cart','orders'=>'My Orders']
];
$T = $tx[strtolower($lang)] ?? $tx['tr'];

/* ===================
   Ürün + Opsiyonlar
   =================== */
$stmt = $pdo->prepare("
    SELECT m.MenuItemID,m.MenuName,m.Description,m.SubCategoryID,
           COALESCE(mt.Name,m.MenuName) AS MenuNameDisp,
           COALESCE(mt.Description,m.Description) AS DescriptionDisp
    FROM MenuItems m
    LEFT JOIN MenuItemTranslations mt ON mt.MenuItemID=m.MenuItemID AND mt.LangCode=?
    WHERE m.MenuItemID=? AND m.RestaurantID=?
");
$stmt->execute([$lang,$itemId,$restaurantId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) die('Ürün bulunamadı');

/* Görseller */
$imgStmt=$pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID=?");
$imgStmt->execute([$itemId]);
$images=$imgStmt->fetchAll(PDO::FETCH_COLUMN);

/* Opsiyonlar */
$optStmt=$pdo->prepare("
    SELECT o.*, COALESCE(ot.Name,o.OptionName) AS OptionNameDisp
    FROM MenuItemOptions o
    LEFT JOIN MenuItemOptionTranslations ot ON ot.OptionID=o.OptionID AND ot.LangCode=?
    WHERE o.MenuItemID=?
    ORDER BY o.SortOrder,o.OptionID
");
$optStmt->execute([$lang,$itemId]);
$options=$optStmt->fetchAll(PDO::FETCH_ASSOC);

/* Sepet toplamı */
$total=0;
if(!empty($_SESSION['cart'][$hash])){
  foreach($_SESSION['cart'][$hash] as $it){$total+=$it['price']*$it['qty'];}
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['MenuNameDisp']) ?> - <?= htmlspecialchars($restaurantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Ibarra+Real+Nova:wght@400;600;700&display=swap" rel="stylesheet">
<link href="assets/menu.css" rel="stylesheet">
<style>
/* 🟡 Masaüstünde sayfa genişliğini daralt ve ortala */
@media (min-width: 992px) {
  .container {
    max-width: 500px;   /* 600-800 arası en ideal */
  }
}

</style>
</head>

<body class="menu-body" data-theme="<?= htmlspecialchars($theme) ?>">

  <div class="container my-4">

<div class="header-with-button position-relative text-center mb-3">
  <!-- 🟢 Sağ üstte buton -->
  <button type="button"
          class="btn btn-outline-secondary btn-sm position-absolute"
          style="top:0; right:0;"
          onclick="window.history.back()">
    Menüye Dön
  </button>

  <!-- Başlıklar -->
  <h1 class="mb-1 mt-2 mt-sm-3"><?= htmlspecialchars($restaurantName) ?></h1>
  <h3 class="mb-0"><?= htmlspecialchars($item['MenuNameDisp']) ?></h3>
</div>



  <div class="card">
    <?php if(!empty($images)): ?>
      <div id="carouselItem" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach($images as $i=>$img): ?>
          <div class="carousel-item <?= $i===0?'active':'' ?>">
            <img src="<?= htmlspecialchars($img) ?>" class="d-block w-100 menu-img" alt="">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card-body">
      <?php if($item['DescriptionDisp']): ?>
        <p><?= nl2br(htmlspecialchars($item['DescriptionDisp'])) ?></p>
      <?php endif; ?>

      <?php if(!empty($options)): ?>
        <div class="option-list mt-3">
          <h4><?= htmlspecialchars($T['options']) ?></h4>
          <?php foreach($options as $opt): ?>
            <div class="option-item d-flex justify-content-between align-items-center flex-wrap py-2 border-bottom">
              <div class="flex-grow-1">
                <span class="fw-semibold"><?= htmlspecialchars($opt['OptionNameDisp']) ?></span>
                <small class="text-muted ms-2"><?= number_format((float)$opt['Price'],2) ?> ₺</small>
              </div>
              <div class="qty-group mt-2 mt-sm-0" data-option-id="<?= (int)$opt['OptionID'] ?>">
                <button class="btn btn-outline-secondary btn-sm qty-minus">−</button>
                <input type="number" class="form-control form-control-sm qty-input" value="0" min="0" style="width:60px;">
                <button class="btn btn-outline-secondary btn-sm qty-plus">+</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="mt-4">
        <button class="btn btn-outline-warning w-100 flex-grow-1 add-to-cart"
                data-item-id="<?= (int)$item['MenuItemID'] ?>"
                data-hash="<?= htmlspecialchars($hash) ?>">
          <?= htmlspecialchars($T['add']) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- 🔻 Alt sabit bar -->
<div class="vov-cart-bar">
  <div class="cart-bar-inner container">
    <a href="menu_order.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>" class="btn btn-outline-secondary btn-sm">🍽️ <?= $T['home'] ?></a>
    <a href="menu_cart.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>" class="btn btn-outline-success btn-sm" id="cartButtonBar">
      🛒 <?= $T['cart'] ?> (₺<?= number_format($total,2,',','.') ?>)
    </a>
    <a href="orders.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>" class="btn btn-outline-primary btn-sm">📋 <?= $T['orders'] ?></a>
  </div>
</div>

<!-- Toast alanı -->
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// 🔹 Toast
function showToast(msg,type='success'){
 const c=document.getElementById('toastContainer');
 const id='t'+Math.random().toString(36).slice(2);
 const html=`<div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert">
   <div class="d-flex"><div class="toast-body">${msg}</div>
   <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
 c.insertAdjacentHTML('beforeend',html);
 const el=document.getElementById(id);
 const t=new bootstrap.Toast(el,{delay:1800});t.show();
 el.addEventListener('hidden.bs.toast',()=>el.remove());
}

// 🔹 Artı / Eksi butonları
document.addEventListener('click',e=>{
 if(e.target.closest('.qty-plus')){
   const i=e.target.closest('.qty-group').querySelector('.qty-input');
   i.value=Math.max(0,parseInt(i.value||'0')+1);
 }
 if(e.target.closest('.qty-minus')){
   const i=e.target.closest('.qty-group').querySelector('.qty-input');
   i.value=Math.max(0,parseInt(i.value||'0')-1);
 }
});

// 🔹 Sepete ekle (birden fazla option)
document.querySelector('.add-to-cart').addEventListener('click',async()=>{
 const hash=document.querySelector('.add-to-cart').dataset.hash;
 const itemId=document.querySelector('.add-to-cart').dataset.itemId;
 const groups=document.querySelectorAll('.qty-group');
 let added=0,totalUpdated=0;
 for(const g of groups){
   const qty=parseInt(g.querySelector('.qty-input').value||'0');
   if(qty>0){
     const optionId=g.dataset.optionId;
     const f=new FormData();
     f.append('hash',hash);
     f.append('itemId',itemId);
     f.append('optionId',optionId);
     f.append('qty',qty);
     try{
       const res=await fetch('add_to_cart.php',{method:'POST',body:f});
       const data=await res.json();
       if(data.status==='ok'){totalUpdated=data.total;added++;}
     }catch{}
   }
 }
 if(added>0){
   const formatted=Number(totalUpdated||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
   document.getElementById('cartButtonBar').innerHTML=`🛒 <?= $T['cart'] ?> (₺${formatted})`;
   // inputları sıfırla
   document.querySelectorAll('.qty-input').forEach(i=>i.value='0');
   showToast('Sepete eklendi');
 }else{
   showToast('Lütfen adet giriniz','danger');
 }
});

function goBack() {
  if (document.referrer && document.referrer.includes('menu_order.php')) {
    window.history.back(); // tarayıcı geçmişine dön
  } else {
    // fallback - doğrudan kategoriye veya ana menüye
    const params = new URLSearchParams(window.location.search);
    const hash = params.get("hash");
    const theme = params.get("theme") || "light";
    const lang = params.get("lang") || "tr";
    const cat = params.get("cat");
    let url = "menu_order.php?hash=" + encodeURIComponent(hash)
            + "&theme=" + encodeURIComponent(theme)
            + "&lang=" + encodeURIComponent(lang);
    if (cat) url += "&cat=" + encodeURIComponent(cat);
    window.location.href = url;
  }
}

</script>
</body>
</html>
