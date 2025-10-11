<?php
session_start();
require_once __DIR__ . '/db.php';

$hash  = $_GET['hash']  ?? null;
$catId = $_GET['cat']   ?? null;
$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang']  ?? null;
if (!$hash) die('Geçersiz link!');

/* ==== HASH çözümü ==== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}
function resolve_table_by_hash(PDO $pdo, string $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cand = substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 24);
        if (hash_equals($cand, $hash)) return $r;
    }
    return null;
}

$tableRow = resolve_table_by_hash($pdo, $hash);
if (!$tableRow) die('Geçersiz bağlantı');
if (empty($tableRow['IsActive'])) die('Bu masa pasif durumda.');

$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$tableRow['RestaurantID']]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Restoran bulunamadı');
$restaurantId = $restaurant['RestaurantID'];
$lang = $lang ?: ($restaurant['DefaultLanguage'] ?: 'tr');

/* ==== UI metinleri ==== */
$tx = [
  'home'=>'Ana Menü','add'=>'Sepete Ekle','more'=>'Seçenekleri Gör',
  'cart'=>'Sepet','emptyCart'=>'Sepetiniz boş.','checkout'=>'Siparişi Gönder'
];

/* ==== Ürünler ==== */
$stmt = $pdo->prepare("
SELECT mi.MenuItemID, mi.MenuName, mi.Description,
(SELECT Price FROM MenuItemOptions WHERE MenuItemID=mi.MenuItemID AND IsDefault=1 LIMIT 1) as Price
FROM MenuItems mi WHERE mi.RestaurantID = ? LIMIT 20");
$stmt->execute([$restaurantId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($restaurant['Name']) ?> - Menü</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  font-family:'Ibarra Real Nova',sans-serif;
  <?= $theme==='dark'?'background:#121212;color:#f1f1f1;':'background:#f8f9fa;color:#333;' ?>
}
.card { border:none; border-radius:12px; background:<?= $theme==='dark'?'#1e1e1e':'#fff' ?>;
  box-shadow:0 2px 6px <?= $theme==='dark'?'rgba(255,255,255,0.05)':'rgba(0,0,0,0.1)' ?>; }
.price { color:<?= $theme==='dark'?'#ff9800':'#007bff' ?>; font-weight:600; }

/* Floating mini cart */
.floating-cart{position:fixed;bottom:20px;right:20px;z-index:2000;}
.cart-btn{font-weight:600;border-radius:50px;
  background-color:<?= $theme==='dark'?'#ff9800':'#007bff' ?>;color:<?= $theme==='dark'?'#000':'#fff' ?>;
  box-shadow:0 3px 8px rgba(0,0,0,0.25);}
.cart-popup{display:none;position:absolute;bottom:50px;right:0;width:300px;
  background:<?= $theme==='dark'?'#1e1e1e':'#fff' ?>;border:1px solid <?= $theme==='dark'?'#444':'#ccc' ?>;
  border-radius:12px;padding:12px;box-shadow:0 8px 16px rgba(0,0,0,.25);}
.cart-item-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.cart-item-name{font-weight:500;flex-grow:1;}
.remove-btn{border:none;background:none;color:red;font-size:1.3rem;line-height:1;cursor:pointer;}
.remove-btn:hover{color:darkred;}
</style>
</head>
<body>
<div class="container py-4">
  <h2 class="text-center mb-4"><?= htmlspecialchars($restaurant['Name']) ?> Menüsü</h2>
  <div class="row g-4">
    <?php foreach($items as $it): ?>
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card p-3 text-center">
          <h6><?= htmlspecialchars($it['MenuName']) ?></h6>
          <p class="text-muted small"><?= htmlspecialchars($it['Description']) ?></p>
          <p class="price mb-2"><?= number_format($it['Price']??0,2) ?> ₺</p>
          <button class="btn btn-<?= $theme==='dark'?'warning':'primary' ?> btn-sm add-to-cart"
            data-id="<?= $it['MenuItemID'] ?>" data-price="<?= $it['Price'] ?? 0 ?>"> <?= $tx['add'] ?> </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Mini Cart -->
<div class="floating-cart">
  <button id="cartToggle" class="btn cart-btn">
    🛒 <span id="cartCount">0</span> • <span id="cartTotal">0.00</span> ₺
  </button>
  <div id="cartPopup" class="cart-popup">
    <h6 class="fw-bold mb-2"><?= $tx['cart'] ?></h6>
    <div id="cartItems"></div>
    <p class="mt-2 mb-1 text-end fw-semibold">Toplam: <span id="popupTotal">0.00 ₺</span></p>
    <button id="cartCheckoutBtn" class="btn btn-success btn-sm w-100 mt-2"><?= $tx['checkout'] ?></button>
  </div>
</div>

<script>
async function updateCartSummary(){
  const res=await fetch('get_cart_summary.php?hash=<?=urlencode($hash)?>');
  const data=await res.json().catch(()=>({}));
  const countEl=document.getElementById('cartCount');
  const totalEl=document.getElementById('cartTotal');
  const popupTotal=document.getElementById('popupTotal');
  const itemsWrap=document.getElementById('cartItems');
  if(data.status==='ok'){
    countEl.textContent=data.count||0;
    totalEl.textContent=data.total||'0.00';
    popupTotal.textContent=(data.total||'0.00')+' ₺';
    itemsWrap.innerHTML='';
    const items=data.items||[];
    if(!items.length){
      itemsWrap.innerHTML='<p class="text-muted small mb-0"><?= $tx['emptyCart'] ?></p>';
    }else{
      items.forEach(it=>{
        const row=document.createElement('div');
        row.className='cart-item-row';
        row.innerHTML=`
          <div class="cart-item-name">${it.name}</div>
          <div>${it.qty}×${it.price}₺</div>
          <button class="remove-btn" data-key="${it.key}">×</button>`;
        itemsWrap.appendChild(row);
      });
    }
  }
}

async function addToCart(id,price){
  await fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({item_id:id,option_id:id,quantity:1,hash:'<?=$hash?>',price})});
  updateCartSummary();
}
async function removeFromCart(key){
  await fetch('remove_from_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({hash:'<?=$hash?>',key})});
  updateCartSummary();
}

document.addEventListener('DOMContentLoaded',()=>{
  document.body.addEventListener('click',e=>{
    const add=e.target.closest('.add-to-cart');if(add) addToCart(add.dataset.id,add.dataset.price);
    const rm=e.target.closest('.remove-btn');if(rm) removeFromCart(rm.dataset.key);
  });
  const cartToggle=document.getElementById('cartToggle');
  const popup=document.getElementById('cartPopup');
  cartToggle.addEventListener('click',()=>popup.style.display=(popup.style.display==='block')?'none':'block');
  document.addEventListener('click',e=>{if(!e.target.closest('.floating-cart')) popup.style.display='none';});
  updateCartSummary();
});
</script>
</body>
</html>
