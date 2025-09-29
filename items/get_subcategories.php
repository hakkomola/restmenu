<?php
// subcategories/get_subcategories.php
require_once __DIR__ . '/../db.php';

$categoryId = $_GET['category_id'] ?? null;
$restaurantId = $_SESSION['restaurant_id'] ?? null;

if(!$categoryId || !$restaurantId){
    echo '<option value="">Seçiniz</option>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID=? AND RestaurantID=? ORDER BY SubCategoryName ASC");
$stmt->execute([$categoryId, $restaurantId]);
$subs = $stmt->fetchAll();

if($subs){
    foreach($subs as $sub){
        echo '<option value="'.$sub['SubCategoryID'].'">'.htmlspecialchars($sub['SubCategoryName']).'</option>';
    }
} else {
    echo '<option value="">Alt kategori bulunamadı</option>';
}
