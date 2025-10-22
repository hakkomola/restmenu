<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$pageTitle = "Kullanıcılar";

// Kullanıcıları çek
$stmt = $pdo->prepare("
  SELECT UserID, FullName, Email, IsActive, CreatedAt, UpdatedAt
  FROM RestaurantUsers
  WHERE RestaurantID = ?
  ORDER BY UserID ASC
");
$stmt->execute([$restaurantId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Şube ve rol bilgilerini iliştir
foreach ($users as &$u) {
    // Şubeler
    $stmtB = $pdo->prepare("
      SELECT rb.BranchName
      FROM RestaurantBranchUsers rbu
      JOIN RestaurantBranches rb ON rb.BranchID = rbu.BranchID
      WHERE rbu.UserID = ? AND rb.RestaurantID = ?
      ORDER BY rb.BranchName
    ");
    $stmtB->execute([$u['UserID'], $restaurantId]);
    $branches = $stmtB->fetchAll(PDO::FETCH_COLUMN);
    $u['Branches'] = $branches ? implode(', ', $branches) : '-';

    // Roller
    $stmtR = $pdo->prepare("
      SELECT rr.RoleName
      FROM RestaurantUserRoles rur
      JOIN RestaurantRoles rr ON rr.RoleID = rur.RoleID
      WHERE rur.UserID = ? AND rr.RestaurantID = ?
      ORDER BY rr.RoleName
    ");
    $stmtR->execute([$u['UserID'], $restaurantId]);
    $roles = $stmtR->fetchAll(PDO::FETCH_COLUMN);
    $u['Roles'] = $roles ? implode(', ', $roles) : '-';
}
unset($u);

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
  <a href="create.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle"></i> Yeni Kullanıcı
  </a>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Ad Soyad</th>
        <th>E-posta</th>
        <th>Roller</th>
        <th>Şubeler</th>
        <th>Durum</th>
        <th>Oluşturulma</th>
        <th>Güncellendi</th>
        <th class="text-end">İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($users): ?>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['UserID'] ?></td>
            <td><?= htmlspecialchars($u['FullName']) ?></td>
            <td><?= htmlspecialchars($u['Email']) ?></td>
            <td><?= htmlspecialchars($u['Roles']) ?></td>
            <td><?= htmlspecialchars($u['Branches']) ?></td>
            <td>
              <?php if ($u['IsActive']): ?>
                <span class="badge bg-success">Aktif</span>
              <?php else: ?>
                <span class="badge bg-secondary">Pasif</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($u['CreatedAt']) ?></td>
            <td><?= htmlspecialchars($u['UpdatedAt']) ?></td>
            <td class="text-end" style="white-space: nowrap;">
              <a href="edit.php?id=<?= $u['UserID'] ?>" class="btn-action" title="Düzenle"><i class="bi bi-pencil"></i></a>
              <a href="delete.php?id=<?= $u['UserID'] ?>" class="btn-action" title="Sil"
                 onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')">
                 <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Hiç kullanıcı bulunamadı.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
