<?php
// categories/update_category_order.php
require_once __DIR__ . '/../db.php';

$order = $_POST['order'] ?? [];
if ($order) {
    foreach($order as $index => $id) {
        $stmt = $pdo->prepare("UPDATE MenuCategories SET SortOrder = ? WHERE CategoryID = ?");
        $stmt->execute([$index, $id]);
    }
    echo "success";
} else {
    echo "no data";
}
