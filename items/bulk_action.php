<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Yetkisiz erişim.']));
}

$restaurantId = $_SESSION['restaurant_id'];
$action = $_POST['action'] ?? '';
$value  = floatval($_POST['value'] ?? 0);
$ids    = $_POST['ids'] ?? [];

if (empty($ids)) {
    die(json_encode(['status' => 'error', 'message' => 'Seçim yapılmadı.']));
}

$idList = implode(',', array_map('intval', $ids));

switch ($action) {
    case 'delete':
        $stmt = $pdo->prepare("DELETE FROM MenuItems WHERE RestaurantID=? AND MenuItemID IN ($idList)");
        $stmt->execute([$restaurantId]);
        echo json_encode(['status' => 'success', 'message' => 'Seçilen kayıtlar silindi.']);
        break;

    case 'increase_percent':
        $pdo->prepare("
            UPDATE MenuItemOptions 
            SET Price = Price * (1 + ?/100)
            WHERE MenuItemID IN ($idList)
        ")->execute([$value]);
        echo json_encode(['status' => 'success', 'message' => "%$value fiyat artışı uygulandı."]);
        break;

    case 'increase_fixed':
        $pdo->prepare("
            UPDATE MenuItemOptions 
            SET Price = Price + ?
            WHERE MenuItemID IN ($idList)
        ")->execute([$value]);
        echo json_encode(['status' => 'success', 'message' => "$value TL eklendi."]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem.']);
}
