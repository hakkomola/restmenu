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

              <a href="create.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Yeni
            </a>
            <a href="/../restaurants/dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle"></i> Geri
            </a>
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


<!-- jQuery (varsa tekrar yüklemeyin) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery UI (Sortable bunun içinde) -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- TouchPunch: jQuery UI'yi mobil dokunuşla çalıştırır -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>

<script>
    

$(function(){
  $("#sortable").sortable({
    items: "> tr",                 // sadece satırlar
    handle: ".drag-handle",        // sadece tutamaçtan sürükle
    axis: "y",                     // dikey sürükleme
    placeholder: "sortable-placeholder",
    helper: function(e, tr) {      // tablo hücre genişliği sabit kalsın
      var $originals = tr.children();
      var $helper = tr.clone();
      $helper.children().each(function(index){
        $(this).width($originals.eq(index).width());
      });
      return $helper;
    },
    update: function(){
      var order = $(this).children().map(function(){ return $(this).data('id'); }).get();
      $.post('update_category_order.php', { order: order }, function(res){
        console.log(res);
      });
    }
  }).disableSelection();
});


</script>



<?php include __DIR__ . '/../includes/footer.php'; ?>