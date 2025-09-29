<?php
// subcategories/update_subcategory_order.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(403);
    die('Yetkisiz');
}

$restaurantId = $_SESSION['restaurant_id'];
$order = $_POST['order'] ?? [];

if (!is_array($order)) {
    http_response_code(400);
    die('Geçersiz veri');
}

foreach ($order as $sort => $subId) {
    // Sadece o restorana ait alt kategoriler için güncelle
    $stmt = $pdo->prepare("UPDATE SubCategories SET SortOrder=? WHERE SubCategoryID=? AND CategoryID IN (SELECT CategoryID FROM MenuCategories WHERE RestaurantID=?)");
    $stmt->execute([$sort+1, $subId, $restaurantId]);
}

echo json_encode(['status'=>'success']);
