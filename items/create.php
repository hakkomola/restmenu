<?php
// items/create.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

// Kategorileri getir
$stmt = $pdo->prepare('SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC');
$stmt->execute([$restaurantId]);
$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $categoryId = $_POST['category_id'] ?? null;

    if (!$categoryId) {
        $message = 'Lütfen bir kategori seçin.';
    }

    if (!$message) {
        // Menü öğesini ekle
        $stmt = $pdo->prepare('INSERT INTO MenuItems (CategoryID, RestaurantID, MenuName, Description, Price) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$categoryId, $restaurantId, $name, $description, $price]);
        $menuItemId = $pdo->lastInsertId();

        // Eğer resim yüklendiyse kaydet
        if (!empty($_FILES['images']['name'][0])) {
            $uploadsDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                $fileName = time() . '_' . basename($_FILES['images']['name'][$index]);
                $target = $uploadsDir . $fileName;

                if (move_uploaded_file($tmpName, $target)) {
                    // Burada kesinlikle 'uploads/' ekliyoruz
                    $imageUrl = 'uploads/' . $fileName;
                    $stmt = $pdo->prepare('INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)');
                    $stmt->execute([$menuItemId, $imageUrl]);
                }
            }
        }

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
    <title>Yeni Menü Öğesi Ekle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 700px;">
    <h2 class="mb-4">Yeni Menü Öğesi Ekle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Menü Adı</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Açıklama</label>
            <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
            <label>Fiyat (₺)</label>
            <input type="number" step="0.01" name="price" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Kategori</label>
            <select name="category_id" class="form-select" required>
                <option value="">Seçiniz</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Resimler (birden fazla seçebilirsiniz)</label>
            <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
        </div>
        <button class="btn btn-success">Kaydet</button>
        <a href="list.php" class="btn btn-secondary">Geri</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
