<?php
// categories/edit.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php');
    exit;
}

// Mevcut kategori bilgilerini al
$stmt = $pdo->prepare('SELECT * FROM MenuCategories WHERE CategoryID = ? AND RestaurantID = ?');
$stmt->execute([$id, $restaurantId]);
$category = $stmt->fetch();
if (!$category) {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';

    $imagePath = $category['ImageURL'];
    if (!empty($_FILES['image']['name'])) {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $target = $uploadsDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imagePath = 'uploads/' . $fileName;
        } else {
            $message = 'Resim yüklenirken hata oluştu.';
        }
    }

    if (!$message) {
        $stmt = $pdo->prepare('UPDATE MenuCategories SET CategoryName = ?, ImageURL = ? WHERE CategoryID = ? AND RestaurantID = ?');
        $stmt->execute([$name, $imagePath, $id, $restaurantId]);
        header('Location: list.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 600px;">
    <h2 class="mb-4">Kategori Düzenle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="edit.php?id=<?= $category['CategoryID'] ?>" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Kategori Adı</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($category['CategoryName']) ?>">
        </div>
        <div class="mb-3">
            <label>Mevcut Resim</label><br>
            <?php if ($category['ImageURL']): ?>
                <img src="../<?= htmlspecialchars($category['ImageURL']) ?>" style="height:80px;">
            <?php else: ?>
                <span>Resim yok</span>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label>Yeni Resim Yükle (isteğe bağlı)</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <button class="btn btn-primary">Güncelle</button>
        <a href="list.php" class="btn btn-secondary">Geri</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
