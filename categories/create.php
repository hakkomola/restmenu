<?php
// categories/create.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';
if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';

    $imagePath = null;
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
        $stmt = $pdo->prepare('INSERT INTO MenuCategories (RestaurantID, CategoryName, ImageURL) VALUES (?, ?, ?)');
        $stmt->execute([$restaurantId, $name, $imagePath]);
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
    <title>Yeni Kategori Ekle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 600px;">
    <h2 class="mb-4">Yeni Kategori Ekle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="create.php" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Kategori Adı</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Resim Yükle</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <button class="btn btn-success">Kaydet</button>
        <a href="list.php" class="btn btn-secondary">Geri</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
