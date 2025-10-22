<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$pageTitle = 'Rol Düzenle';
$restaurantId = $_SESSION['restaurant_id'];
$isAdmin = !empty($_SESSION['is_admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: list.php');
    exit;
}

// mevcut rolü getir
$stmt = $pdo->prepare("SELECT * FROM RestaurantRoles WHERE RoleID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);

$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    die('Rol bulunamadı veya erişim izniniz yok.');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleName = trim($_POST['role_name']);
    $permissions = $_POST['perm'] ?? [];

    $permJson = json_encode([
        'menu'      => in_array('menu', $permissions),
        'orders'    => in_array('orders', $permissions),
        'tables'    => in_array('tables', $permissions),
        'users'     => in_array('users', $permissions),
        'branches'  => in_array('branches', $permissions),
        'roles'     => in_array('roles', $permissions),
    ], JSON_UNESCAPED_UNICODE);

 $update = $pdo->prepare("UPDATE RestaurantRoles SET RoleName=?, Permissions=? WHERE RoleID=? AND RestaurantID=?");
$update->execute([$roleName, $permJson, $id, $restaurantId]);


    header('Location: list.php');
    exit;
}

include __DIR__ . '/../includes/bo_header.php';

$existingPerms = json_decode($role['Permissions'], true) ?? [];
?>

<h4>Rol Düzenle</h4>
<form method="post" class="mt-3">
  <div class="mb-3">
    <label class="form-label">Rol Adı</label>
    <input type="text" name="role_name" class="form-control" required value="<?= htmlspecialchars($role['RoleName']) ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Yetkiler</label>
    <div class="row">
      <?php
      $perms = [
        'menu' => 'Menü Yönetimi',
        'orders' => 'Siparişler',
        'tables' => 'Masalar',
        'users' => 'Kullanıcılar',
        'branches' => 'Şubeler',
        'roles' => 'Rol Tanımları'
      ];
      foreach ($perms as $k => $label):
      ?>
      <div class="col-md-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="perm[]" value="<?= $k ?>" id="perm_<?= $k ?>"
            <?= (!empty($existingPerms[$k])) ? 'checked' : '' ?>>
          <label class="form-check-label" for="perm_<?= $k ?>"><?= $label ?></label>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Güncelle</button>
  <a href="list.php" class="btn btn-secondary">İptal</a>
</form>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
