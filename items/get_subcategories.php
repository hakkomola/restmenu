<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$categoryId = $_GET['category_id'] ?? 0;

$stmt = $pdo->prepare('SELECT SubCategoryID, SubCategoryName FROM SubCategories WHERE RestaurantID = ? AND CategoryID = ? ORDER BY SortOrder ASC, SubCategoryName ASC');
$stmt->execute([$restaurantId, $categoryId]);
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($subcategories);
