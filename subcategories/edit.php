<?php
// subcategories/edit.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: list.php');
    exit;
}

// Alt kategori bilgisi
$stmt = $pdo->prepare("
    SELECT sc.*, mc.CategoryName 
    FROM SubCategories sc 
    JOIN MenuCategories mc ON sc.CategoryID = mc.CategoryID 
    WHERE sc.SubCategoryID = ? AND sc.RestaurantID = ?
");
$stmt->execute([$id, $restaurantId]);
$sub = $stmt->fetch();

if (!$sub) {
    header('Location: list.php');
    exit;
}

// Tüm ana kategorileri getir
$catStmt = $pdo->prepare("SELECT CategoryID, CategoryName FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC");
$catStmt->execute([$restaurantId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';

// Resim silme işlemi
if (isset($_GET['delete_image']) && $_GET['delete_image'] == 1 && $sub['ImageURL']) {
    $imgPath = __DIR__ . '/../' . $sub['ImageURL'];
    if (file_exists($imgPath)) unlink($imgPath);

    $update = $pdo->prepare("UPDATE SubCategories SET ImageURL=NULL WHERE SubCategoryID=?");
    $update->execute([$id]);

    header("Location: edit.php?id=$id");
    exit;
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = $_POST['category_id'] ?? $sub['CategoryID'];

    if ($name === '') {
        $message = 'Alt kategori adı boş olamaz.';
    }

    if (!$message) {
        $imageUrl = $sub['ImageURL'];

        // Resim değiştirildiyse yükle
        if (!empty($_FILES['image']['name'])) {
            $uploadsDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $target = $uploadsDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                // Önce eski resmi sil
                if ($imageUrl && file_exists(__DIR__ . '/../' . $imageUrl)) {
                    unlink(__DIR__ . '/../' . $imageUrl);
                }
                $imageUrl = 'uploads/' . $fileName;
            }
        }

        $update = $pdo->prepare("UPDATE SubCategories SET SubCategoryName=?, ImageURL=?, CategoryID=? WHERE SubCategoryID=?");
        $update->execute([$name, $imageUrl, $categoryId, $id]);

        header('Location: list.php?cat=' . $categoryId);
        exit;
    }
}

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alt Kategori Düzenle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.img-container { position: relative; display: inline-block; margin-bottom: 10px; }
.img-container img { height: 100px; object-fit: cover; display: block; border-radius: 5px; }
.img-container .delete-btn {
    position: absolute;
    top: -5px;
    right: -5px;
    background: red;
    color: white;
    border-radius: 50%;
    border: none;
    width: 22px;
    height: 22px;
    line-height: 18px;
    font-weight: bold;
    text-align: center;
    cursor: pointer;
}
</style>
</head>
<body>
<div class="container mt-5" style="max-width:600px;">
    <h2>Alt Kategori Düzenle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- Ana kategori seçimi -->
        <div class="mb-3">
            <label>Ana Kategori</label>
            <select name="category_id" class="form-select" required>
                <option value="">Seçiniz</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>" <?= $cat['CategoryID'] == $sub['CategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Alt kategori adı -->
        <div class="mb-3">
            <label>Alt Kategori Adı</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($sub['SubCategoryName']) ?>" required>
        </div>

        <!-- Mevcut resim -->
        <div class="mb-3">
            <label>Mevcut Resim</label><br>
            <?php if ($sub['ImageURL']): ?>
                <div class="img-container">
                    <img src="../<?= htmlspecialchars($sub['ImageURL']) ?>">
                    <a href="edit.php?id=<?= $id ?>&delete_image=1" class="delete-btn" onclick="return confirm('Bu resmi silmek istiyor musunuz?')">&times;</a>
                </div>
            <?php else: ?>
                <p>Resim yok</p>
            <?php endif; ?>
        </div>

        <!-- Yeni resim -->
        <div class="mb-3">
            <label>Yeni Resim Yükle (Opsiyonel)</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>

        <button class="btn btn-success">Güncelle</button>
        <a href="list.php?cat=<?= $sub['CategoryID'] ?>" class="btn btn-secondary">Geri</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
