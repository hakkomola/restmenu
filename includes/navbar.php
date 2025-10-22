<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_login();

$currentBranch = $_SESSION['current_branch'] ?? null;
$branches = $_SESSION['branches'] ?? [];
$isAdmin = !empty($_SESSION['is_admin']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container-fluid px-3 px-md-4">
    <a class="navbar-brand fw-semibold text-primary" href="../restaurants/dashboard.php">
        🍽️ VovMenu
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="../restaurants/dashboard.php"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
        <li class="nav-item"><a class="nav-link" href="../categories/list.php"><i class="bi bi-list-nested me-1"></i>Kategoriler</a></li>
        <li class="nav-item"><a class="nav-link" href="../subcategories/list.php"><i class="bi bi-diagram-3 me-1"></i>Alt Kategoriler</a></li>
        <li class="nav-item"><a class="nav-link" href="../items/list.php"><i class="bi bi-card-text me-1"></i>Menü Öğeleri</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/menu_tree.php"><i class="bi bi-lightning-charge me-1"></i>Kolay Menü</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/profile.php"><i class="bi bi-building me-1"></i>Restoran Bilgileri</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/tables.php"><i class="bi bi-grid-3x3-gap me-1"></i>Masalar</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/change_password.php"><i class="bi bi-lock me-1"></i>Şifre Değiştir</a></li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <?php if (count($branches) > 1 || $isAdmin): ?>
          <form method="post" action="../restaurants/select_branch.php" class="m-0">
            <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;min-width:160px;">
              <?php foreach ($branches as $b): ?>
                <option value="<?= $b['BranchID'] ?>"
                  <?= ($b['BranchID'] == $currentBranch) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['BranchName']) ?>
                </option>
              <?php endforeach; ?>
              <?php if ($isAdmin): ?>
                <option value="0" <?= $currentBranch ? '' : 'selected' ?>>(Tüm Şubeler)</option>
              <?php endif; ?>
            </select>
          </form>
        <?php endif; ?>

        <span class="navbar-text text-dark small">
          <i class="bi bi-person-circle me-1"></i>
          <?= htmlspecialchars($_SESSION['role_name'] ?? 'Kullanıcı') ?>
        </span>

        <a href="../restaurants/logout.php" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-box-arrow-right me-1"></i> Çıkış
        </a>
      </div>
    </div>
  </div>
</nav>
