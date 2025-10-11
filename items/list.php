<?php
// items/list.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/dashboard.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

// Kategoriler
$categories = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID=? ORDER BY CategoryName ASC");
$categories->execute([$restaurantId]);
$categories = $categories->fetchAll(PDO::FETCH_ASSOC);

// Alt kategoriler (hepsini çekiyoruz, JS ile filtrelenecek)
$subStmt = $pdo->prepare("SELECT * FROM SubCategories WHERE RestaurantID=? ORDER BY SubCategoryName ASC");
$subStmt->execute([$restaurantId]);
$allSubcategories = $subStmt->fetchAll(PDO::FETCH_ASSOC);

// Filtreler
$filterCategory = $_GET['category'] ?? '';
$filterSubCategory = $_GET['subcategory'] ?? '';

// Menü öğeleri
$sql = '
    SELECT mi.*, sc.SubCategoryID, sc.SubCategoryName, mc.CategoryID, mc.CategoryName
    FROM MenuItems mi
    LEFT JOIN SubCategories sc ON mi.SubCategoryID = sc.SubCategoryID
    LEFT JOIN MenuCategories mc ON sc.CategoryID = mc.CategoryID
    WHERE mi.RestaurantID = ?
';
$params = [$restaurantId];

if ($filterCategory) {
    $sql .= " AND mc.CategoryID = ?";
    $params[] = $filterCategory;
}
if ($filterSubCategory) {
    $sql .= " AND sc.SubCategoryID = ?";
    $params[] = $filterSubCategory;
}

$sql .= " ORDER BY mi.SortOrder ASC, mi.MenuName ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="container mt-5">
  <h2 class="mb-4">Menü Öğeleri</h2>

  <!-- Filtre Alanı -->
  <div class="row mb-2">
    <div class="col-md-4 mb-2">
      <select id="categoryFilter" class="form-select">
        <option value="">-- Ana Kategori Seç --</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['CategoryID'] ?>" <?= $filterCategory == $cat['CategoryID'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['CategoryName']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 mb-2">
      <select id="subcategoryFilter" class="form-select">
        <option value="">-- Alt Kategori Seç --</option>
      </select>
    </div>
  </div>

  <!-- Butonlar -->
  <div class="mb-4 d-flex flex-wrap gap-2">
    <a href="create.php" class="btn btn-success">
      <i class="bi bi-plus-circle"></i> Yeni
    </a>
    <a href="../restaurants/dashboard.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i> Geri
    </a>
  </div>

  <!-- Menü Tablosu -->
  <div class="table-responsive">
    <table class="table table-bordered align-middle" id="menu-table">
      <thead class="table-light">
        <tr>
          <th style="width:50px;">#</th>
          <th>Menü Adı</th>
          <th>Kategori</th>
          <th>Alt Kategori</th>
          <th>Açıklama</th>
          <th>Fiyat</th>
          <th style="width:200px;">İşlemler</th>
        </tr>
      </thead>
      <tbody id="sortable">
        <?php foreach ($menuItems as $item): ?>
        <tr data-id="<?= $item['MenuItemID'] ?>">
          <td class="drag-handle">☰</td>
          <td><?= htmlspecialchars($item['MenuName']) ?></td>
          <td><?= htmlspecialchars($item['CategoryName'] ?? '-') ?></td>
          <td><?= htmlspecialchars($item['SubCategoryName'] ?? '-') ?></td>
          <td><?= htmlspecialchars($item['Description']) ?></td>
          <td><?= number_format($item['Price'], 2) ?> ₺</td>
          <td>
            <a href="edit.php?id=<?= $item['MenuItemID'] ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-pencil-square"></i> Düzenle
            </a>
            <a href="delete.php?id=<?= $item['MenuItemID'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Silmek istediğinize emin misiniz?')">
               <i class="bi bi-trash"></i> Sil
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- SortableJS: mobil + desktop sürükle-bırak -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
$(function() {
  const allSubs = <?= json_encode($allSubcategories) ?>;
  const currentCat = '<?= $filterCategory ?>';
  const currentSub = '<?= $filterSubCategory ?>';

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

  // Sayfa ilk açıldığında seçili kategori varsa alt kategorileri yükle
  if (currentCat) {
    populateSubcats(currentCat);
  }

  // Ana kategori değişince alt kategorileri filtrele
  $('#categoryFilter').on('change', function() {
    const catId = $(this).val();
    populateSubcats(catId);
  });

  // Filtre değişince sayfayı yenile
  $('#categoryFilter, #subcategoryFilter').on('change', function() {
    const cat = $('#categoryFilter').val();
    const sub = $('#subcategoryFilter').val();
    window.location.href = '?category=' + cat + '&subcategory=' + sub;
  });

$(function() {
  const allSubs = <?= json_encode($allSubcategories) ?>;
  const currentCat = '<?= $filterCategory ?>';
  const currentSub = '<?= $filterSubCategory ?>';

  function populateSubcats(catId) {
    let html = '<option value="">-- Alt Kategori Seç --</option>';
    if (!catId) {
      $('#subcategoryFilter').html(html);
      return;
    }
    allSubs.forEach(sc => {
      if (sc.CategoryID == catId) {
        html += '<option value="' + sc.SubCategoryID + '"' + (sc.SubCategoryID == currentSub ? ' selected' : '') + '>' + sc.SubCategoryName + '</option>';
      }
    });
    $('#subcategoryFilter').html(html);
  }

  if (currentCat) { populateSubcats(currentCat); }

  $('#categoryFilter').on('change', function() {
    const catId = $(this).val();
    populateSubcats(catId);
  });

  $('#categoryFilter, #subcategoryFilter').on('change', function() {
    const cat = $('#categoryFilter').val() || '';
    const sub = $('#subcategoryFilter').val() || '';
    window.location.href = '?category=' + cat + '&subcategory=' + sub;
  });

  // --- SortableJS ile sürükle-bırak (mobil + desktop) ---
  const el = document.getElementById('sortable');
  if (el) {
    new Sortable(el, {
      handle: '.drag-handle',
      animation: 150,
      direction: 'vertical',
      onEnd: function () {
        const order = Array.from(el.children).map(function(tr){
          return tr.getAttribute('data-id');
        });
        // PHP tarafı array bekliyorsa form-encoded gönder
        fetch('update_menu_order.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: 'order[]=' + order.join('&order[]=')
        }).then(r => r.text()).then(console.log);
      }
    });
  }
});

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
