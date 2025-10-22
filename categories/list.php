<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$pageTitle = "Kategoriler";

// 🔹 Şubeleri al
$stmt = $pdo->prepare("SELECT BranchID, BranchName FROM RestaurantBranches WHERE RestaurantID=? ORDER BY BranchName ASC");
$stmt->execute([$restaurantId]);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Filtre
$selectedBranchId = $_GET['branch_id'] ?? 'all';

// 🔹 Kategoriler
$sql = "
  SELECT c.CategoryID, c.CategoryName, c.ImageURL, c.SortOrder, b.BranchName, c.BranchID
  FROM MenuCategories c
  LEFT JOIN RestaurantBranches b ON b.BranchID = c.BranchID
  WHERE c.RestaurantID = ?
";
$params = [$restaurantId];
if ($selectedBranchId !== 'all') {
  $sql .= " AND c.BranchID = ?";
  $params[] = $selectedBranchId;
}
$sql .= " ORDER BY c.SortOrder ASC, c.CategoryName ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>
    <!-- 🔹 Şube filtre -->
    <form method="get" class="d-flex align-items-center ms-2">
      <select name="branch_id" id="branchSelect" class="form-select form-select-sm">
        <option value="all" <?= $selectedBranchId === 'all' ? 'selected' : '' ?>>Tüm Şubeler</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $selectedBranchId == $b['BranchID'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['BranchName']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <a href="create.php<?= $selectedBranchId !== 'all' ? '?branch_id=' . $selectedBranchId : '' ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle"></i> Yeni
  </a>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Kategori Adı</th>
        <th>Şube</th>
        <th>Resim</th>
        <th>Sıra</th>
        <th class="text-end">İşlem</th>
      </tr>
    </thead>
    <tbody id="sortable">
      <?php if ($categories): ?>
        <?php foreach ($categories as $c): ?>
          <tr data-id="<?= $c['CategoryID'] ?>">
            <td><?= $c['CategoryID'] ?></td>
            <td><?= htmlspecialchars($c['CategoryName']) ?></td>
            <td><?= htmlspecialchars($c['BranchName'] ?? 'Tüm Şubeler') ?></td>
            <td class="text-center">
              <?php if (!empty($c['ImageURL'])): ?>
                <img src="../<?= htmlspecialchars($c['ImageURL']) ?>" style="height:50px; border-radius:6px;">
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($c['SortOrder']) ?></td>
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
          <td colspan="6" class="text-center text-muted py-4">Hiç kategori bulunamadı.</td>
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
  // 🔹 Filtre değişince sayfayı yenile
  $('#branchSelect').change(function(){
    let branchId = $(this).val();
    if (branchId && branchId !== 'all')
      window.location.href = '?branch_id=' + branchId;
    else
      window.location.href = '?branch_id=all';
  });

  // 🔹 Drag & drop sıralama
  $("#sortable").sortable({
    placeholder: "sortable-placeholder",
    handle: "td:first-child",
    update: function() {
      let order = $(this).children().map(function(){ return $(this).data('id'); }).get();
      $.post('update_category_order.php', { order: order });
    }
  }).disableSelection();
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
