<?php
session_start();
require_once __DIR__ . '/../db.php';
error_reporting(E_ALL & ~E_WARNING);

$sourceRestaurantId = 2;

// Kategoriler
$stmt = $pdo->prepare("SELECT CategoryID, CategoryName FROM MenuCategories WHERE RestaurantID=? ORDER BY SortOrder,CategoryName ASC");
$stmt->execute([$sourceRestaurantId]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tree = [];

foreach ($categories as $cat) {
    $catId = $cat['CategoryID'];
    $tree[$catId] = [
        'id' => $catId,
        'name' => $cat['CategoryName'],
        'subcategories' => []
    ];

    // Alt kategoriler
    $stmtSub = $pdo->prepare("SELECT SubCategoryID, SubCategoryName FROM SubCategories WHERE CategoryID=? AND RestaurantID=? ORDER BY SortOrder,SubCategoryName ASC");
    $stmtSub->execute([$catId, $sourceRestaurantId]);
    $subcategories = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subcategories as $sub) {
        $subId = $sub['SubCategoryID'];
        $tree[$catId]['subcategories'][$subId] = [
            'id' => $subId,
            'name' => $sub['SubCategoryName'],
            'items' => []
        ];

        // Menü itemleri
        $stmtItem = $pdo->prepare("SELECT MenuItemID, MenuName FROM MenuItems WHERE SubCategoryID=? AND RestaurantID=? ORDER BY SortOrder,MenuName ASC");
        $stmtItem->execute([ $subId, $sourceRestaurantId]);
        $items = $stmtItem->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $tree[$catId]['subcategories'][$subId]['items'][] = [
                'id' => $item['MenuItemID'],
                'name' => $item['MenuName']
            ];
        }

        // items array formatına çevir
        $tree[$catId]['subcategories'][$subId]['items'] = array_values($tree[$catId]['subcategories'][$subId]['items']);
    }

    // subcategories array formatına çevir
    $tree[$catId]['subcategories'] = array_values($tree[$catId]['subcategories']);
}

// JSON olarak dön
header('Content-Type: application/json');
echo json_encode(array_values($tree));
