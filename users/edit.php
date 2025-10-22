<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$userId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM RestaurantUsers WHERE UserID=? AND RestaurantID=?");
$stmt->execute([$userId, $restaurantId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die('Kullanıcı bulunamadı.');

$message = '';

// Şubeler, roller, mevcut yetkiler
$stmtB = $pdo->prepare("SELECT BranchID, BranchName FROM RestaurantBranches WHERE RestaurantID=? ORDER BY BranchName ASC");
$stmtB->execute([$restaurantId]);
$branches = $stmtB->fetchAll(PDO::FETCH_ASSOC);

$stmtR = $pdo->prepare("SELECT RoleID, RoleName FROM RestaurantRoles WHERE RestaurantID=? ORDER BY RoleName ASC");
$stmtR->execute([$restaurantId]);
$roles = $stmtR->fetchAll(PDO::FETCH_ASSOC);

$stmtUB = $pdo->prepare("SELECT BranchID FROM RestaurantBranchUsers WHERE UserID=?");
$stmtUB->execute([$userId]);
$userBranches = $stmtUB->fetchAll(PDO::FETCH_COLUMN);

$stmtUR = $pdo->prepare("SELECT RoleID FROM RestaurantUserRoles WHERE UserID=?");
$stmtUR->execute([$userId]);
$userRoles = $stmtUR->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['FullName'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $password = trim($_POST['Password'] ?? '');
    $isActive = isset($_POST['IsActive']) ? 1 : 0;
    $branchIds = $_POST['Branches'] ?? [];
    $roleIds = $_POST['Roles'] ?? [];

    if ($fullName === '' || $email === '') {
        $message = 'Lütfen gerekli alanları doldurun.';
    } else {
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE RestaurantUsers SET FullName=?, Email=?, PasswordHash=?, IsActive=?
                WHERE UserID=? AND RestaurantID=?
            ");
            $stmt->execute([$fullName, $email, $hash, $isActive, $userId, $restaurantId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE RestaurantUsers SET FullName=?, Email=?, IsActive=?
                WHERE UserID=? AND RestaurantID=?
            ");
            $stmt->execute([$fullName, $email, $isActive, $userId, $restaurantId]);
        }

        // Şubeleri güncelle
        $pdo->prepare("DELETE FROM RestaurantBranchUsers WHERE UserID=?")->execute([$userId]);
        if ($branchIds) {
            $ins = $pdo->prepare("INSERT INTO RestaurantBranchUsers (BranchID, UserID) VALUES (?, ?)");
            foreach ($branchIds as $bid) $ins->execute([$bid, $userId]);
        }

        // Rolleri güncelle
        $pdo->prepare("DELETE FROM RestaurantUserRoles WHERE UserID=?")->execute([$userId]);
        if ($roleIds) {
            $ins = $pdo->prepare("INSERT INTO RestaurantUserRoles (UserID, RoleID) VALUES (?, ?)");
            foreach ($roleIds as $rid) $ins->execute([$userId, $rid]);
        }

        header('Location: list.php');
        exit;
    }
}

$pageTitle = "Kullanıcıyı Düzenle";
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
      <input type="text" name="FullName" class="form-control" value="<?= htmlspecialchars($user['FullName']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">E-posta <span class="text-danger">*</span></label>
      <input type="email" name="Email" class="form-control" value="<?= htmlspecialchars($user['Email']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Yeni Şifre (boş bırakırsanız değişmez)</label>
      <input type="password" name="Password" class="form-control">
    </div>

    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" <?= $user['IsActive'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="IsActive">Kullanıcı aktif</label>
    </div>

    <?php if ($roles): ?>
    <div class="mb-3">
      <label class="form-label">Roller</label>
      <div class="border rounded p-3">
        <?php foreach ($roles as $r): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="Roles[]" value="<?= $r['RoleID'] ?>"
              id="role<?= $r['RoleID'] ?>" <?= in_array($r['RoleID'], $userRoles) ? 'checked' : '' ?>>
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
            <input class="form-check-input" type="checkbox" name="Branches[]" value="<?= $b['BranchID'] ?>"
              id="branch<?= $b['BranchID'] ?>" <?= in_array($b['BranchID'], $userBranches) ? 'checked' : '' ?>>
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
      <button type="submit" class="btn btn-primary">Güncelle</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
