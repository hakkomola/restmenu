<?php
// categories/delete.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php');
    exit;
}

// Önce kategori bilgilerini al
$stmt = $pdo->prepare("SELECT ImageURL FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
$stmt->execute([$id, $_SESSION['restaurant_id']]);
$cat = $stmt->fetch();

if ($cat) {
    // Resim dosyasını sil
    if (!empty($cat['ImageURL'])) {
        $filePath = __DIR__ . '/../' . $cat['ImageURL'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Kategoriyi sil
    $del = $pdo->prepare("DELETE FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
    $del->execute([$id, $_SESSION['restaurant_id']]);
}

header('Location: list.php');
exit;
