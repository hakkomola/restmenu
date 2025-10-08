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

// Eğer kategori seçilmemişse ilkini otomatik seç
$selectedCategoryId = $_GET['category_id'] ?? null;
if (!$selectedCategoryId && !empty($categories)) {
    $firstCatId = $categories[0]['CategoryID'];
    header("Location: ?category_id=" . $firstCatId);
    exit;
}

// Seçili kategori varsa alt kategorileri getir
if ($selectedCategoryId) {
    $stmtSub = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID=? ORDER BY SortOrder ASC, SubCategoryName ASC");
    $stmtSub->execute([$selectedCategoryId]);
    $subcategories = $stmtSub->fetchAll();
} else {
    $subcategories = [];
}

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

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
        <div class="mb-3 d-flex gap-2">
            <a href="create.php?category_id=<?= $selectedCategoryId ?>" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Yeni
            </a>
            <a href="/../restaurants/dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle"></i> Geri
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
<script>
$(function(){
    // Kategori değişince sayfayı reload et
    $('#categorySelect').change(function(){
        let catId = $(this).val();
        if (catId) window.location.href = '?category_id=' + catId;
        else window.location.href = '?';
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
