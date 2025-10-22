<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId   = $_SESSION['restaurant_id'];
$currentBranch  = $_SESSION['current_branch'] ?? null;
$pageTitle      = "Kategoriler";

// 🔹 Kategoriler
$sql = "
  SELECT c.CategoryID, c.CategoryName, c.ImageURL, c.SortOrder, b.BranchName, c.BranchID
  FROM MenuCategories c
  LEFT JOIN RestaurantBranches b ON b.BranchID = c.BranchID
  WHERE c.RestaurantID = ?
";
$params = [$restaurantId];

// Eğer aktif bir şube seçiliyse sadece o şubenin kategorilerini getir
if (!empty($currentBranch)) {
  $sql .= " AND (c.BranchID = ? OR c.BranchID IS NULL)";
  $params[] = $currentBranch;
}

$sql .= " ORDER BY c.SortOrder ASC, c.CategoryName ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>

  <a href="create.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle"></i> Yeni
  </a>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:40px;" class="text-center"><i class="bi bi-arrows-move"></i></th>
        <th>Kategori Adı</th>
        <th>Şube</th>
        <th>Resim</th>
        <th class="text-end">İşlem</th>
      </tr>
    </thead>
    <tbody id="sortable">
      <?php if ($categories): ?>
        <?php foreach ($categories as $c): ?>
          <tr data-id="<?= $c['CategoryID'] ?>" style="cursor:move;">
            <td class="text-center text-secondary">
              <i class="bi bi-grip-vertical" style="font-size:1.1rem;"></i>
            </td>
            <td><?= htmlspecialchars($c['CategoryName']) ?></td>
            <td><?= htmlspecialchars($c['BranchName'] ?? 'Tüm Şubeler') ?></td>
            <td class="text-center">
              <?php if (!empty($c['ImageURL'])): ?>
                <img src="../<?= htmlspecialchars($c['ImageURL']) ?>" style="height:50px; border-radius:6px;">
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>

            <td class="text-end" style="white-space: nowrap;">
              <a href="edit.php?id=<?= $c['CategoryID'] ?>" class="btn-action" title="Düzenle">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="delete.php?id=<?= $c['CategoryID'] ?>"
                 class="btn-action"
                 title="Sil"
                 onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-4">Hiç kategori bulunamadı.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
<script>
$(function(){
  // 🔹 Drag & drop sıralama
  $("#sortable").sortable({
    placeholder: "sortable-placeholder",
    handle: "td:first-child", // sadece ilk sütundan sürükle
    update: function() {
      let order = $(this).children().map(function(){ return $(this).data('id'); }).get();
      $.post('update_category_order.php', { order: order });
    }
  }).disableSelection();
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
