<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId  = $_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null;
$pageTitle     = "Alt Kategoriler";

// 1) Şubeye göre kategorileri çek
$sqlCats = "
  SELECT c.CategoryID, c.CategoryName
  FROM MenuCategories c
  WHERE c.RestaurantID = ?
";
$params = [$restaurantId];
if (!empty($currentBranch)) {
  $sqlCats .= " AND (c.BranchID = ? OR c.BranchID IS NULL)";
  $params[] = $currentBranch;
}
$sqlCats .= " ORDER BY c.SortOrder ASC, c.CategoryName ASC";

$stmtCats = $pdo->prepare($sqlCats);
$stmtCats->execute($params);
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// 2) Güvenli seçili kategori belirle (redirectsız)
$selectedCategoryId = $_GET['category_id'] ?? null;
$selectedCategoryId = $selectedCategoryId ? (int)$selectedCategoryId : null;

$validIds = array_column($categories, 'CategoryID');
if ($selectedCategoryId && !in_array($selectedCategoryId, $validIds)) {
  // URL’deki id yeni şubede yok -> geçersiz say
  $selectedCategoryId = null;
}
if (!$selectedCategoryId && !empty($categories)) {
  // hiç seçim yoksa ilk uygun kategori
  $selectedCategoryId = (int)$categories[0]['CategoryID'];
}

// 3) Alt kategorileri getir (varsa)
$subcategories = [];
if ($selectedCategoryId) {
  $stmtSub = $pdo->prepare("
    SELECT s.SubCategoryID, s.SubCategoryName, s.ImageURL, s.SortOrder
    FROM SubCategories s
    WHERE s.RestaurantID = ? AND s.CategoryID = ?
    ORDER BY s.SortOrder ASC, s.SubCategoryName ASC
  ");
  $stmtSub->execute([$restaurantId, $selectedCategoryId]);
  $subcategories = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>

    <?php if ($categories): ?>
      <form method="get" class="d-flex align-items-center">
        <select name="category_id" id="categorySelect" class="form-select form-select-sm">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['CategoryID'] ?>" <?= ((int)$cat['CategoryID'] === (int)$selectedCategoryId) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['CategoryName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($selectedCategoryId): ?>
    <a href="create.php?category_id=<?= (int)$selectedCategoryId ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle"></i> Yeni
    </a>
  <?php endif; ?>
</div>

<?php if (!$categories): ?>
  <div class="alert alert-info">Bu şubede hiç kategori yok. Önce kategori ekleyin.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:40px;" class="text-center"><i class="bi bi-arrows-move"></i></th>
          <th>Alt Kategori Adı</th>
          <th>Resim</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody id="sortable">
        <?php if ($subcategories): ?>
          <?php foreach ($subcategories as $s): ?>
            <tr data-id="<?= (int)$s['SubCategoryID'] ?>" style="cursor:move;">
              <td class="text-center text-secondary">
                <i class="bi bi-grip-vertical" style="font-size:1.1rem;"></i>
              </td>
              <td><?= htmlspecialchars($s['SubCategoryName']) ?></td>
              <td class="text-center">
                <?php if (!empty($s['ImageURL'])): ?>
                  <img src="../<?= htmlspecialchars($s['ImageURL']) ?>" style="height:50px; border-radius:6px;">
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-end" style="white-space: nowrap;">
                <a href="edit.php?id=<?= (int)$s['SubCategoryID'] ?>" class="btn-action" title="Düzenle">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="delete.php?id=<?= (int)$s['SubCategoryID'] ?>"
                   class="btn-action"
                   title="Sil"
                   onclick="return confirm('Bu alt kategoriyi silmek istediğinize emin misiniz?')">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center text-muted py-4">Bu kategoride alt kategori yok.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
<script>
$(function(){
  // kategori değişince querystring ile yenile
  $('#categorySelect').on('change', function(){
    const catId = $(this).val();
    const base = window.location.pathname; // /subcategories/list.php
    if (catId) window.location.href = base + '?category_id=' + encodeURIComponent(catId);
    else window.location.href = base;
  });

  // drag & drop
  $("#sortable").sortable({
    placeholder: "sortable-placeholder",
    handle: "td:first-child",
    update: function() {
      const order = $(this).children().map(function(){ return $(this).data('id'); }).get();
      $.post('update_subcategory_order.php', { order: order, category_id: $('#categorySelect').val() });
    }
  }).disableSelection();
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
