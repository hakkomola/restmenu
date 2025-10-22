<?php
session_start();
if (!isset($_SESSION['last_page'][$_GET['hash'] ?? ''])) {
    $_SESSION['last_page'][$_GET['hash'] ?? ''] = $_SERVER['HTTP_REFERER'] ?? '';
}

require_once __DIR__ . '/db.php';

$theme  = $_GET['theme'] ?? 'light';
$lang   = $_GET['lang'] ?? 'tr';
$hash   = $_GET['hash'] ?? null;
$branch = $_GET['branch'] ?? null; // 🔹 şube desteği
if (!$hash) die('Geçersiz bağlantı!');

/* ===== HASH çözümü ===== */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Masa tespiti
    $q = $pdo->query("SELECT RestaurantID, BranchID, Code, Name FROM RestaurantTables");
    $table = null;
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $calc = substr(hash('sha256', $r['RestaurantID'].'|'.(int)$r['BranchID'].'|'.$r['Code'].'|'.RESTMENU_HASH_PEPPER), 0, 32);
        if (hash_equals($calc, $hash)) {
            $table = $r;
            break;
        }
    }
    if (!$table) die('Geçersiz masa!');

    $rid     = (int)$table['RestaurantID'];
    $branch  = (int)$table['BranchID'];
    $code    = $table['Code'];

    // Siparişleri çek
    $stmt = $pdo->prepare("
        SELECT o.*, ost.Name AS OrderStatusName
        FROM Orders o
        LEFT JOIN OrderStatusTranslations ost
               ON ost.StatusID = o.StatusID AND ost.LangCode = :lang
        WHERE o.RestaurantID = :rid
          AND o.OrderCode = :code
          AND DATE(o.CreatedAt) = CURDATE()
        ORDER BY o.CreatedAt DESC, o.OrderID DESC
    ");
    $stmt->execute([':lang' => $lang, ':rid' => $rid, ':code' => $code]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $ex) {
    die('Hata: '.$ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Siparişlerim</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/menu.css" rel="stylesheet">
</head>
<body class="menu-body" data-theme="<?= htmlspecialchars($theme) ?>">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">🧾 Siparişlerim</h4>
<?php
// 🔹 Menüye Dön linkini oluştur (cat ve branch varsa ekle)
$catPart    = isset($_GET['cat']) ? '&cat='.(int)$_GET['cat'] : '';
$branchPart = $branch ? '&branch='.(int)$branch : '';
$backUrl = "menu_order.php?hash=" . urlencode($_GET['hash'] ?? '') .
            "&theme=" . urlencode($_GET['theme'] ?? 'light') .
            "&lang=" . urlencode($_GET['lang'] ?? 'tr') .
            $branchPart . $catPart;
?>
<a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary btn-sm">
  Menüye Dön
</a>
  </div>

  <?php if (empty($orders)): ?>
    <div class="alert alert-info">Henüz bu masa için bir sipariş bulunamadı.</div>
  <?php else: ?>
    <?php
    // Her siparişin kalemleri
    $stmtItems = $pdo->prepare("
        SELECT
          oi.OrderItemID, oi.OptionID, oi.Quantity, oi.BasePrice, oi.TotalPrice, oi.StatusID,
          mo.OptionName, mo.Price AS OptionPrice,
          mi.MenuName,
          ost.Name AS ItemStatusName
        FROM OrderItems oi
        JOIN MenuItemOptions mo ON mo.OptionID = oi.OptionID
        JOIN MenuItems mi ON mi.MenuItemID = mo.MenuItemID
        LEFT JOIN OrderStatusTranslations ost
               ON ost.StatusID = oi.StatusID AND ost.LangCode = :lang
        WHERE oi.OrderID = :oid
        ORDER BY oi.OrderItemID ASC
    ");
    ?>

    <?php foreach ($orders as $order): ?>
      <?php
        $stmtItems->execute([':lang' => $lang, ':oid' => $order['OrderID']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="card mb-4 shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center"
             style="background:#198754; color:#fff;">
          <div>
            <span class="fw-semibold">Sipariş #<?= (int)$order['OrderID'] ?></span>
            <small class="ms-2 badge bg-light text-dark">
              <?= htmlspecialchars($order['OrderStatusName'] ?? 'Bekliyor') ?>
            </small>
          </div>
          <small><?= date('d.m.Y H:i', strtotime($order['CreatedAt'])) ?></small>
        </div>

        <div class="card-body p-3">
          <?php if (empty($items)): ?>
            <div class="text-muted small">Bu siparişin kalemleri bulunamadı.</div>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($it['MenuName']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($it['OptionName']) ?></small>
                </div>
                <div class="text-end">
                  <div>
                    <span class="badge bg-secondary"><?= (int)$it['Quantity'] ?>x</span>
                    <span class="fw-semibold"><?= number_format((float)$it['BasePrice'], 2) ?> ₺</span>
                  </div>
                  <small class="text-muted">
                    <?= htmlspecialchars($it['ItemStatusName'] ?? 'Bekliyor') ?>
                  </small>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="text mt-3 d-flex justify-content-between align-items-start">
              <?php if (!empty($order['Note'])): ?>
                <div class="alert alert-warning py-2 px-3 mb-0 flex-grow-1 me-3" style="max-width:70%;">
                  <strong>📝 Not:</strong> <?= nl2br(htmlspecialchars($order['Note'])) ?>
                </div>
              <?php endif; ?>
              <div class="text-end flex-shrink-0">
                <strong>Toplam: ₺<?= number_format((float)$order['TotalPrice'], 2, ',', '.') ?></strong>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</body>
</html>
