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

// Tüm ana kategoriler
$catStmt = $pdo->prepare("SELECT CategoryID, CategoryName FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC");
$catStmt->execute([$restaurantId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

/** Restoranın desteklediği diller */
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
    if ($L['IsDefault']) { $defaultLang = $L['LangCode']; break; }
}
if (!$defaultLang && $languages) $defaultLang = $languages[0]['LangCode'];

/** Mevcut çeviriler */
$trs = $pdo->prepare("SELECT LangCode, Name FROM SubCategoryTranslations WHERE SubCategoryID=?");
$trs->execute([$id]);
$translations = [];
foreach ($trs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $translations[$row['LangCode']] = $row['Name'];
}

$message = '';

/** Resim silme işlemi */
if (isset($_GET['delete_image']) && $_GET['delete_image'] == 1 && $sub['ImageURL']) {
    $imgPath = __DIR__ . '/../' . $sub['ImageURL'];
    if (file_exists($imgPath)) unlink($imgPath);
    $update = $pdo->prepare("UPDATE SubCategories SET ImageURL=NULL WHERE SubCategoryID=?");
    $update->execute([$id]);
    header("Location: edit.php?id=$id");
    exit;
}

/** Güncelleme işlemi */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trans = $_POST['trans'] ?? [];
    $categoryId = $_POST['category_id'] ?? $sub['CategoryID'];
    $defaultName = trim($trans[$defaultLang]['name'] ?? '');

    if ($defaultName === '') {
        $message = strtoupper($defaultLang) . ' dilinde alt kategori adı boş olamaz.';
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
                if ($imageUrl && file_exists(__DIR__ . '/../' . $imageUrl)) {
                    unlink(__DIR__ . '/../' . $imageUrl);
                }
                $imageUrl = 'uploads/' . $fileName;
            }
        }

        try {
            $pdo->beginTransaction();

            // Ana tabloyu güncelle (fallback)
            $upd = $pdo->prepare("UPDATE SubCategories SET SubCategoryName=?, ImageURL=?, CategoryID=? WHERE SubCategoryID=?");
            $upd->execute([$defaultName, $imageUrl, $categoryId, $id]);

            // Çevirileri güncelle
            $ins = $pdo->prepare("
                INSERT INTO SubCategoryTranslations (SubCategoryID, LangCode, Name)
                VALUES (:sid, :lang, :name)
                ON DUPLICATE KEY UPDATE Name = VALUES(Name)
            ");
            foreach ($languages as $L) {
                $lc = $L['LangCode'];
                $name = trim($trans[$lc]['name'] ?? '');
                if ($name !== '') {
                    $ins->execute([
                        ':sid'  => $id,
                        ':lang' => $lc,
                        ':name' => $name
                    ]);
                } else {
                    // Boş gönderildiyse sil
                    $del = $pdo->prepare("DELETE FROM SubCategoryTranslations WHERE SubCategoryID=? AND LangCode=?");
                    $del->execute([$id, $lc]);
                }
            }

            $pdo->commit();
            header('Location: list.php?cat=' . $categoryId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }
}

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

?>



<div class="container mt-5" style="max-width:700px;">
    <h2>Alt Kategori Düzenle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">
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

            <!-- Çoklu dil sekmeleri -->
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
                            <label>Alt Kategori Adı (<?= strtoupper($lc) ?>)</label>
                            <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($translations[$lc] ?? (($lc === $defaultLang) ? $sub['SubCategoryName'] : '')) ?>"
                                   <?= $L['IsDefault'] ? 'required' : '' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
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
        </div>

        <div class="card-footer d-flex gap-2">
            <button class="btn btn-success">Güncelle</button>
            <a href="list.php?cat=<?= $sub['CategoryID'] ?>" class="btn btn-secondary">Geri</a>
        </div>
    </form>
</div>



<?php include __DIR__ . '/../includes/footer.php'; ?>
