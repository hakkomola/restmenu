<?php
require_once __DIR__ . '/db.php';

$hash  = $_GET['hash']  ?? null;
$catId = $_GET['cat']   ?? null;
$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang']  ?? null;

if (!$hash) die('Geçersiz link!');

/* ==== HASH yardımcıları ==== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}
function table_public_hash_local(int $rid, string $code): string {
    return substr(hash('sha256', $rid . '|' . $code . '|' . RESTMENU_HASH_PEPPER), 0, 24);
}
function resolve_table_by_hash(PDO $pdo, string $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables");
    foreach ($stmt as $r) {
        $variants = [
            substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 24),
            md5($r['RestaurantID'].'-'.$r['Code']),
            md5($r['RestaurantID'].$r['Code']),
            md5($r['Code']),
            $r['Code']
        ];
        foreach ($variants as $v) if (hash_equals($v, $hash)) return $r;
    }
    return null;
}

/* ==== Masa / restoran çöz ==== */
$tableRow = resolve_table_by_hash($pdo, $hash);
if (!$tableRow || empty($tableRow['IsActive'])) die('Bu masa pasif veya geçersiz.');
$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID=?");
$stmt->execute([$tableRow['RestaurantID']]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC) ?: die('Restoran bulunamadı.');
$restaurantId = (int)$restaurant['RestaurantID'];
$restaurantName = $restaurant['Name'];
if (!$lang) $lang = $restaurant['DefaultLanguage'] ?: 'tr';

/* ==== UI metinleri ==== */
$uiText = [
 'tr'=>['home'=>'Ana Menü','add'=>'Sepete Ekle','more'=>'Seçenekleri Gör','cart'=>'Sepet','viewAll'=>'Tümünü Gör','checkout'=>'Siparişi Gönder','emptyCart'=>'Sepetiniz boş.'],
 'en'=>['home'=>'Main Menu','add'=>'Add to Cart','more'=>'See Options','cart'=>'Cart','viewAll'=>'View All','checkout'=>'Submit Order','emptyCart'=>'Your cart is empty.']
];
$tx=$uiText[$lang]??$uiText['tr'];


/* ==== MenuItemTranslations FK kolon tespiti ==== */
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

/* ==== VERİ ÇEKİMİ ==== */
if ($catId) {
    // Kategori
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

    // Alt kategoriler
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

    $subcategories = [];
    $itemsBySub    = [];

    // Ürünler + opsiyon özeti (count, default, tek opsiyon)
    $sqlItems = "
        SELECT 
            mi.MenuItemID, mi.RestaurantID, mi.MenuName, mi.Description, mi.SortOrder, mi.SubCategoryID,
            COALESCE(mt.Name, mi.MenuName)          AS MenuNameDisp,
            COALESCE(mt.Description, mi.Description) AS DescriptionDisp,
            (SELECT COUNT(*) FROM MenuItemOptions moo WHERE moo.MenuItemID = mi.MenuItemID) AS OptionsCount,
            (SELECT moo.OptionID FROM MenuItemOptions moo
             WHERE moo.MenuItemID = mi.MenuItemID AND moo.IsDefault = 1
             ORDER BY moo.SortOrder, moo.OptionID LIMIT 1) AS DefaultOptionID,
            (SELECT moo.Price FROM MenuItemOptions moo
             WHERE moo.MenuItemID = mi.MenuItemID AND moo.IsDefault = 1
             ORDER BY moo.SortOrder, moo.OptionID LIMIT 1) AS DefaultPrice,
            (SELECT moo.OptionID FROM MenuItemOptions moo
             WHERE moo.MenuItemID = mi.MenuItemID
             ORDER BY moo.SortOrder, moo.OptionID LIMIT 1) AS SingleOptionID,
            (SELECT moo.Price FROM MenuItemOptions moo
             WHERE moo.MenuItemID = mi.MenuItemID
             ORDER BY moo.SortOrder, moo.OptionID LIMIT 1) AS SingleOptionPrice
        FROM MenuItems mi
        LEFT JOIN MenuItemTranslations mt ON mt.$itemFkCol = mi.MenuItemID AND mt.LangCode = ?
        WHERE mi.SubCategoryID = ?
        ORDER BY MenuNameDisp
    ";
    $stmtItems = $pdo->prepare($sqlItems);

    foreach ($allSubcategories as $sub) {
        $stmtItems->execute([$lang, $sub['SubCategoryID']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if ($items) {
            // Görseller
            foreach ($items as $ix => $it) {
                $stmtImg = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID = ?");
                $stmtImg->execute([$it['MenuItemID']]);
                $imgs = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
                $fixed = [];
                foreach ($imgs as $img) {
                    $url = ltrim($img['ImageURL'], '/');
                    if (strpos($url, 'uploads/') !== 0) $url = 'uploads/'.$url;
                    $fixed[] = ['ImageURL' => $url];
                }
                $items[$ix]['images'] = $fixed;
            }
            $subcategories[] = $sub;
            $itemsBySub[$sub['SubCategoryID']] = $items;
        }
    }

} else {
    // Kategori listesi
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
<link href="https://fonts.googleapis.com/css2?family=Ibarra+Real+Nova:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
  font-family: "Ibarra Real Nova", sans-serif;
  scroll-behavior: smooth;
  <?= $theme === 'dark'
      ? 'background-color:#121212;color:#f1f1f1;'
      : 'background-color:#f8f9fa;color:#333;'
  ?>
}
.topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:10px 0; }
.lang-switch { display:flex; gap:8px; flex-wrap:wrap; }
.lang-switch a {
  display:inline-flex; align-items:center; gap:6px; padding:2px 4px; border-radius:10px; font-weight:600; text-decoration:none;
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

.page-header { text-align:center; margin-bottom:25px; padding-top:10px; }
.page-header h1 { font-size: clamp(22px, 5vw, 34px); font-weight:700; margin-bottom:5px; }
.page-header h3 { font-size: clamp(18px, 4vw, 26px); opacity:.85; font-weight:500; }

.card {
  border:none; border-radius:12px; overflow:hidden;
  box-shadow: 0 2px 8px <?= $theme === 'dark' ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.08)' ?>;
  background: <?= $theme === 'dark' ? '#1e1e1e' : '#fff' ?>;
  transition: all .25s ease-in-out;
}
.card:hover {
  <?= $theme === 'dark'
      ? 'background-color:#252525;box-shadow:0 6px 14px rgba(255,255,255,0.1);'
      : 'box-shadow:0 6px 14px rgba(0,0,0,0.15);'
  ?>
}
.card-title { color: <?= $theme === 'dark' ? '#fff' : '#222' ?>; font-weight:600; }
.card-text  { color: <?= $theme === 'dark' ? '#ccc' : '#555' ?>; }
.price { font-weight:700; font-size:1rem; color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>; }
.category-img, .menu-img { height:220px; object-fit:cover; width:100%; <?= $theme === 'dark' ? 'filter:brightness(0.9);' : '' ?> }

.subcategory-menu {
  position: sticky; top: 0; z-index: 999;
  background-color: <?= $theme === 'dark' ? 'rgba(18,18,18,0.95)' : 'rgba(255,255,255,0.95)' ?>;
  padding: 8px 0; border-bottom: 1px solid <?= $theme === 'dark' ? '#333' : '#ddd' ?>;
  overflow-x: auto; white-space: nowrap; scrollbar-width: none;
}
.subcategory-menu::-webkit-scrollbar { display: none; }
.subcategory-menu a {
  display:inline-block; margin:0 4px; border-radius:50px; padding:6px 14px; font-size:.9rem;
  border:1px solid <?= $theme === 'dark' ? '#555' : '#ccc' ?>;
  color: <?= $theme === 'dark' ? '#ddd' : '#333' ?>;
  background: <?= $theme === 'dark' ? '#1e1e1e' : '#f8f9fa' ?>;
}
.subcategory-menu a.active {
  background-color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
  border-color: <?= $theme === 'dark' ? '#ff9800' : '#007bff' ?>;
  color: <?= $theme === 'dark' ? '#000' : '#fff' ?>;
}
section { scroll-margin-top: 80px; }

.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 1080; }

/* Adet seçici - kompakt */
.qty-group { display:flex; align-items:center; gap:6px; }
.qty-group .btn { padding: .25rem .5rem; }
.qty-input { width: 56px; text-align:center; }

/* Floating Cart */
.floating-cart { position: fixed; bottom: 20px; right: 20px; z-index: 2000; }
.cart-btn{
  background-color:#ff9800 !important;
  border-color:#ff9800 !important;
  color:#000 !important;
  font-weight:600;
  border-radius:50px;
  box-shadow:0 3px 8px rgba(0,0,0,0.25);
  outline:none !important;
  -webkit-tap-highlight-color: transparent; /* iOS tap griliğini kaldır */
}

/* Tüm hallerde aynı renk kalsın */
.cart-btn:hover,
.cart-btn:focus,
.cart-btn:active,
.cart-btn:focus:active,
.btn.cart-btn:active,
.btn.cart-btn:focus{
  background-color:#ff9800 !important;
  border-color:#ff9800 !important;
  color:#000 !important;
  box-shadow:0 3px 8px rgba(0,0,0,0.25) !important;
}

/* Bootstrap toggle buton varyasyonları için de sabitle */
.btn-check:checked + .cart-btn,
.btn-check:active + .cart-btn,
.btn-check:focus + .cart-btn{
  background-color:#ff9800 !important;
  border-color:#ff9800 !important;
  color:#000 !important;
}
.cart-popup {
  display: none;
  position: absolute;
  bottom: 50px;
  right: 0;
  background: <?= $theme === 'dark' ? '#1e1e1e' : '#fff' ?>;
  color: <?= $theme === 'dark' ? '#eee' : '#222' ?>;
  border: 1px solid <?= $theme === 'dark' ? '#444' : '#ddd' ?>;
  border-radius: 16px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.35);
  width: 340px;           /* 🔹 eskiden 280px idi, biraz genişlettik */
  padding: 16px;          /* içerik daha ferah dursun */
}

.cart-popup-content {
  max-height: 340px;      /* 🔹 daha fazla ürün görünsün */
  overflow-y: auto;
  font-size: .9rem;
}
.cart-item-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
.cart-item-name { font-weight:500; flex-grow:1; }
.cart-item-qty { opacity:.8; font-size:.85rem; }

.cart-item-remove {
  color: #dc3545;              /* kırmızı */
  font-weight: bold;
  font-size: 2.5rem;           /* daha büyük görünsün */
  margin-left: 8px;
  cursor: pointer;             /* mouse ile el işareti çıkar */
  user-select: none;           /* metin seçilemez */
  transition: transform 0.15s ease;
  touch-action: manipulation;
}




@media (max-width: 576px) {
  .category-grid { display:grid !important; grid-template-columns:repeat(2,1fr); gap:12px; }
  .category-grid img { height:130px; }
}
</style>
</head>
<body data-bs-spy="scroll" data-bs-target="#subcategoryNav" data-bs-offset="100" tabindex="0">

<div class="container py-4">

  <!-- Üst bar: diller -->
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
    </div>
  </div>

<?php if (!$catId): ?>
  <!-- Ana sayfa / Kategoriler -->
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

  <?php 
    // Tema bazlı buton rengi: light = primary (mavi), dark = warning (amber)
    $addBtnColorClass = ($theme === 'dark') ? 'btn-warning' : 'btn-primary';
  ?>

  <?php foreach ($subcategories as $sub): ?>
    <section id="sub<?= (int)$sub['SubCategoryID'] ?>" class="mt-4">
      <h3 class="mb-3"><?= htmlspecialchars($sub['SubCategoryNameDisp']) ?></h3>
      <div class="row g-4">
        <?php foreach ($itemsBySub[$sub['SubCategoryID']] as $item): ?>
          <?php
            $optionsCount    = (int)($item['OptionsCount'] ?? 0);
            $defaultOptionID = $item['DefaultOptionID'] ?? null;
            $defaultPrice    = $item['DefaultPrice'] ?? null;
            $singleOptionID  = $item['SingleOptionID'] ?? null;
            $singlePrice     = $item['SingleOptionPrice'] ?? null;

            // Hızlı ekleme için kullanılacak opsiyon ID
            $quickOptionId = null;
            if ($optionsCount === 1 && $singleOptionID) {
                $quickOptionId = $singleOptionID;
            } elseif (!empty($defaultOptionID)) {
                $quickOptionId = $defaultOptionID;
            }

            // Gösterilecek fiyat (varsa default, yoksa tek opsiyon)
            $displayPrice = null;
            if (!empty($defaultOptionID) && $defaultPrice !== null) {
                $displayPrice = (float)$defaultPrice;
            } elseif ($optionsCount === 1 && $singlePrice !== null) {
                $displayPrice = (float)$singlePrice;
            }

            $detailUrl = 'menu_item.php?id='.(int)$item['MenuItemID'].'&hash='.urlencode($hash).'&theme='.urlencode($theme).'&lang='.urlencode($lang);
          ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 d-flex flex-column">
              <a href="<?= $detailUrl ?>"
                 class="text-decoration-none <?= $theme === 'dark' ? 'text-light' : 'text-dark' ?>">
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
              </a>

              <div class="card-body d-flex flex-column">
                <a href="<?= $detailUrl ?>"
                   class="text-decoration-none <?= $theme === 'dark' ? 'text-light' : 'text-dark' ?>">
                  <h5 class="card-title mb-1"><?= htmlspecialchars($item['MenuNameDisp']) ?></h5>
                </a>
                <?php if (!empty($item['DescriptionDisp'])): ?>
                  <p class="card-text mb-2"><?= htmlspecialchars($item['DescriptionDisp']) ?></p>
                <?php endif; ?>

                <?php if ($displayPrice !== null): ?>
                  <p class="price mb-2"><?= number_format((float)$displayPrice, 2) ?> ₺</p>
                <?php else: ?>
                  <p class="price mb-2" style="opacity:.8;">&nbsp;</p>
                <?php endif; ?>

                <!-- Adet seçici -->
                <div class="qty-group mb-2" data-item="<?= (int)$item['MenuItemID'] ?>">
                  <button class="btn btn-outline-secondary btn-sm qty-minus" type="button" aria-label="Azalt" <?= $quickOptionId ? '' : 'disabled' ?>>−</button>
                  <input type="number" class="form-control form-control-sm qty-input" value="1" min="1" step="1" <?= $quickOptionId ? '' : 'disabled' ?> />
                  <button class="btn btn-outline-secondary btn-sm qty-plus" type="button" aria-label="Arttır" <?= $quickOptionId ? '' : 'disabled' ?>>+</button>
                </div>

                <div class="mt-auto d-flex gap-2">
                  <?php if ($quickOptionId): ?>
                  <button 
                    class="btn <?= $addBtnColorClass ?> btn-sm flex-grow-1 add-to-cart"
                    data-item-id="<?= (int)$item['MenuItemID'] ?>"
                    data-option-id="<?= (int)$quickOptionId ?>"
                    data-hash="<?= htmlspecialchars($hash) ?>"
                  ><?= htmlspecialchars($tx['add']) ?></button>
                  <?php else: ?>
                  <a class="btn btn-secondary btn-sm flex-grow-1 disabled" aria-disabled="true" href="#">
                    <?= htmlspecialchars($tx['add']) ?>
                  </a>
                  <?php endif; ?>

                  <?php if ($optionsCount > 1): ?>
                  <a class="btn <?= $theme === 'dark' ? 'btn-outline-light btn-sm' : 'btn-outline-secondary btn-sm' ?>"
                     href="<?= $detailUrl ?>"><?= htmlspecialchars($tx['more']) ?></a>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

</div>


<!-- Toasts -->
<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Basit toast
function showToast(message) {
  const container = document.getElementById('toastContainer');
  const id = 't' + Math.random().toString(36).slice(2);
  const html = `
  <div id="${id}" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>`;
  container.insertAdjacentHTML('beforeend', html);
  const el = document.getElementById(id);
  const t = new bootstrap.Toast(el, { delay: 1800 });
  t.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}

// Sepete ekle (AJAX)
async function addToCart(itemId, optionId, hash, quantity) {
  try {
    const body = new URLSearchParams({ item_id: itemId, option_id: optionId, quantity: quantity || 1, hash: hash });
    const res  = await fetch('add_to_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok && data && (data.status === 'ok' || data.success === true)) {
      updateCartSummary(); // 🧠 kritik: anında mini sepeti güncelle
    } else {
      showToast(data.message || 'İşlem başarısız.');
    }
  } catch (e) {
    showToast('Bağlantı hatası.');
  }
}

// Mini sepeti güncelle
async function updateCartSummary() {
  try {
    const res = await fetch(`get_cart_summary.php?hash=<?=urlencode($hash)?>`);
    const data = await res.json();
    if (data.status === 'ok') {
      const countEl = document.getElementById('cartCount');
      const totalEl = document.getElementById('cartTotal');
      const popupTotalEl = document.getElementById('popupTotal');
      const itemsWrap = document.getElementById('cartItems');

      if (countEl) countEl.textContent = data.count || 0;
      if (totalEl) totalEl.textContent = data.total || '0.00';
      if (popupTotalEl) popupTotalEl.textContent = (data.total || '0.00') + ' ₺';

      if (itemsWrap) {
        itemsWrap.innerHTML = '';
        const items = data.items || [];
        if (!items.length) {
          itemsWrap.innerHTML = `<p class="text-muted small mb-0"><?= htmlspecialchars($tx['emptyCart']) ?></p>`;
        } else {
          items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'cart-item-row';
row.innerHTML = `
  <div class="cart-item-name">${item.name}<br>
    <small class="text-muted">${item.option_name}</small>
  </div>
  <div class="cart-item-qty">
    ${item.qty} × ${item.price} ₺
    <span class="cart-item-remove" data-key="${item.key}">&times;</span>
  </div>
`;


            itemsWrap.appendChild(row);
          });
        }
      }
    }
  } catch (err) {
    console.error('Sepet güncellenemedi', err);
  }
}




  // Adet +/- butonları
  addEventListener('click', function(e) {
    const minus = e.target.closest('.qty-minus');
    const plus  = e.target.closest('.qty-plus');
    if (!minus && !plus) return;
    e.preventDefault();
    const group = e.target.closest('.qty-group');
    const input = group ? group.querySelector('.qty-input') : null;
    if (!input || input.disabled) return;
    let v = parseInt(input.value, 10); if (isNaN(v)) v = 1;
    if (minus) v = Math.max(1, v - 1);
    if (plus)  v = v + 1;
    input.value = v;
  });




  

// Sayfa açılışında mini sepeti getir
updateCartSummary();

// ScrollSpy aktif merkezleme
const scrollSpy = new bootstrap.ScrollSpy(document.body, { target: '#subcategoryNav', offset: 100 });
document.addEventListener('activate.bs.scrollspy', function () {
  const active = document.querySelector('#subcategoryNav .active');
  if (active) active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
});

</script>

<!-- Floating Cart -->
<div id="floatingCart" class="floating-cart">
 <button id="cartToggle" class="btn cart-btn">
  🛒 <span id="cartCount">0</span> • <span id="cartTotal">0.00</span> ₺
 </button>
 <div id="cartPopup" class="cart-popup">
   <div class="cart-popup-content">
     <h6 class="fw-bold mb-2"><?=htmlspecialchars($tx['cart'])?></h6>
     <div id="cartItems"></div>
     <p class="mt-2 mb-2 text-end fw-semibold">Toplam: <span id="popupTotal">0.00 ₺</span></p>
     <div class="mb-2">
  <label for="orderNote" class="form-label small mb-1">Sipariş Notu (isteğe bağlı)</label>
  <textarea id="orderNote" class="form-control form-control-sm" rows="2" placeholder="Örneğin: Az tuzlu olsun, içecekler soğuk..."></textarea>
</div>

     <div class="d-flex justify-content-between mt-2">
       <button id="cartCheckoutBtn" class="btn btn-primary btn-sm"><?=htmlspecialchars($tx['checkout'])?></button>
     </div>
   </div>
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ Sepete ekle
async function addToCart(itemId, optionId, hash, quantity){
  const body=new URLSearchParams({item_id:itemId,option_id:optionId,quantity,hash});
  const res=await fetch('add_to_cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
  if(res.ok){updateCartSummary();}
}

// ✅ Sepetten sil
async function removeFromCart(key) {
  try {
    const res = await fetch('remove_from_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        key: key,
        hash: '<?=urlencode($hash)?>'
      })
    });
    const data = await res.json();
    if (data.status === 'ok') {
      updateCartSummary();
    } else {
      console.error('Silme hatası:', data.message);
    }
  } catch (err) {
    console.error('İstek hatası:', err);
  }
}





// ✅ Mini sepeti güncelle
async function updateCartSummary(){
  const res=await fetch('get_cart_summary.php?hash=<?=$hash?>');
  const data=await res.json();
  const cC=document.getElementById('cartCount'), cT=document.getElementById('cartTotal'),
        pT=document.getElementById('popupTotal'), cI=document.getElementById('cartItems');
  if(data.status==='ok'){
    cC.textContent=data.count; cT.textContent=data.total; pT.textContent=data.total+' ₺';
    cI.innerHTML='';
    if(!data.items.length){cI.innerHTML='<p class="text-muted small mb-0"><?=$tx['emptyCart']?></p>';return;}
    data.items.forEach(it=>{
      const row=document.createElement('div');
      row.className='cart-item-row';
      row.innerHTML=`
        <div class="cart-item-name">${it.name}<br><small class="text-muted">${it.option_name}</small></div>
        <div class="cart-item-qty">${it.qty} × ${it.price} ₺</div>
        <span class="cart-item-remove" data-key="${it.key}">&times;</span>
      `;
      cI.appendChild(row);
    });
  }
}

// ✅ Tüm olayları tek DOMContentLoaded bloğunda birleştirilmiş versiyon
document.addEventListener('DOMContentLoaded', () => {

  /* 🛒 1. Ürün ekleme */
  document.body.addEventListener('click', e => {
    const add = e.target.closest('.add-to-cart');
    if (add) {
      addToCart(add.dataset.itemId, add.dataset.optionId, add.dataset.hash, 1);
      return;
    }
  });

  /* 🧠 2. Dinamik olay yakalayıcı (örneğin remove veya sonradan eklenen öğeler) */
  document.body.addEventListener('click', e => {
    const del = e.target.closest('.cart-item-remove');
    if (del) {
      removeFromCart(del.dataset.key);
      return;
    }

    // Adet +/- butonları
    const minus = e.target.closest('.qty-minus');
    const plus  = e.target.closest('.qty-plus');
    if (minus || plus) {
      e.preventDefault();
      const group = e.target.closest('.qty-group');
      const input = group ? group.querySelector('.qty-input') : null;
      if (!input || input.disabled) return;

      let v = parseInt(input.value, 10);
      if (isNaN(v)) v = 1;
      if (minus) v = Math.max(1, v - 1);
      if (plus) v = v + 1;
      input.value = v;
    }
  });

  /* 🧾 3. Checkout (Siparişi Gönder) */
  const checkoutBtn = document.getElementById('cartCheckoutBtn');
  const cartPopup   = document.getElementById('cartPopup');

  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', async () => {
      if (!confirm(<?= json_encode($tx['checkout'] . '?') ?>)) return;

      const note = document.getElementById('orderNote')?.value || '';
      try {
        const res = await fetch('submit_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            hash: '<?=urlencode($hash)?>',
            note: note
          })
        });

        const data = await res.json().catch(() => ({}));
        if (data.status === 'ok') {
          alert('Siparişiniz alındı!');
          updateCartSummary();
          if (cartPopup) cartPopup.style.display = 'none';
        } else {
          alert(data.message || 'Hata oluştu.');
        }
      } catch (err) {
        console.error(err);
        alert('Bağlantı hatası.');
      }
    });
  }

  /* 💬 4. Floating cart aç/kapa */
  const cartToggle = document.getElementById('cartToggle');
  if (cartToggle && cartPopup) {
    ['click', 'touchend'].forEach(evt => {
      cartToggle.addEventListener(evt, e => {
        e.stopPropagation();
        cartPopup.style.display = (cartPopup.style.display === 'block') ? 'none' : 'block';
      });
    });

    // dışarı tıklayınca kapanma
    document.addEventListener('click', e => {
      if (!e.target.closest('.floating-cart')) {
        cartPopup.style.display = 'none';
      }
    });
  }

  /* 💰 5. İlk yüklemede sepet özeti getir */
  updateCartSummary();
});


</script>
</body>
</html>
