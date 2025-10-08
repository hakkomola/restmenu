<?php
// categories/list.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

// Restoranın kategorilerini getir (SortOrder'a göre)
$stmt = $pdo->prepare('SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY SortOrder ASC, CategoryName ASC');
$stmt->execute([$restaurantId]);
$categories = $stmt->fetchAll();

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>


<div class="container mt-5">
    <h2 class="mb-4">Kategoriler</h2>
    <div class="mb-3">
        <a href="create.php" class="btn btn-success me-2">Yeni Kategori Ekle</a>
        <a href="../restaurants/dashboard.php" class="btn btn-secondary">Geri</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="category-table">
            <thead class="table-light">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Kategori Adı</th>
                    <th style="width:120px;">Resim</th>
                    <th style="width:200px;">İşlemler</th>
                </tr>
            </thead>
            <tbody id="sortable">
                <?php foreach ($categories as $c): ?>
                <tr data-id="<?= $c['CategoryID'] ?>">
                    <td class="drag-handle text-center">☰</td>
                    <td><?= htmlspecialchars($c['CategoryName']) ?></td>
                    <td class="text-center">
                        <?php if ($c['ImageURL']): ?>
                            <img src="../<?= htmlspecialchars($c['ImageURL']) ?>" style="height:60px; border-radius:6px;">
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="edit.php?id=<?= $c['CategoryID'] ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil-square"></i> Düzenle
                        </a>
                        <a href="delete.php?id=<?= $c['CategoryID'] ?>" class="btn btn-danger btn-sm" 
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

<script>
$(function(){
    $("#sortable").sortable({
        placeholder: "sortable-placeholder",
        handle: ".drag-handle",
        update: function(event, ui) {
            let order = $(this).children().map(function(){ return $(this).data('id'); }).get();
            $.post('update_category_order.php', {order: order}, function(res){
                console.log(res);
            });
        }
    });
});
</script>



<?php include __DIR__ . '/../includes/footer.php'; ?>