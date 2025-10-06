<?php
// items/create.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $subCategoryId = $_POST['sub_category_id'] ?? null;
    $options = $_POST['options'] ?? []; // Yeni: seçenekler (OptionName / Price)

    if (!$subCategoryId) {
        $message = 'Lütfen bir alt kategori seçin.';
    }

    if (!$message) {
        // Menü öğesini ekle
        $stmt = $pdo->prepare('INSERT INTO MenuItems (SubCategoryID, RestaurantID, MenuName, Description, Price) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$subCategoryId, $restaurantId, $name, $description, $price]);
        $menuItemId = $pdo->lastInsertId();

        // Seçenekleri kaydet
        if (!empty($options['name'])) {
            $optStmt = $pdo->prepare('INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, SortOrder) VALUES (?, ?, ?, ?)');
            foreach ($options['name'] as $i => $optName) {
                $optName = trim($optName);
                $optPrice = floatval($options['price'][$i] ?? 0);
                if ($optName !== '') {
                    $optStmt->execute([$menuItemId, $optName, $optPrice, $i]);
                }
            }
        }

        // Eğer resim yüklendiyse kaydet
        if (!empty($_FILES['images']['name'][0])) {
            $uploadsDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                $fileName = time() . '_' . basename($_FILES['images']['name'][$index]);
                $target = $uploadsDir . $fileName;

                if (move_uploaded_file($tmpName, $target)) {
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
include __DIR__ . '/../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yeni Menü Öğesi Ekle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.option-row { display: flex; gap: 10px; margin-bottom: 8px; }
.option-row input { flex: 1; }
</style>
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
            <label>Varsayılan Fiyat (₺)</label>
            <input type="number" step="0.01" name="price" class="form-control">
            <div class="form-text">Bu fiyat seçenek belirtilmediğinde geçerlidir.</div>
        </div>

        <!-- 🔸 Seçenekler -->
        <div class="mb-3">
            <label>Farklı Seçenekler (örnek: 33cl / 70cl veya 1 porsiyon / 1,5 porsiyon)</label>
            <div id="optionsContainer"></div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addOptionBtn">+ Yeni Seçenek Ekle</button>
        </div>

        <!-- Ana kategori seçimi -->
        <div class="mb-3">
            <label>Ana Kategori</label>
            <select name="category_id" id="categorySelect" class="form-select" required>
                <option value="">Seçiniz</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Alt kategori seçimi -->
        <div class="mb-3">
            <label>Alt Kategori</label>
            <select name="sub_category_id" id="subCategorySelect" class="form-select" required>
                <option value="">Seçiniz</option>
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

    // 🔹 Dinamik seçenek ekleme
    $('#addOptionBtn').click(function(){
        const optionHtml = `
            <div class="option-row">
                <input type="text" name="options[name][]" class="form-control" placeholder="Seçenek adı (örnek: 1 Porsiyon)">
                <input type="number" step="0.01" name="options[price][]" class="form-control" placeholder="Fiyat (₺)">
                <button type="button" class="btn btn-outline-danger removeOptionBtn">×</button>
            </div>`;
        $('#optionsContainer').append(optionHtml);
    });

    // 🔹 Seçenek silme
    $(document).on('click', '.removeOptionBtn', function(){
        $(this).closest('.option-row').remove();
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
