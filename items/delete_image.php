<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$id = $_POST['id'] ?? null; // silinecek resmin ID'si
$item_id = $_POST['item_id'] ?? null;

if (!$id || !$item_id) {
    http_response_code(400);
    echo "Eksik veri";
    exit;
}

// Resmi bul
$stmt = $pdo->prepare("SELECT * FROM menuimages WHERE MenuImageID=?");
$stmt->execute([$id]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$image) {
    http_response_code(404);
    echo "Resim bulunamadı";
    exit;
}

// Sunucudan sil
$path = __DIR__ . "/../uploads/" . $image['ImageURL'];
if (file_exists($path)) {
    unlink($path);
}

// Veritabanından sil
$stmt = $pdo->prepare("DELETE FROM menuimages WHERE MenuImageID=?");
$stmt->execute([$id]);

echo "ok";
