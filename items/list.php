<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId  = $_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null;
$pageTitle     = "Menü Öğeleri";

// 🔹 Kategoriler (aktif şube)
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

// 🔹 Alt kategoriler (aktif şube)
$sqlSubs = "
  SELECT s.SubCategoryID, s.SubCategoryName, s.CategoryID
  FROM SubCategories s
  JOIN MenuCategories c ON s.CategoryID = c.CategoryID
  WHERE s.RestaurantID = ?
";
$paramsSub = [$restaurantId];
if (!empty($currentBranch)) {
  $sqlSubs .= " AND (c.BranchID = ? OR c.BranchID IS NULL)";
  $paramsSub[] = $currentBranch;
}
$sqlSubs .= " ORDER BY s.SortOrder ASC, s.SubCategoryName ASC";
$stmtSubs = $pdo->prepare($sqlSubs);
$stmtSubs->execute($paramsSub);
$allSubcategories = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Filtreler
$filterCategory    = $_GET['category'] ?? '';
$filterSubCategory = $_GET['subcategory'] ?? '';

// 🔹 Menü Öğeleri
$sqlItems = "
  SELECT mi.MenuItemID, mi.MenuName, mi.Description, mi.SortOrder,
         mo.Price, sc.SubCategoryName, mc.CategoryName
  FROM MenuItems mi
  LEFT JOIN SubCategories sc ON mi.SubCategoryID = sc.SubCategoryID
  LEFT JOIN MenuCategories mc ON sc.CategoryID = mc.CategoryID
  LEFT JOIN MenuItemOptions mo ON mi.MenuItemID = mo.MenuItemID AND mo.IsDefault = 1
  WHERE mi.RestaurantID = ?
";
$paramsItems = [$restaurantId];
if (!empty($currentBranch)) {
  $sqlItems .= " AND (mi.BranchID = ? OR mi.BranchID IS NULL)";
  $paramsItems[] = $currentBranch;
}
if ($filterCategory) {
  $sqlItems .= " AND mc.CategoryID = ?";
  $paramsItems[] = $filterCategory;
}
if ($filterSubCategory) {
  $sqlItems .= " AND sc.SubCategoryID = ?";
  $paramsItems[] = $filterSubCategory;
}
$sqlItems .= " ORDER BY mi.SortOrder ASC, mi.MenuName ASC";
$stmt = $pdo->prepare($sqlItems);
$stmt->execute($paramsItems);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-semibold mb-0"><?= htmlspecialchars($pageTitle) ?></h4>

  <a href="create.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle"></i> Yeni
  </a>
</div>

<!-- 🔹 Filtre Alanı -->
<div class="row g-2 mb-4">
  <div class="col-md-4">
    <select id="categoryFilter" class="form-select form-select-sm">
      <option value="">-- Ana Kategori Seç --</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['CategoryID'] ?>" <?= $filterCategory == $cat['CategoryID'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['CategoryName']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <select id="subcategoryFilter" class="form-select form-select-sm">
      <option value="">-- Alt Kategori Seç --</option>
    </select>
  </div>
</div>

<!-- 🔹 Menü Tablosu -->
<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:40px;" class="text-center"><i class="bi bi-arrows-move"></i></th>
        <th>Menü Adı</th>
        <th>Kategori</th>
        <th>Alt Kategori</th>
        <th>Açıklama</th>
        <th>Fiyat</th>
        <th class="text-end">İşlem</th>
      </tr>
    </thead>
    <tbody id="sortable">
      <?php if ($menuItems): ?>
        <?php foreach ($menuItems as $m): ?>
          <tr data-id="<?= $m['MenuItemID'] ?>" style="cursor:move;">
            <td class="text-center text-secondary"><i class="bi bi-grip-vertical" style="font-size:1.1rem;"></i></td>
            <td><?= htmlspecialchars($m['MenuName']) ?></td>
            <td><?= htmlspecialchars($m['CategoryName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($m['SubCategoryName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($m['Description'] ?? '-') ?></td>
            <td><?= number_format($m['Price'] ?? 0, 2) ?> ₺</td>
            <td class="text-end" style="white-space: nowrap;">
              <a href="edit.php?id=<?= $m['MenuItemID'] ?>" class="btn-action" title="Düzenle">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="delete.php?id=<?= $m['MenuItemID'] ?>" class="btn-action"
                 title="Sil"
                 onclick="return confirm('Bu menü öğesini silmek istediğinize emin misiniz?')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center text-muted py-4">Hiç menü öğesi bulunamadı.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- 🔹 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
<script>
$(function(){
  const allSubs = <?= json_encode($allSubcategories) ?>;
  const currentCat = '<?= $filterCategory ?>';
  const currentSub = '<?= $filterSubCategory ?>';

  // Alt kategori listesini kategoriye göre doldur
  function populateSubcats(catId) {
    let html = '<option value="">-- Alt Kategori Seç --</option>';
    if (!catId) {
      $('#subcategoryFilter').html(html);
      return;
    }
    allSubs.forEach(sc => {
      if (sc.CategoryID == catId) {
        html += `<option value="${sc.SubCategoryID}" ${(sc.SubCategoryID == currentSub ? 'selected' : '')}>${sc.SubCategoryName}</option>`;
      }
    });
    $('#subcategoryFilter').html(html);
  }

  // Sayfa ilk açılışta doğru alt kategorileri getir
  if (currentCat) populateSubcats(currentCat);

  // Ana kategori değiştiğinde alt kategorileri güncelle
  $('#categoryFilter').on('change', function(){
    const catId = $(this).val();
    populateSubcats(catId);
  });

  // Filtre değiştiğinde sayfayı yenile
  $('#categoryFilter, #subcategoryFilter').on('change', function(){
    const cat = $('#categoryFilter').val() || '';
    const sub = $('#subcategoryFilter').val() || '';
    window.location.href = '?category=' + cat + '&subcategory=' + sub;
  });

  // Drag-drop sıralama
  $("#sortable").sortable({
    placeholder: "sortable-placeholder",
    handle: "td:first-child",
    update: function() {
      const order = $(this).children().map(function(){ return $(this).data('id'); }).get();
      $.post('update_menu_order.php', { order: order });
    }
  }).disableSelection();
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
