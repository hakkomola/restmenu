<?php
// subcategories/create.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

// Kategoriler
$stmtCats = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID=? ORDER BY CategoryName ASC");
$stmtCats->execute([$restaurantId]);
$categories = $stmtCats->fetchAll();

// Liste sayfasından gelen kategori ID (default seçili olacak)
$selectedCategoryId = $_GET['category_id'] ?? null;

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'] ?? null;
    $subName = trim($_POST['sub_name'] ?? '');
    
    // Resim işlemi
    $imgPath = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'uploads/subcategory_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], '../' . $filename)) {
            $imgPath = $filename;
        } else {
            $errors[] = 'Resim yüklenemedi!';
        }
    }

    if (!$categoryId) $errors[] = 'Kategori seçmelisiniz!';
    if (!$subName) $errors[] = 'Alt kategori adı boş olamaz!';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO SubCategories (CategoryID, RestaurantID, SubCategoryName, ImageURL) VALUES (?, ?, ?, ?)");
        $stmt->execute([$categoryId, $restaurantId, $subName, $imgPath]);
        $success = true;
        // Redirect back to list sayfası seçili kategori ile
        header("Location: list.php?category_id=".$categoryId);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yeni Alt Kategori Ekle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Yeni Alt Kategori Ekle</h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach($errors as $e) echo "<div>$e</div>"; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="categorySelect" class="form-label">Kategori</label>
            <select name="category_id" id="categorySelect" class="form-select" required>
                <option value="">-- Kategori Seçin --</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>" <?= ($cat['CategoryID']==$selectedCategoryId)?'selected':'' ?>>
                        <?= htmlspecialchars($cat['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="subName" class="form-label">Alt Kategori Adı</label>
            <input type="text" name="sub_name" id="subName" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">Resim (opsiyonel)</label>
            <input type="file" name="image" id="image" class="form-control" accept="image/*">
        </div>

        <button type="submit" class="btn btn-success">Kaydet</button>
        <a href="list.php?category_id=<?= $selectedCategoryId ?>" class="btn btn-secondary">İptal</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
