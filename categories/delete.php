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
if (!$id) {
    header('Location: list.php');
    exit;
}

// Kategori bilgisi
$stmt = $pdo->prepare("SELECT ImageURL FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
$stmt->execute([$id, $restaurantId]);
$cat = $stmt->fetch();

if ($cat) {
    try {
        $pdo->beginTransaction();

        // 1. Çeviri kayıtlarını sil
        $delTr = $pdo->prepare("DELETE FROM MenuCategoryTranslations WHERE CategoryID=?");
        $delTr->execute([$id]);

        // 2. Resmi sil
        if (!empty($cat['ImageURL'])) {
            $filePath = __DIR__ . '/../' . $cat['ImageURL'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 3. Ana kategori kaydını sil
        $del = $pdo->prepare("DELETE FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
        $del->execute([$id, $restaurantId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // hata oluşsa bile yönlendirme yapıyoruz ama log tutmak istersen buraya yazabilirsin
    }
}

header('Location: list.php');
exit;
