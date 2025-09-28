<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    echo json_encode(['success'=>false, 'msg'=>'Giriş yapmadınız']);
    exit;
}

$item_id = $_POST['item_id'] ?? null;
if (!$item_id) {
    echo json_encode(['success'=>false, 'msg'=>'Item ID yok']);
    exit;
}

$uploadedImages = [];

if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
            $fileName = time().'_'.basename($_FILES['images']['name'][$index]);
            $target = __DIR__ . "/../uploads/" . $fileName;
            if (move_uploaded_file($tmpName, $target)) {
                $stmt = $pdo->prepare("INSERT INTO menuimages (MenuItemID, ImageURL) VALUES (?, ?)");
                $stmt->execute([$item_id, "/uploads/" . $fileName]);
                $uploadedImages[] = ['id'=>$pdo->lastInsertId(), 'url'=>"/../uploads/" . $fileName];
            }
        }
    }
}

echo json_encode(['success'=>true, 'images'=>$uploadedImages]);
