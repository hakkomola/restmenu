<?php
// categories/delete.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$id = $_GET['id'] ?? null;

if ($id) {
    // Önce kategori mevcut mu kontrol et
    $stmt = $pdo->prepare('SELECT * FROM MenuCategories WHERE CategoryID = ? AND RestaurantID = ?');
    $stmt->execute([$id, $restaurantId]);
    $category = $stmt->fetch();

    if ($category) {
        // Eğer kategoriye ait resim varsa dosyadan sil
        if ($category['ImageURL'] && file_exists(__DIR__ . '/../' . $category['ImageURL'])) {
            unlink(__DIR__ . '/../' . $category['ImageURL']);
        }

        // Kategoriyi sil
        $stmt = $pdo->prepare('DELETE FROM MenuCategories WHERE CategoryID = ? AND RestaurantID = ?');
        $stmt->execute([$id, $restaurantId]);
    }
}

// Liste sayfasına geri yönlendir
header('Location: list.php');
exit;
