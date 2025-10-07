<?php
// subcategories/delete.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$subId = $_GET['id'] ?? null;
$categoryId = $_GET['category_id'] ?? null;

if (!$subId) {
    header("Location: list.php");
    exit;
}

// Alt kategori kontrolü
$stmt = $pdo->prepare("SELECT * FROM SubCategories WHERE SubCategoryID=? AND RestaurantID=?");
$stmt->execute([$subId, $restaurantId]);
$sub = $stmt->fetch();

if ($sub) {
    try {
        $pdo->beginTransaction();

        // 1️⃣ Çeviri kayıtlarını sil
        $delTr = $pdo->prepare("DELETE FROM SubCategoryTranslations WHERE SubCategoryID=?");
        $delTr->execute([$subId]);

        // 2️⃣ Resim varsa sil
        if ($sub['ImageURL'] && file_exists('../' . $sub['ImageURL'])) {
            unlink('../' . $sub['ImageURL']);
        }

        // 3️⃣ Alt kategoriyi sil
        $stmtDel = $pdo->prepare("DELETE FROM SubCategories WHERE SubCategoryID=? AND RestaurantID=?");
        $stmtDel->execute([$subId, $restaurantId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // isteğe göre hata loglanabilir
    }
}

// Liste sayfasına yönlendir
header("Location: list.php?category_id=" . ($categoryId ?? ''));
exit;
