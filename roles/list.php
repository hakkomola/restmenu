<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('roles')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$pageTitle    = "Rol Tanımları";

$stmt = $pdo->prepare("
    SELECT RoleID, RestaurantID, RoleName, Permissions
    FROM RestaurantRoles
    WHERE RestaurantID = ?
    ORDER BY RoleID ASC
");
$stmt->execute([$restaurantId]);
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
  <a href="create.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle"></i> Yeni Rol
  </a>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Rol Adı</th>
        <th>Yetkiler</th>
        <th>Restoran</th>
        <th class="text-end">İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($roles): ?>
        <?php foreach ($roles as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['RoleID']) ?></td>
          <td><?= htmlspecialchars($r['RoleName']) ?></td>

          <!-- Yetkiler -->
          <td>
            <?php
              $perms = json_decode($r['Permissions'], true);
              if ($perms && is_array($perms)) {
                  $keys = [];
                  foreach ($perms as $k => $v) {
                      if ($v) $keys[] = $k;
                  }
                  echo $keys ? htmlspecialchars(implode(', ', $keys)) : '<span class="text-muted">Yok</span>';
              } else {
                  echo '<span class="text-muted">Yok</span>';
              }
            ?>
          </td>

          <!-- Restoran -->
          <td>
            <?php if ($r['RestaurantID']): ?>
              <?= htmlspecialchars($r['RestaurantID']) ?>
            <?php else: ?>
              <span class="text-muted">Genel (Admin)</span>
            <?php endif; ?>
          </td>

          <!-- İşlem -->
          <td class="text-end" style="white-space: nowrap;">
            <a href="edit.php?id=<?= $r['RoleID'] ?>" class="btn-action" title="Düzenle">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="delete.php?id=<?= $r['RoleID'] ?>" class="btn-action" title="Sil"
               onclick="return confirm('Bu rolü silmek istediğinize emin misiniz?')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-4">Hiç rol bulunamadı.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
