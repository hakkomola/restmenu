<?php
// categories/edit.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) header('Location: list.php');

// Kategori bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
$stmt->execute([$id, $_SESSION['restaurant_id']]);
$category = $stmt->fetch();

// Güncelleme işlemi
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $imageUpdated = false;

    // Yeni resim yükleme
    if (!empty($_FILES['image']['name'])) {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        $fileName = time().'_'.basename($_FILES['image']['name']);
        $target = $uploadsDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            // Eski resmi sil
            if (!empty($category['ImageURL']) && file_exists(__DIR__ . '/../' . $category['ImageURL'])) {
                unlink(__DIR__ . '/../' . $category['ImageURL']);
            }

            $imageUrl = 'uploads/' . $fileName;
            $update = $pdo->prepare("UPDATE MenuCategories SET CategoryName=?, ImageURL=? WHERE CategoryID=? AND RestaurantID=?");
            $update->execute([$name, $imageUrl, $id, $_SESSION['restaurant_id']]);
            $imageUpdated = true;
        }
    } else {
        $update = $pdo->prepare("UPDATE MenuCategories SET CategoryName=? WHERE CategoryID=? AND RestaurantID=?");
        $update->execute([$name, $id, $_SESSION['restaurant_id']]);
    }

    header('Location: list.php');
    exit;
}

// Mevcut resim
$imageURL = $category['ImageURL'] ?? '';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Kategori Düzenle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.img-thumb-container { position: relative; display: inline-block; margin-right: 10px; }
.img-thumb-container img { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; }
.img-thumb-container a { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; padding: 2px 6px; text-decoration: none; font-weight: bold; }
</style>
</head>
<body>
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4">Kategori Düzenle</h2>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Kategori Adı</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['CategoryName']) ?>" required>
        </div>

        <?php if($imageURL): ?>
        <div class="mb-3">
            <label>Mevcut Resim</label>
            <div class="img-thumb-container">
                <img src="../<?= htmlspecialchars($imageURL) ?>" alt="Kategori Resmi">
                <a href="delete_image.php?id=<?= $category['CategoryID'] ?>" 
                   onclick="return confirm('Bu resmi silmek istediğinize emin misiniz?')">&times;</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-3">
            <label>Yeni Resim Yükle</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>

        <button class="btn btn-primary">Güncelle</button>
        <a href="list.php" class="btn btn-secondary">Geri</a>
    </form>
</div>
</body>
</html>
