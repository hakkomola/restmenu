<?php
// items/edit.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: list.php");
    exit;
}

// Menü öğesini getir
$stmt = $pdo->prepare("SELECT * FROM MenuItems WHERE MenuItemID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    header("Location: list.php");
    exit;
}

// Ana kategorileri getir
$catStmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC");
$catStmt->execute([$restaurantId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Her ana kategoriye ait alt kategorileri bir diziye al
$subCategoriesMap = [];
foreach ($categories as $cat) {
    $subStmt = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID = ? ORDER BY SubCategoryName ASC");
    $subStmt->execute([$cat['CategoryID']]);
    $subCategoriesMap[$cat['CategoryID']] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Seçili alt kategoriye göre seçili ana kategori belirle
$selectedSubCategoryId = $item['SubCategoryID'] ?? null;
$selectedCategoryId = null;
if ($selectedSubCategoryId) {
    foreach ($subCategoriesMap as $catId => $subs) {
        foreach ($subs as $sub) {
            if ($sub['SubCategoryID'] == $selectedSubCategoryId) {
                $selectedCategoryId = $catId;
                break 2;
            }
        }
    }
}

// Güncelleme işlemi
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $subCategoryId = $_POST['sub_category_id'] ?? null;

    if (!$subCategoryId) {
        $message = 'Lütfen bir alt kategori seçin.';
    }

    if (!$message) {
        $update = $pdo->prepare("UPDATE MenuItems SET SubCategoryID=?, MenuName=?, Description=?, Price=? WHERE MenuItemID=? AND RestaurantID=?");
        $update->execute([$subCategoryId, $name, $description, $price, $id, $restaurantId]);

        header("Location: list.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü Öğesi Düzenle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 700px;">
    <h2 class="mb-4">Menü Öğesi Düzenle</h2>

    <?php if($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Menü Adı</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['MenuName']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Açıklama</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($item['Description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label>Fiyat (₺)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($item['Price']) ?>" required>
        </div>

        <!-- Ana kategori seçimi -->
        <div class="mb-3">
            <label>Ana Kategori</label>
            <select name="category_id" id="categorySelect" class="form-select" required>
                <option value="">Seçiniz</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>" <?= $cat['CategoryID']==$selectedCategoryId?'selected':'' ?>>
                        <?= htmlspecialchars($cat['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Alt kategori seçimi -->
        <div class="mb-3">
            <label>Alt Kategori</label>
            <select name="sub_category_id" id="subCategorySelect" class="form-select" required>
                <option value="">Seçiniz</option>
                <?php
                if ($selectedCategoryId && isset($subCategoriesMap[$selectedCategoryId])) {
                    foreach($subCategoriesMap[$selectedCategoryId] as $sub) {
                        $selected = $sub['SubCategoryID']==$selectedSubCategoryId ? 'selected' : '';
                        echo '<option value="'.$sub['SubCategoryID'].'" '.$selected.'>'.htmlspecialchars($sub['SubCategoryName']).'</option>';
                    }
                }
                ?>
            </select>
        </div>

        <button class="btn btn-primary">Güncelle</button>
        <a href="list.php" class="btn btn-secondary">Geri</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(function(){
    // Ana kategori değişince alt kategori dropdown'ı güncelle
    const subCategoriesMap = <?= json_encode($subCategoriesMap) ?>;

    $('#categorySelect').change(function(){
        let catId = $(this).val();
        let html = '<option value="">Seçiniz</option>';
        if(catId && subCategoriesMap[catId]){
            subCategoriesMap[catId].forEach(sub => {
                html += `<option value="${sub.SubCategoryID}">${sub.SubCategoryName}</option>`;
            });
        }
        $('#subCategorySelect').html(html);
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
