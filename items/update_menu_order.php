<?php
// items/update_menu_order.php
require_once __DIR__ . '/../db.php';

$order = $_POST['order'] ?? [];
if ($order) {
    foreach($order as $index => $id) {
        $stmt = $pdo->prepare("UPDATE MenuItems SET SortOrder = ? WHERE MenuItemID = ?");
        $stmt->execute([$index, $id]);
    }
    echo "success";
} else {
    echo "no data";
}
