<?php
// menu_order.php
session_start();
require_once __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =====================
   GİRİŞ PARAMETRELERİ
===================== */
$hash  = $_GET['hash'] ?? null;
$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : null;
$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang'] ?? 'tr';

if (!$hash) die('Geçersiz bağlantı');

/* =====================
   SABİT
===================== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

/* =====================
   HASH → RESTORAN & MASA ÇÖZ
===================== */
function resolve_table(PDO $pdo, string $hash) {
    $rows = $pdo->query("SELECT RestaurantID, BranchID, Code, Name, IsActive FROM RestaurantTables")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $branchId = (int)($r['BranchID'] ?? 0);
        $calc = substr(hash('sha256', $r['RestaurantID'].'|'.$branchId.'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 32);
        if (hash_equals($calc, $hash)) {
            return $r;
        }
    }
    return null;
}

$table = resolve_table($pdo, $hash);
if (!$table || !$table['IsActive']) die('Masa bulunamadı veya pasif.');

/* =====================
   RESTORAN & ŞUBE BİLGİLERİ
===================== */
$restaurantId = (int)$table['RestaurantID'];
$branchId     = (int)($table['BranchID'] ?? 0);
$tableName    = $table['Name'];

// Restoran bilgisi
$stmt = $pdo->prepare("SELECT RestaurantID, Name, OrderUse FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) die('Restoran bulunamadı.');

// OrderUse kontrolü
if (strtoupper($restaurant['OrderUse']) === 'N') {
    die('Restoranın sipariş yetkisi yok.');
}

$restaurantName = $restaurant['Name'] ?? 'Restoran';

/* =====================
   ÇEVİRİ METİNLERİ
===================== */
$tx = [
    'home' => $lang === 'tr' ? 'Anasayfa' : 'Home',
    'add' => $lang === 'tr' ? 'Sepete Ekle' : 'Add',
    'more' => $lang === 'tr' ? 'Seçenekler' : 'More',
    'cart' => $lang === 'tr' ? 'Sepet' : 'Cart',
    'checkout' => $lang === 'tr' ? 'Gönder' : 'Checkout'
];

/* =====================
   DİL DESTEĞİ
===================== */
$supportedLangs = $pdo->prepare("
    SELECT L.LangCode, L.LangName 
    FROM Languages L
    INNER JOIN RestaurantLanguages RL ON RL.LangCode=L.LangCode
    WHERE RL.RestaurantID=? AND L.IsActive=1
    ORDER BY RL.IsDefault DESC, L.SortOrder
");
$supportedLangs->execute([$restaurantId]);
$supportedLangs = $supportedLangs->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   KATEGORİLER & ÜRÜNLER
===================== */
if (!$catId) {
    // Ana sayfa: sadece kategoriler (şube filtreli)
    $stmt = $pdo->prepare("
        SELECT c.CategoryID, 
               COALESCE(t.Name, c.CategoryName) AS CategoryNameDisp,
               c.ImageURL
        FROM MenuCategories c
        LEFT JOIN MenuCategoryTranslations t 
          ON t.CategoryID=c.CategoryID AND t.LangCode=:lang
        WHERE c.RestaurantID=:rid
          AND (c.BranchID=:bid)
        ORDER BY c.SortOrder, c.CategoryID
    ");
    $stmt->execute([':lang' => $lang, ':rid' => $restaurantId, ':bid' => $branchId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $category = null;
    $subcategories = [];
    $itemsBySub = [];
} else {
    // Seçili kategori (şube filtreli)
    $catStmt = $pdo->prepare("
        SELECT c.CategoryID, COALESCE(t.Name, c.CategoryName) AS CategoryNameDisp
        FROM MenuCategories c
        LEFT JOIN MenuCategoryTranslations t 
          ON t.CategoryID=c.CategoryID AND t.LangCode=:lang
        WHERE c.CategoryID=:cid AND (c.BranchID=:bid)
    ");
    $catStmt->execute([':lang'=>$lang, ':cid'=>$catId, ':bid'=>$branchId]);
    $category = $catStmt->fetch(PDO::FETCH_ASSOC);

    /* ==== Alt Kategoriler ==== */
    $subStmt = $pdo->prepare("
        SELECT s.SubCategoryID, COALESCE(t.Name, s.SubCategoryName) AS SubCategoryNameDisp
        FROM SubCategories s
        LEFT JOIN SubCategoryTranslations t 
          ON t.SubCategoryID=s.SubCategoryID AND t.LangCode=:lang
        WHERE s.RestaurantID=:rid 
          AND s.CategoryID=:cid
          AND (s.BranchID=:bid)
        ORDER BY s.SortOrder, s.SubCategoryID
    ");
    $subStmt->execute([':lang'=>$lang, ':rid'=>$restaurantId, ':cid'=>$catId, ':bid'=>$branchId]);
    $subcategories = $subStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==== Ürünler ==== */
    // DİKKAT: Aynı named placeholder iki kez kullanılamaz → :bid_sub ve :bid_item
    $itemStmt = $pdo->prepare("
        SELECT i.MenuItemID, i.SubCategoryID,
               COALESCE(mt.Name, i.MenuName) AS MenuNameDisp,
               COALESCE(mt.Description, i.Description) AS DescriptionDisp
        FROM MenuItems i
        LEFT JOIN MenuItemTranslations mt 
          ON mt.MenuItemID=i.MenuItemID AND mt.LangCode=:lang
        WHERE i.RestaurantID=:rid 
          AND i.SubCategoryID IN (
              SELECT SubCategoryID FROM SubCategories 
              WHERE CategoryID=:cid AND (BranchID=:bid_sub)
          )
          AND (i.BranchID=:bid_item)
        ORDER BY i.SortOrder, i.MenuItemID
    ");
    $itemStmt->execute([
        ':lang'=>$lang,
        ':rid'=>$restaurantId,
        ':cid'=>$catId,
        ':bid_sub'=>$branchId,
        ':bid_item'=>$branchId
    ]);
    $allItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==== Ürün Opsiyonları ve Görselleri ==== */
    $optStmt = $pdo->prepare("
        SELECT o.MenuItemID, o.OptionID, COALESCE(ot.Name, o.OptionName) AS OptionNameDisp,
               o.Price, o.IsDefault
        FROM MenuItemOptions o
        LEFT JOIN MenuItemOptionTranslations ot 
          ON ot.OptionID=o.OptionID AND ot.LangCode=:lang
        WHERE o.MenuItemID=:iid
        ORDER BY o.SortOrder, o.OptionID
    ");
    $imgStmt = $pdo->prepare("
        SELECT ImageURL 
        FROM MenuImages 
        WHERE MenuItemID=:iid 
        ORDER BY MenuImageID
    ");

    $itemsBySub = [];
    foreach ($allItems as &$item) {
        $optStmt->execute([':lang'=>$lang, ':iid'=>$item['MenuItemID']]);
        $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

        $imgStmt->execute([':iid'=>$item['MenuItemID']]);
        $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

        // default & price tespiti
        $item['images'] = $images;
        $item['OptionsCount'] = count($options);
        $item['DefaultOptionID'] = null;
        $item['DefaultPrice'] = null;
        $item['SingleOptionID'] = null;
        $item['SingleOptionPrice'] = null;

        if (count($options) === 1) {
            $item['SingleOptionID'] = $options[0]['OptionID'];
            $item['SingleOptionPrice'] = $options[0]['Price'];
        }

        foreach ($options as $opt) {
            if ($opt['IsDefault']) {
                $item['DefaultOptionID'] = $opt['OptionID'];
                $item['DefaultPrice'] = $opt['Price'];
            }
        }

        $itemsBySub[$item['SubCategoryID']][] = $item;
    }
}

/* =====================
   SEPET TOPLAMI
===================== */
$total = 0;
if (!empty($_SESSION['cart'][$hash])) {
    foreach ($_SESSION['cart'][$hash] as $item) {
        $total += $item['qty'] * $item['price'];
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($restaurantName) ?> Menü</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Ibarra+Real+Nova:wght@400;600;700&display=swap" rel="stylesheet">
<link href="assets/menu.css" rel="stylesheet">

<?php // CSS sonraki adımda eklenecek ?>
</head>

<body class="menu-body" data-theme="<?= htmlspecialchars($theme) ?>" data-bs-spy="scroll" data-bs-target="#subcategoryNav" data-bs-offset="100" tabindex="0">

<div class="container py-4">




  <!-- 🏷️ ANA SAYFA / KATEGORİLER -->
  <?php if (!$catId): ?>
    <h1 class="mb-4 text-center"><?= htmlspecialchars($restaurantName) ?></h1>

    <div class="row g-4 category-grid">
      <?php foreach ($categories as $cat): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a href="?hash=<?= htmlspecialchars($hash) ?>&cat=<?= (int)$cat['CategoryID'] ?>&theme=<?= htmlspecialchars($theme) ?>&lang=<?= htmlspecialchars($lang) ?>"
             class="text-decoration-none <?= $theme==='dark'?'text-light':'text-dark' ?>">
            <div class="card h-100 text-center">
              <?php if (!empty($cat['ImageURL'])): ?>
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
    <!-- 📂 KATEGORİ SAYFASI -->
    <div class="page-header text-center mb-4">
      <h1><?= htmlspecialchars($restaurantName) ?></h1>
      <h3><?= htmlspecialchars($category['CategoryNameDisp'] ?? '') ?></h3>
    </div>

    <?php if (!empty($subcategories)): ?>
      <div id="subcategoryNav" class="subcategory-menu mb-3">

<?php foreach ($subcategories as $sub): ?>
  <a href="#sub<?= (int)$sub['SubCategoryID'] ?>" class="btn">
    <?= htmlspecialchars($sub['SubCategoryNameDisp']) ?>
  </a>
<?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php $addBtnColorClass = ($theme==='dark') ? 'btn-outline-warning' : 'btn-primary'; ?>

    <?php foreach ($subcategories as $sub): ?>
      <section id="sub<?= (int)$sub['SubCategoryID'] ?>" class="mt-4">
        <h4 class="mb-3"><?= htmlspecialchars($sub['SubCategoryNameDisp']) ?></h4>
        <div class="row g-4">
          <?php foreach (($itemsBySub[$sub['SubCategoryID']] ?? []) as $item): ?>
            <?php
              $optionsCount    = (int)($item['OptionsCount'] ?? 0);
              $defaultOptionID = $item['DefaultOptionID'] ?? null;
              $defaultPrice    = $item['DefaultPrice'] ?? null;
              $singleOptionID  = $item['SingleOptionID'] ?? null;
              $singlePrice     = $item['SingleOptionPrice'] ?? null;

              $quickOptionId = $defaultOptionID ?: ($singleOptionID ?: null);
              $displayPrice = $defaultPrice ?? $singlePrice;

              $detailUrl = 'menu_order_item.php?id='.(int)$item['MenuItemID']
    .'&hash='.urlencode($hash)
    .'&theme='.urlencode($theme)
    .'&lang='.urlencode($lang)
    .'&from='.urlencode($_SERVER['REQUEST_URI']);

            ?>

            
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card h-100 d-flex flex-column">
                <!-- 📸 Görsel -->
                <?php if (!empty($item['images'])): ?>
                  <div id="carousel<?= (int)$item['MenuItemID'] ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                      <?php foreach ($item['images'] as $i => $img): ?>
                        <div class="carousel-item <?= $i===0?'active':'' ?>">
                          <img src="<?= htmlspecialchars($img['ImageURL']) ?>" class="d-block w-100 menu-img" alt="Menü Görseli">
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <?php if (count($item['images']) > 1): ?>
                      <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?= (int)$item['MenuItemID'] ?>" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                      </button>
                      <button class="carousel-control-next" type="button" data-bs-target="#carousel<?= (int)$item['MenuItemID'] ?>" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                      </button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="card-body d-flex flex-column">
                  <h5 class="card-title mb-1"><?= htmlspecialchars($item['MenuNameDisp']) ?></h5>
                  <?php if (!empty($item['DescriptionDisp'])): ?>
                    <p class="card-text mb-2"><?= htmlspecialchars($item['DescriptionDisp']) ?></p>
                  <?php endif; ?>

                  <?php if ($displayPrice): ?>
                    <p class="price mb-2"><?= number_format((float)$displayPrice, 2) ?> ₺</p>
                  <?php endif; ?>

                  <div class="qty-group mb-2" data-item="<?= (int)$item['MenuItemID'] ?>">
                    <button class="btn btn-outline-secondary btn-sm qty-minus" type="button" <?= $quickOptionId?'':'disabled' ?>>−</button>
                    <input type="number" class="form-control form-control-sm qty-input" value="1" min="1" <?= $quickOptionId?'':'disabled' ?> />
                    <button class="btn btn-outline-secondary btn-sm qty-plus" type="button" <?= $quickOptionId?'':'disabled' ?>>+</button>
                  </div>

<?php
// 🔹 Buton yapısı - tek/çoklu opsiyon ayrımı
$hasMultipleOptions = ($optionsCount > 1);
?>
<div class="mt-auto d-flex gap-2">
  <?php if ($quickOptionId): ?>
    <button class="btn <?= $addBtnColorClass ?> btn-sm <?= $hasMultipleOptions ? 'flex-grow-1' : 'w-100' ?> add-to-cart"
            data-item-id="<?= (int)$item['MenuItemID'] ?>"
            data-option-id="<?= (int)$quickOptionId ?>"
            data-hash="<?= htmlspecialchars($hash) ?>">
      <?= htmlspecialchars($tx['add']) ?>
    </button>
  <?php else: ?>
    <a class="btn btn-secondary btn-sm <?= $hasMultipleOptions ? 'flex-grow-1' : 'w-100' ?> disabled"><?= htmlspecialchars($tx['add']) ?></a>
  <?php endif; ?>

  <?php if ($hasMultipleOptions): ?>
    <a href="<?= $detailUrl ?>"
       class="btn <?= $theme==='dark'?'btn-outline-light':'btn-outline-secondary' ?> btn-sm">
       <?= htmlspecialchars($tx['more']) ?>
    </a>
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


<!-- Toast alanı -->
<div class="toast-container" id="toastContainer"></div>

<!-- Alt sabit sepet barı -->
<?php if (!empty($catId)): ?>
  <!-- Alt sabit sepet barı -->
  <div class="vov-cart-bar">
    <div class="cart-bar-inner container d-flex justify-content-between align-items-center">
      <div class="cart-bar-left">
        🛒 <strong>Sepet Toplamı:</strong> 
        <span id="cartTotalBar">₺<?= number_format($total ?? 0, 2, ',', '.') ?></span>
  
<!-- 🔻 Yeni alt sabit sepet bar -->
<div class="vov-cart-bar">
  <div class="cart-bar-inner container d-flex justify-content-center align-items-center gap-2 flex-wrap">
    <a href="menu_order.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>"
       class="btn btn-outline-secondary btn-sm">
       🍽️ Ana Menü
    </a>

    <a href="menu_cart.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>&from=<?= $_SERVER['REQUEST_URI'] ?>"
       class="btn btn-outline-success btn-sm" id="cartButtonBar">
       🛒 Sepet (₺<?= number_format($total ?? 0, 2, ',', '.') ?>)
    </a>

    <a href="orders.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>&from=<?= $_SERVER['REQUEST_URI'] ?>"
       class="btn btn-outline-primary btn-sm">
       📋 Siparişlerim
    </a>
  </div>
</div>



  </div>
<?php endif; ?>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// ===========================
//  BASİT TOAST MESAJLARI
// ===========================
function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  const id = 't' + Math.random().toString(36).slice(2);
  const html = `
  <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
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

// ===========================
//  SEPET BUTONU GÜNCELLEME
// ===========================
function vovUpdateCartDisplay(totalTry) {
  const n = Number(totalTry || 0);
  const formatted = n
    .toFixed(2)
    .replace('.', ',')
    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  // 🔹 eski sepet butonu varsa (artık olmayabilir)
  const btn = document.getElementById('vovCartButton');
  if (btn) {
    btn.textContent = `🛒 <?= htmlspecialchars($tx['cart']) ?> (₺${formatted})`;
  }

// 🔹 Sepet butonundaki toplamı güncelle
const cartBtn = document.getElementById('cartButtonBar');
if (cartBtn) {
  cartBtn.innerHTML = `🛒 Sepet (₺${formatted})`;
}

}


// ===========================
//  ADETLERİ ARTIR / AZALT
// ===========================
document.addEventListener('click', (e) => {
  if (e.target.closest('.qty-plus')) {
    const input = e.target.closest('.qty-group').querySelector('.qty-input');
    input.value = Math.max(1, parseInt(input.value || '1') + 1);
  }
  if (e.target.closest('.qty-minus')) {
    const input = e.target.closest('.qty-group').querySelector('.qty-input');
    input.value = Math.max(1, parseInt(input.value || '1') - 1);
  }
});

// ===========================
//  SEPETE EKLEME
// ===========================
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.add-to-cart');
  if (!btn) return;

  const itemId   = btn.getAttribute('data-item-id');
  const optionId = btn.getAttribute('data-option-id');
  const hash     = btn.getAttribute('data-hash');
  const qtyInput = btn.closest('.card').querySelector('.qty-input');
  const qty      = qtyInput ? Math.max(1, parseInt(qtyInput.value || '1')) : 1;

  try {
    const form = new FormData();
    form.append('hash', hash);
    form.append('itemId', itemId);
    form.append('optionId', optionId);
    form.append('qty', qty);

    const res = await fetch('add_to_cart.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data && data.status === 'ok') {
      vovUpdateCartDisplay(Number(data.total || 0));
      showToast('Sepete eklendi');
    } else {
      showToast(data.message || 'Bir hata oluştu', 'danger');
    }
  } catch (err) {
    showToast('Bağlantı hatası', 'danger');
  }
});
// TRY biçimlendirici
function formatTRY(n) {
  const v = Number(n || 0);
  return v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Sepet UI güncelle (buton + alt bar birlikte)
function updateCartUI(total) {
  // 1) (Varsa) eski üst/sağ buton
  const btn = document.getElementById('vovCartButton');
  if (btn) {
    btn.innerHTML = `🛒 Sepet (₺${formatTRY(total)})`;
  }
  // 2) Alt bar
  const bar = document.getElementById('vovCartBar');
  if (bar) {
    const span = document.getElementById('cartTotalBar');
    if (span) span.textContent = `₺${formatTRY(total)}`;
  }
}
document.addEventListener("DOMContentLoaded", function() {
  // topbar yüksekliğini al
  const topbar = document.querySelector('.topbar');
  const offsetValue = topbar ? topbar.offsetHeight + 10 : 80; // 10px ek tampon
  
  // ScrollSpy'ı başlat
  const scrollSpy = new bootstrap.ScrollSpy(document.body, {
    target: '#subcategoryNav',
    offset: offsetValue
  });

  // aktif linki görünür alanda tut
  document.addEventListener('activate.bs.scrollspy', function () {
    const active = document.querySelector('#subcategoryNav .active');
    if (active) {
      active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
  });
});

// Sayfa tarayıcı geçmişinden geri gelince otomatik yenile
window.addEventListener("pageshow", function (event) {
  if (event.persisted || performance.getEntriesByType("navigation")[0]?.type === "back_forward") {
    window.location.reload();
  }
});

</script>

</body>
</html>
