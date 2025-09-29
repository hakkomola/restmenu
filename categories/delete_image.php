<?php
session_start();
require_once __DIR__ . '/../db.php';

$id = $_GET['id'] ?? null;
if (!$id) exit;

$stmt = $pdo->prepare("SELECT ImageURL FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
$stmt->execute([$id, $_SESSION['restaurant_id']]);
$cat = $stmt->fetch();

if ($cat && !empty($cat['ImageURL'])) {
    $file = __DIR__ . '/../' . $cat['ImageURL'];
    if (file_exists($file)) unlink($file);

    $update = $pdo->prepare("UPDATE MenuCategories SET ImageURL=NULL WHERE CategoryID=? AND RestaurantID=?");
    $update->execute([$id, $_SESSION['restaurant_id']]);
}

header('Location: edit.php?id=' . $id);
exit;
