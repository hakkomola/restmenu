<?php
// items/edit.php
session_start();
require_once __DIR__ . '/../db.php';

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

        // Yeni resimleri kaydet
        if (!empty($_FILES['images']['name'][0])) {
            $uploadsDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                $fileName = time().'_'.basename($_FILES['images']['name'][$index]);
                $target = $uploadsDir . $fileName;

                if (move_uploaded_file($tmpName, $target)) {
                    $imageUrl = 'uploads/' . $fileName;
                    $stmt = $pdo->prepare('INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)');
                    $stmt->execute([$id, $imageUrl]);
                }
            }
        }

        header("Location: list.php");
        exit;
    }
}

// Mevcut resimleri getir
$imgStmt = $pdo->prepare("SELECT * FROM MenuImages WHERE MenuItemID=?");
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/navbar.php';

?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü Öğesi Düzenle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.img-container { display:flex; flex-wrap:wrap; gap:10px; }
.img-container .img-box { position:relative; width:100px; }
.img-container .img-box img { width:100%; height:100px; object-fit:cover; border-radius:5px; }
.img-container .img-box .remove-btn { position:absolute; top:0; right:0; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; text-align:center; line-height:18px; cursor:pointer; }
</style>
</head>
<body>
<div class="container mt-5" style="max-width: 700px;">
    <h2 class="mb-4">Menü Öğesi Düzenle</h2>

    <?php if($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="editForm">
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

        <!-- Mevcut ve yeni resimler -->
        <div class="mb-3">
            <label>Resimler</label>
            <div class="img-container" id="imageContainer">
                <?php foreach($images as $img): ?>
                    <div class="img-box">
                        <img src="../<?= htmlspecialchars($img['ImageURL']) ?>">
                        <a href="delete_image.php?id=<?= $img['MenuImageID'] ?>&menu_id=<?= $id ?>" class="remove-btn" onclick="return confirm('Bu resmi silmek istediğinize emin misiniz?')">&times;</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label>Yeni Resim Ekle</label>
            <input type="file" name="images[]" class="form-control" accept="image/*" multiple id="newImages">
            <small class="text-muted">Birden fazla resim seçebilirsiniz.</small>
        </div>

        <button class="btn btn-primary">Güncelle</button>
        <a href="list.php" class="btn btn-secondary">Geri</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(function(){
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

    // Yeni resim seçildiğinde hemen preview
    $('#newImages').on('change', function(){
        const container = $('#imageContainer');
        const files = this.files;
        for(let i=0; i<files.length; i++){
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e){
                const imgBox = $('<div class="img-box"></div>');
                imgBox.append('<img src="'+e.target.result+'">');
                container.append(imgBox);
            }
            reader.readAsDataURL(file);
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
