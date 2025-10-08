<?php
// subcategories/list.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

// Tüm kategoriler
$stmtCats = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID=? ORDER BY CategoryName ASC");
$stmtCats->execute([$restaurantId]);
$categories = $stmtCats->fetchAll();

// Seçili kategori
$selectedCategoryId = $_GET['category_id'] ?? null;
if ($selectedCategoryId) {
    $stmtSub = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID=? ORDER BY SortOrder ASC, SubCategoryName ASC");
    $stmtSub->execute([$selectedCategoryId]);
    $subcategories = $stmtSub->fetchAll();
} else {
    $subcategories = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alt Kategoriler</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<style>
    body { background: #f8f9fa; font-family: "Segoe UI", Arial, sans-serif; }
    .sortable-placeholder { height: 60px; background: #f0f0f0; border: 2px dashed #ccc; }
    .ui-sortable-helper { background: #e9ecef; }
    .drag-handle { cursor: move; }
    .img-thumb { height: 50px; object-fit: cover; border-radius: 6px; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Alt Kategoriler</h2>

    <div class="mb-3">
        <label for="categorySelect" class="form-label">Kategori Seçin</label>
        <select id="categorySelect" class="form-select">
            <option value="">-- Kategori Seçin --</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['CategoryID'] ?>" <?= $cat['CategoryID']==$selectedCategoryId?'selected':'' ?>>
                    <?= htmlspecialchars($cat['CategoryName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($selectedCategoryId): ?>
        <div class="mb-3">
            <a href="create.php?category_id=<?= $selectedCategoryId ?>" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Yeni Alt Kategori Ekle
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="subcat-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Alt Kategori Adı</th>
                        <th style="width:120px;">Resim</th>
                        <th style="width:200px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody id="sortable">
                    <?php foreach($subcategories as $sub): ?>
                    <tr data-id="<?= $sub['SubCategoryID'] ?>">
                        <td class="drag-handle text-center">☰</td>
                        <td><?= htmlspecialchars($sub['SubCategoryName']) ?></td>
                        <td class="text-center">
                            <?php if ($sub['ImageURL']): ?>
                                <img src="../<?= htmlspecialchars($sub['ImageURL']) ?>" class="img-thumb">
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="edit.php?id=<?= $sub['SubCategoryID'] ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil-square"></i> Düzenle
                            </a>
                            <a href="delete.php?id=<?= $sub['SubCategoryID'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Silmek istediğinize emin misiniz?')">
                                <i class="bi bi-trash"></i> Sil
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Lütfen üstteki drop-down’dan bir kategori seçin.</div>
    <?php endif; ?>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
<!-- ✅ Bootstrap bundle (navbar mobil menü için gerekli) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    // Kategori değişince sayfayı reload et
    $('#categorySelect').change(function(){
        let catId = $(this).val();
        window.location.href = '?category_id=' + catId;
    });

    // Sıralama (drag & drop)
    $("#sortable").sortable({
        placeholder: "sortable-placeholder",
        handle: ".drag-handle",
        update: function(event, ui) {
            let order = $(this).children().map(function(){ return $(this).data('id'); }).get();
            $.post('update_subcategory_order.php', {order: order}, function(res){
                console.log(res);
            });
        }
    });
});
</script>
</body>
</html>
