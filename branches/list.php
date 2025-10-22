<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('branches')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];

// Şubeleri getir
$stmt = $pdo->prepare("
    SELECT BranchID, BranchName, Address, Phone, MapUrl, IsActive, CreatedAt, UpdatedAt
    FROM RestaurantBranches
    WHERE RestaurantID = ?
    ORDER BY BranchID ASC
");
$stmt->execute([$restaurantId]);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Şubeler";
include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
  <a href="create.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle"></i> Yeni
  </a>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Şube Adı</th>
        <th>Adres</th>
        <th>Telefon</th>
        <th>Durum</th>
        <th>Harita</th>
        <th>Oluşturulma</th>
        <th>Güncellendi</th>
        <th class="text-end">İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($branches): ?>
        <?php foreach ($branches as $b): ?>
        <tr>
          <td><?= $b['BranchID'] ?></td>
          <td><?= htmlspecialchars($b['BranchName']) ?></td>
          <td><?= htmlspecialchars($b['Address'] ?? '-') ?></td>
          <td><?= htmlspecialchars($b['Phone'] ?? '-') ?></td>

          <!-- Durum -->
          <td>
            <?php if ($b['IsActive']): ?>
              <span class="badge bg-success">Aktif</span>
            <?php else: ?>
              <span class="badge bg-secondary">Pasif</span>
            <?php endif; ?>
          </td>

          <!-- Harita -->
          <td>
            <?php if (!empty($b['MapUrl'])): ?>
              <a href="<?= htmlspecialchars($b['MapUrl']) ?>" target="_blank" class="text-decoration-none">
                <i class="bi bi-geo-alt"></i> Haritayı Aç
              </a>
            <?php else: ?>
              <span class="text-muted">-</span>
            <?php endif; ?>
          </td>

          <!-- Oluşturulma & Güncellenme -->
          <td><?= htmlspecialchars($b['CreatedAt']) ?></td>
          <td><?= htmlspecialchars($b['UpdatedAt']) ?></td>

          <!-- İşlem -->
          <td class="text-end" style="white-space: nowrap;">
  <a href="edit.php?id=<?= $b['BranchID'] ?>" class="btn-action" title="Düzenle">
    <i class="bi bi-pencil"></i>
  </a>
  <a href="delete.php?id=<?= $b['BranchID'] ?>" class="btn-action" title="Sil"
     onclick="return confirm('Bu şubeyi silmek istediğinize emin misiniz?')">
    <i class="bi bi-trash"></i>
  </a>
</td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" class="text-center text-muted py-4">Hiç şube bulunamadı.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
