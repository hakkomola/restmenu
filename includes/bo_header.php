<?php
// includes/bo_header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_login();

$restaurantId   = $_SESSION['restaurant_id'];
$restaurantName = $_SESSION['restaurant_name'] ?? 'Restoran';
$currentBranch  = $_SESSION['current_branch'] ?? null;
$branches_header = $_SESSION['branches'] ?? [];
$isAdmin        = !empty($_SESSION['is_admin']);
$roleName       = $_SESSION['role_name'] ?? 'Kullanıcı';

// aktif link
$currentFile = basename($_SERVER['PHP_SELF']);
$currentURI  = $_SERVER['REQUEST_URI'] ?? '';

// aktif şube adı
$branchName = '(Tüm Şubeler)';
if ($currentBranch) {
  $stmt = $pdo->prepare("SELECT BranchName FROM RestaurantBranches WHERE BranchID=? AND RestaurantID=?");
  $stmt->execute([$currentBranch, $restaurantId]);
  $branchName = $stmt->fetchColumn() ?: $branchName;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>VovMenu Backoffice</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/style.css" rel="stylesheet">
</head>
<body>
<div class="bo-shell">

  <!-- SIDEBAR -->
  <aside class="bo-sidebar">
    <a class="bo-brand" href="/restaurants/dashboard.php">
      <span style="font-size: 1.2rem;">🍽️</span> <span>VovMenu Backoffice</span>
    </a>

    <div class="bo-menu">
      <div class="text-uppercase text-secondary small px-2 mb-2">Genel</div>

      <a href="/restaurants/dashboard.php" class="<?= $currentFile==='dashboard.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
      </a>

      <?php if (can('orders')): ?>
      <a href="/orders/list.php" class="<?= strpos($currentURI, '/orders/')!==false ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> <span>Siparişler</span>
      </a>
      <?php endif; ?>

      <?php if (can('tables')): ?>
      <a href="/restaurants/tables.php" class="<?= $currentFile==='tables.php' ? 'active' : '' ?>">
        <i class="bi bi-grid-3x3-gap"></i> <span>Masalar</span>
      </a>
      <?php endif; ?>

      <?php if (can('menu')): ?>
      <div class="text-uppercase text-secondary small px-2 mt-3 mb-2">Menü Yönetimi</div>

      <a href="/categories/list.php" class="<?= strpos($currentURI, '/categories/')!==false ? 'active' : '' ?>">
        <i class="bi bi-list-nested"></i> <span>Kategoriler</span>
      </a>

      <a href="/subcategories/list.php" class="<?= strpos($currentURI, '/subcategories/')!==false ? 'active' : '' ?>">
        <i class="bi bi-diagram-3"></i> <span>Alt Kategoriler</span>
      </a>

      <a href="/items/list.php" class="<?= strpos($currentURI, '/items/')!==false ? 'active' : '' ?>">
        <i class="bi bi-card-text"></i> <span>Menü Öğeleri</span>
      </a>

      <a href="/restaurants/menu_tree.php" class="<?= $currentFile==='menu_tree.php' ? 'active' : '' ?>">
        <i class="bi bi-lightning-charge"></i> <span>Kolay Menü</span>
      </a>

      <a href="/restaurants/qrlinks.php" class="<?= $currentFile==='qrlinks.php' ? 'active' : '' ?>">
        <i class="bi bi-qr-code"></i> <span>Menü QR Kodları</span>
      </a>
      <?php endif; ?>

      <div class="text-uppercase text-secondary small px-2 mt-3 mb-2">Yönetim</div>

      <?php if (can('branches')): ?>
      <a href="/branches/list.php" class="<?= strpos($currentURI, '/branches/')!==false ? 'active' : '' ?>">
        <i class="bi bi-buildings"></i> <span>Şubeler</span>
      </a>
      <?php endif; ?>

      <?php if (can('users')): ?>
      <a href="/users/list.php" class="<?= strpos($currentURI, '/users/')!==false ? 'active' : '' ?>">
        <i class="bi bi-people"></i> <span>Kullanıcılar</span>
      </a>
      <?php endif; ?>

      <?php if (can('roles')): ?>
      <a href="/roles/list.php" class="<?= strpos($currentURI, '/roles/')!==false ? 'active' : '' ?>">
        <i class="bi bi-shield-lock"></i> <span>Roller</span>
      </a>
      <?php endif; ?>

      <div class="text-uppercase text-secondary small px-2 mt-3 mb-2">Ayarlar</div>

      <a href="/restaurants/profile.php" class="<?= $currentFile==='profile.php' ? 'active' : '' ?>">
        <i class="bi bi-building"></i> <span>Restoran Bilgileri</span>
      </a>

      <a href="/restaurants/change_password.php" class="<?= $currentFile==='change_password.php' ? 'active' : '' ?>">
        <i class="bi bi-lock"></i> <span>Şifre Değiştir</span>
      </a>
    </div>

    <div class="bo-footer">
      <div class="small"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($roleName) ?></div>
      <div class="small text-secondary"><?= htmlspecialchars($restaurantName) ?></div>
      <div class="small text-secondary">Aktif: <strong><?= htmlspecialchars($branchName) ?></strong></div>
      <div class="mt-2">
        <a class="btn btn-sm btn-outline-light w-100" href="/restaurants/logout.php">
          <i class="bi bi-box-arrow-right me-1"></i> Çıkış
        </a>
      </div>
    </div>
  </aside>

  <!-- CONTENT -->
  <div class="bo-content">
    <div class="bo-topbar d-flex justify-content-between align-items-center">
      <!-- 🔹 Hamburger menü -->
      <button class="btn btn-link text-dark d-lg-none" id="sidebarToggle" type="button">
        <i class="bi bi-list" style="font-size: 1.5rem;"></i>
      </button>

      <!-- 🔹 Sayfa başlığı -->
      <div class="fw-semibold flex-grow-1">
        <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Backoffice' ?>
      </div>

      <!-- 🔹 Şube seçici -->
      <?php if (count($branches_header) > 1 || $isAdmin): ?>
        <form method="post" action="/restaurants/select_branch.php" class="d-flex align-items-center m-0">

          <select name="branch_id" class="form-select form-select-sm" style="min-width:180px" onchange="this.form.submit()">
            <?php foreach ($branches_header as $b): ?>
              <option value="<?= $b['BranchID'] ?>" <?= ($b['BranchID']==$currentBranch)?'selected':'' ?>>
                <?= htmlspecialchars($b['BranchName']) ?>
              </option>
            <?php endforeach; ?>

          </select>
        </form>
      <?php endif; ?>
    </div>

    <main class="bo-main">
