<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('branches')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT * FROM RestaurantBranches
    WHERE BranchID = ? AND RestaurantID = ?
");
$stmt->execute([$id, $restaurantId]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    die('Şube bulunamadı veya erişim yetkiniz yok.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['BranchName'] ?? '');
    $address = trim($_POST['Address'] ?? '');
    $phone   = trim($_POST['Phone'] ?? '');
    $mapUrl  = trim($_POST['MapUrl'] ?? '');
    $isActive = isset($_POST['IsActive']) ? 1 : 0;

    if ($name === '') {
        $message = 'Şube adı boş olamaz.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE RestaurantBranches
            SET BranchName=?, Address=?, Phone=?, MapUrl=?, IsActive=?, UpdatedAt=NOW()
            WHERE BranchID=? AND RestaurantID=?
        ");
        $stmt->execute([$name, $address, $phone, $mapUrl, $isActive, $id, $restaurantId]);

        // session’daki branch adını güncelle
        foreach ($_SESSION['branches'] as &$b) {
            if ($b['BranchID'] == $id) {
                $b['BranchName'] = $name;
            }
        }

        header('Location: list.php');
        exit;
    }
}

$pageTitle = "Şube Düzenle";
include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post" class="bo-form">
    <div class="mb-3">
      <label class="form-label">Şube Adı <span class="text-danger">*</span></label>
      <input type="text" name="BranchName" class="form-control" value="<?= htmlspecialchars($branch['BranchName']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Adres</label>
      <textarea name="Address" class="form-control" rows="3"><?= htmlspecialchars($branch['Address'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Telefon</label>
      <input type="text" name="Phone" class="form-control" value="<?= htmlspecialchars($branch['Phone'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Harita URL (Google Maps bağlantısı)</label>
      <input type="url" name="MapUrl" class="form-control" value="<?= htmlspecialchars($branch['MapUrl'] ?? '') ?>">
    </div>

    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="IsActive" id="IsActive" <?= ($branch['IsActive'] ? 'checked' : '') ?>>
      <label class="form-check-label" for="IsActive">Şube aktif</label>
    </div>

    <div class="d-flex justify-content-end">
      <a href="list.php" class="btn btn-outline-secondary me-2">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
