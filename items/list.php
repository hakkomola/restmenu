<?php
// items/list.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

// Menü öğelerini SubCategory ve Category ile birlikte al
$stmt = $pdo->prepare('
    SELECT mi.*, sc.SubCategoryID, sc.SubCategoryName, mc.CategoryID, mc.CategoryName
    FROM MenuItems mi
    LEFT JOIN SubCategories sc ON mi.SubCategoryID = sc.SubCategoryID
    LEFT JOIN MenuCategories mc ON sc.CategoryID = mc.CategoryID
    WHERE mi.RestaurantID = ?
    ORDER BY mi.SortOrder ASC, mi.MenuName ASC
');
$stmt->execute([$restaurantId]);
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
    <a href="create.php" class="btn btn-success mb-3">Yeni Menü Öğesi Ekle</a>
    <a href="../restaurants/dashboard.php" class="btn btn-secondary mb-3">Geri</a>

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
    }).disableSelection(); // Mobil sürükleme desteği için
});
</script>
</body>
</html>
