<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

// Şubeler ve roller
$stmtB = $pdo->prepare("SELECT BranchID, BranchName FROM RestaurantBranches WHERE RestaurantID=? ORDER BY BranchName ASC");
$stmtB->execute([$restaurantId]);
$branches = $stmtB->fetchAll(PDO::FETCH_ASSOC);

$stmtR = $pdo->prepare("SELECT RoleID, RoleName FROM RestaurantRoles WHERE RestaurantID=? ORDER BY RoleName ASC");
$stmtR->execute([$restaurantId]);
$roles = $stmtR->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['FullName'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $password = trim($_POST['Password'] ?? '');
    $isActive = isset($_POST['IsActive']) ? 1 : 0;
    $branchIds = $_POST['Branches'] ?? [];
    $roleIds = $_POST['Roles'] ?? [];

    if ($fullName === '' || $email === '' || $password === '') {
        $message = 'Lütfen tüm gerekli alanları doldurun.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO RestaurantUsers (RestaurantID, FullName, Email, PasswordHash, IsActive)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$restaurantId, $fullName, $email, $hash, $isActive]);
        $userId = $pdo->lastInsertId();

        // Şube yetkileri
        if ($branchIds) {
            $insB = $pdo->prepare("INSERT INTO RestaurantBranchUsers (BranchID, UserID) VALUES (?, ?)");
            foreach ($branchIds as $bid) $insB->execute([$bid, $userId]);
        }

        // Roller
        if ($roleIds) {
            $insR = $pdo->prepare("INSERT INTO RestaurantUserRoles (UserID, RoleID) VALUES (?, ?)");
            foreach ($roleIds as $rid) $insR->execute([$userId, $rid]);
        }

        header('Location: list.php');
        exit;
    }
}

$pageTitle = "Yeni Kullanıcı Oluştur";
include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post" class="bo-form">
    <div class="mb-3">
      <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
      <input type="text" name="FullName" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">E-posta <span class="text-danger">*</span></label>
      <input type="email" name="Email" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Şifre <span class="text-danger">*</span></label>
      <input type="password" name="Password" class="form-control" required>
    </div>

    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" checked>
      <label class="form-check-label" for="IsActive">Kullanıcı aktif</label>
    </div>

    <?php if ($roles): ?>
    <div class="mb-3">
      <label class="form-label">Roller</label>
      <div class="border rounded p-3">
        <?php foreach ($roles as $r): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="Roles[]" value="<?= $r['RoleID'] ?>" id="role<?= $r['RoleID'] ?>">
            <label class="form-check-label" for="role<?= $r['RoleID'] ?>">
              <?= htmlspecialchars($r['RoleName']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($branches): ?>
    <div class="mb-3">
      <label class="form-label">Şube Yetkileri</label>
      <div class="border rounded p-3">
        <?php foreach ($branches as $b): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="Branches[]" value="<?= $b['BranchID'] ?>" id="branch<?= $b['BranchID'] ?>">
            <label class="form-check-label" for="branch<?= $b['BranchID'] ?>">
              <?= htmlspecialchars($b['BranchName']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end">
      <a href="list.php" class="btn btn-outline-secondary me-2">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
