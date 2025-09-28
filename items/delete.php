<?php
// items/delete.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$id = $_GET['id'] ?? null;

if ($id) {
    // Menü öğesini ve resimlerini al
    $stmt = $pdo->prepare('SELECT * FROM MenuItems WHERE MenuItemID = ? AND RestaurantID = ?');
    $stmt->execute([$id, $restaurantId]);
    $item = $stmt->fetch();

    if ($item) {
        // Menüye ait tüm resimleri al ve dosyalardan sil
        $stmt = $pdo->prepare('SELECT * FROM MenuImages WHERE MenuItemID = ?');
        $stmt->execute([$id]);
        $images = $stmt->fetchAll();
        foreach ($images as $img) {
            if ($img['ImageURL'] && file_exists(__DIR__ . '/../' . $img['ImageURL'])) {
                unlink(__DIR__ . '/../' . $img['ImageURL']);
            }
        }

        // MenuImages tablosundaki resimleri sil
        $stmt = $pdo->prepare('DELETE FROM MenuImages WHERE MenuItemID = ?');
        $stmt->execute([$id]);

        // Menü öğesini sil
        $stmt = $pdo->prepare('DELETE FROM MenuItems WHERE MenuItemID = ? AND RestaurantID = ?');
        $stmt->execute([$id, $restaurantId]);
    }
}

// Menü listesine yönlendir
header('Location: list.php');
exit;
