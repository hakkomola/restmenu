<?php
// subcategories/create.php
session_start();
require_once __DIR__ . '/../db.php';

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

// Restoranın desteklediği diller
$stmtLang = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, rl.LangCode
");
$stmtLang->execute([$restaurantId]);
$languages = $stmtLang->fetchAll(PDO::FETCH_ASSOC);

$defaultLang = null;
foreach ($languages as $L) {
    if ($L['IsDefault']) {
        $defaultLang = $L['LangCode'];
        break;
    }
}
if (!$defaultLang && $languages) {
    $defaultLang = $languages[0]['LangCode'];
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'] ?? null;
    $trans = $_POST['trans'] ?? [];

    $defaultName = trim($trans[$defaultLang]['name'] ?? '');
    if (!$categoryId) $errors[] = 'Kategori seçmelisiniz!';
    if ($defaultName === '') $errors[] = strtoupper($defaultLang) . ' dilinde alt kategori adı boş olamaz!';

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

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Ana tabloya ekle (fallback için)
            $stmt = $pdo->prepare("INSERT INTO SubCategories (CategoryID, RestaurantID, SubCategoryName, ImageURL) VALUES (?, ?, ?, ?)");
            $stmt->execute([$categoryId, $restaurantId, $defaultName, $imgPath]);
            $subId = (int)$pdo->lastInsertId();

            // Çevirileri ekle
            $ins = $pdo->prepare("
                INSERT INTO SubCategoryTranslations (SubCategoryID, LangCode, Name, Description)
                VALUES (:sid, :lang, :name, NULL)
                ON DUPLICATE KEY UPDATE Name = VALUES(Name)
            ");
            foreach ($languages as $L) {
                $lc = $L['LangCode'];
                $name = trim($trans[$lc]['name'] ?? '');
                if ($name !== '') {
                    $ins->execute([
                        ':sid'  => $subId,
                        ':lang' => $lc,
                        ':name' => $name
                    ]);
                }
            }

            $pdo->commit();
            header("Location: list.php?category_id=".$categoryId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Kayıt hatası: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/navbar.php';
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
<div class="container mt-5" style="max-width: 700px;">
    <h2>Yeni Alt Kategori Ekle</h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach($errors as $e) echo "<div>$e</div>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($languages): ?>
    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">

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

            <ul class="nav nav-tabs mb-3" role="tablist">
                <?php foreach ($languages as $L): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $L['IsDefault'] ? 'active' : '' ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-<?= htmlspecialchars($L['LangCode']) ?>"
                                type="button" role="tab">
                            <?= strtoupper($L['LangCode']) ?> - <?= htmlspecialchars($L['LangName']) ?>
                            <?php if ($L['IsDefault']): ?><span class="badge text-bg-secondary ms-1">Varsayılan</span><?php endif; ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content">
                <?php foreach ($languages as $L): $lc = $L['LangCode']; ?>
                    <div class="tab-pane fade <?= $L['IsDefault'] ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($lc) ?>">
                        <div class="mb-3">
                            <label for="subName_<?= htmlspecialchars($lc) ?>" class="form-label">Alt Kategori Adı (<?= strtoupper($lc) ?>)</label>
                            <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" id="subName_<?= htmlspecialchars($lc) ?>"
                                   class="form-control" <?= $L['IsDefault'] ? 'required' : '' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-3 mt-3">
                <label for="image" class="form-label">Resim (opsiyonel)</label>
                <input type="file" name="image" id="image" class="form-control" accept="image/*">
            </div>

        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-success">Kaydet</button>
            <a href="list.php?category_id=<?= $selectedCategoryId ?>" class="btn btn-secondary">İptal</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
