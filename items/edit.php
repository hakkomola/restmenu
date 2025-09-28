<?php
session_start();
if (!isset($_SESSION['restaurant_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: list.php");
    exit;
}

// Menü öğesini getir
$stmt = $pdo->prepare("SELECT * FROM menuitems WHERE MenuItemID = ? AND RestaurantID = ?");
$stmt->execute([$id, $_SESSION['restaurant_id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    header("Location: list.php");
    exit;
}

// Kategoriler
$cats = $pdo->prepare("SELECT * FROM menucategories WHERE RestaurantID=? ORDER BY CategoryName ASC");
$cats->execute([$_SESSION['restaurant_id']]);
$categories = $cats->fetchAll(PDO::FETCH_ASSOC);

// Mevcut resimler
$imgs = $pdo->prepare("SELECT * FROM menuimages WHERE MenuItemID=?");
$imgs->execute([$id]);
$images = $imgs->fetchAll(PDO::FETCH_ASSOC);

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';

    $update = $pdo->prepare("UPDATE MenuItems SET MenuName=?, Price=?, Description=?, CategoryID=? WHERE MenuItemID=? AND RestaurantID=?");
    $update->execute([$name, $price, $description, $category_id, $id, $_SESSION['restaurant_id']]);

    header("Location: list.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü Öğesi Düzenle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.img-preview { max-height: 150px; object-fit: cover; }
@media (max-width: 576px) {
    .img-preview { max-height: 120px; }
}
</style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4 text-center">Menü Öğesi Düzenle</h2>
    <form method="post" enctype="multipart/form-data" id="editForm">
        <div class="mb-3">
            <label class="form-label">Adı</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['MenuName']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Fiyat (₺)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($item['Price']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Açıklama</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($item['Description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <select name="category_id" class="form-select" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>" <?= $cat['CategoryID']==$item['CategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['CategoryName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Yeni Resim Ekle</label>
            <input type="file" id="newImage" multiple class="form-control" accept="image/*">
        </div>
        <div class="mb-3">
            <label class="form-label">Mevcut Resimler</label>
            <div class="row" id="imageContainer">
                <?php foreach ($images as $img):
                    $imgUrl = $img['ImageURL'];
                    ?>
                    <div class="col-6 col-sm-4 col-md-3 text-center mb-3" data-id="<?= $img['MenuImageID'] ?>">
                        <img src="../<?= htmlspecialchars($imgUrl) ?>" class="img-fluid rounded img-preview mb-2">
                        <button type="button" class="btn btn-sm btn-danger delete-btn w-100" data-id="<?= $img['MenuImageID'] ?>">Sil</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-2">Güncelle</button>
        <a href="list.php" class="btn btn-secondary w-100">İptal</a>
    </form>
</div>

<script>
// Resim yükleme (AJAX)
document.getElementById('newImage').addEventListener('change', function(){
    let files = this.files;
    let formData = new FormData();
    for(let i=0;i<files.length;i++){
        formData.append('images[]', files[i]);
    }
    formData.append('item_id', <?= $id ?>);

    fetch('upload_image.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            data.images.forEach(img => {
                let div = document.createElement('div');
                div.className = 'col-6 col-sm-4 col-md-3 text-center mb-3';
                div.dataset.id = img.id;
                div.innerHTML = `<img src="../${img.url}" class="img-fluid rounded img-preview mb-2">
                                 <button type="button" class="btn btn-sm btn-danger delete-btn w-100" data-id="${img.id}">Sil</button>`;
                document.getElementById('imageContainer').appendChild(div);
            });
        } else {
            alert('Resim yüklenemedi!');
        }
    });
});

// Resim silme işlemi
document.getElementById('imageContainer').addEventListener('click', function(e){
    if(e.target.classList.contains('delete-btn')){
        if(confirm('Resmi silmek istediğinize emin misiniz?')){
            let imgId = e.target.dataset.id;
            fetch('delete_image.php', {
                method: 'POST',
                headers: { 'Content-Type':'application/x-www-form-urlencoded' },
                body: 'id='+imgId+'&item_id=<?= $id ?>'
            })
            .then(res=>res.text())
            .then(res=>{
                e.target.parentElement.remove();
            });
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
