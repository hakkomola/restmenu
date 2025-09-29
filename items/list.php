<?php
// items/list.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/dashboard.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

// Tüm ana kategoriler ve alt kategoriler
$categories = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID=? ORDER BY CategoryName ASC");
$categories->execute([$restaurantId]);
$categories = $categories->fetchAll();

$subcategories = $pdo->prepare("SELECT * FROM SubCategories WHERE RestaurantID=? ORDER BY SubCategoryName ASC");
$subcategories->execute([$restaurantId]);
$subcategories = $subcategories->fetchAll();

// Filtreler
$filterCategory = $_GET['category'] ?? '';
$filterSubCategory = $_GET['subcategory'] ?? '';

// Menü öğelerini al
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
$menuItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü Yönetimi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<style>
    .sortable-placeholder { height: 60px; background: #f0f0f0; border: 2px dashed #ccc; }
    .ui-sortable-helper { background: #e9ecef; }
    .drag-handle { cursor: move; }
</style>
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Menü Öğeleri</h2>

    <div class="row mb-3">
        <div class="col-md-4">
            <select id="categoryFilter" class="form-select">
                <option value="">-- Ana Kategori Seç --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>" <?= $filterCategory == $cat['CategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select id="subcategoryFilter" class="form-select">
                <option value="">-- Alt Kategori Seç --</option>
                <?php foreach ($subcategories as $sub): ?>
                    <option value="<?= $sub['SubCategoryID'] ?>" <?= $filterSubCategory == $sub['SubCategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sub['SubCategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <a href="create.php" class="btn btn-success">Yeni Menü Öğesi Ekle</a>
            <a href="../restaurants/dashboard.php" class="btn btn-secondary">Geri</a>
        </div>
    </div>

    <table class="table table-bordered" id="menu-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Menü Adı</th>
                <th>Kategori</th>
                <th>Alt Kategori</th>
                <th>Açıklama</th>
                <th>Fiyat</th>
                <th>İşlemler</th>
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
                    <a href="edit.php?id=<?= $item['MenuItemID'] ?>" class="btn btn-primary btn-sm">Düzenle</a>
                    <a href="delete.php?id=<?= $item['MenuItemID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
<script>
$(function(){
    $("#sortable").sortable({
        placeholder: "sortable-placeholder",
        handle: ".drag-handle",
        update: function(event, ui) {
            let order = $(this).children().map(function(){ return $(this).data('id'); }).get();
            $.post('update_menu_order.php', {order: order}, function(res){
                console.log(res);
            });
        }
    });

    // Filtre değişince sayfayı yeniden yükle
    $('#categoryFilter').change(function(){
        let cat = $(this).val();
        let sub = $('#subcategoryFilter').val();
        window.location.href = '?category=' + cat + '&subcategory=' + sub;
    });

    $('#subcategoryFilter').change(function(){
        let cat = $('#categoryFilter').val();
        let sub = $(this).val();
        window.location.href = '?category=' + cat + '&subcategory=' + sub;
    });
});
</script>
</body>
</html>
