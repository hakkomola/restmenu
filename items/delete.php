<?php
// items/delete.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = (int)$_SESSION['restaurant_id'];
$itemId       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// (opsiyonel) geri dönüşte filtreyi korumak istersen:
$subCategoryId = isset($_GET['sub_category_id']) ? (int)$_GET['sub_category_id'] : 0;

if ($itemId <= 0) {
    header('Location: list.php');
    exit;
}

// Ürün doğrula
$itm = $pdo->prepare("SELECT MenuItemID, SubCategoryID FROM MenuItems WHERE MenuItemID=? AND RestaurantID=?");
$itm->execute([$itemId, $restaurantId]);
$item = $itm->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    header('Location: list.php');
    exit;
}

// FK adı otomatik tespit (MenuItemTranslations için)
$fkCol = 'MenuItemID';
try {
    $colCheck = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'MenuItemTranslations'
          AND COLUMN_NAME IN ('MenuItemID','ItemID')
        LIMIT 1
    ");
    $colCheck->execute();
    $found = $colCheck->fetchColumn();
    if ($found) { $fkCol = $found; }
} catch (Exception $e) {
    // Sessiz geç; fkCol varsayılan MenuItemID kalır
}

try {
    $pdo->beginTransaction();

    // 1) OptionID'leri çek
    $optStmt = $pdo->prepare("SELECT OptionID FROM MenuItemOptions WHERE MenuItemID=?");
    $optStmt->execute([$itemId]);
    $optIds = $optStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // 1.a) Option çevirilerini sil (MenuItemOptionTranslations)
    if (!empty($optIds)) {
        // IN listesi
        $placeholders = implode(',', array_fill(0, count($optIds), '?'));
        $delOptTr = $pdo->prepare("DELETE FROM MenuItemOptionTranslations WHERE OptionID IN ($placeholders)");
        $delOptTr->execute($optIds);
    }

    // 1.b) Seçenekleri sil (MenuItemOptions)
    $delOpts = $pdo->prepare("DELETE FROM MenuItemOptions WHERE MenuItemID=?");
    $delOpts->execute([$itemId]);

    // 2) Ürün çevirilerini sil (MenuItemTranslations)
    $delItemTr = $pdo->prepare("DELETE FROM MenuItemTranslations WHERE $fkCol = ?");
    $delItemTr->execute([$itemId]);

    // 3) Resimleri sil (dosya + kayıt) (MenuImages)
    $imgStmt = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID=?");
    $imgStmt->execute([$itemId]);
    $imgs = $imgStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!empty($imgs)) {
        foreach ($imgs as $url) {
            if ($url) {
                $path = __DIR__ . '/../' . $url;
                if (file_exists($path)) { @unlink($path); }
            }
        }
    }
    $delImgs = $pdo->prepare("DELETE FROM MenuImages WHERE MenuItemID=?");
    $delImgs->execute([$itemId]);

    // 4) Ana ürün kaydı sil (MenuItems)
    $delItem = $pdo->prepare("DELETE FROM MenuItems WHERE MenuItemID=? AND RestaurantID=?");
    $delItem->execute([$itemId, $restaurantId]);

    $pdo->commit();

    // geri dönüş (isteğe göre alt kategori filtresi korunur)
    if ($subCategoryId > 0) {
        header('Location: list.php?sub_category_id=' . $subCategoryId);
    } else {
        header('Location: list.php');
    }
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    // İsteğe göre loglanabilir
    if ($subCategoryId > 0) {
        header('Location: list.php?sub_category_id=' . $subCategoryId . '&err=delete');
    } else {
        header('Location: list.php?err=delete');
    }
    exit;
}
