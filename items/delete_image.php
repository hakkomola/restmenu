<?php
// items/delete_image.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) exit;

$id = $_GET['id'] ?? null;
$menuId = $_GET['menu_id'] ?? null;

if(!$id || !$menuId) exit;

// Resmi veritabanından al
$stmt = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuImageID=?");
$stmt->execute([$id]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if($image){
    $path = __DIR__ . '/../' . $image['ImageURL'];
    if(file_exists($path)) unlink($path); // dosyayı sil
    $delStmt = $pdo->prepare("DELETE FROM MenuImages WHERE MenuImageID=?");
    $delStmt->execute([$id]);
}

header("Location: edit.php?id=$menuId");
exit;
