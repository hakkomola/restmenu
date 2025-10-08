<?php
// categories/edit.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$id = $_GET['id'] ?? null;
if (!$id) header('Location: list.php');

// Kategori bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
$stmt->execute([$id, $restaurantId]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: list.php');
    exit;
}

$message = '';
$error = '';

/** Restoranın desteklediği diller */
$stmt = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, rl.LangCode
");
$stmt->execute([$restaurantId]);
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

/** Mevcut çeviriler */
$trs = $pdo->prepare("SELECT LangCode, Name FROM MenuCategoryTranslations WHERE CategoryID=?");
$trs->execute([$id]);
$translations = [];
foreach ($trs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $translations[$row['LangCode']] = $row['Name'];
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trans = $_POST['trans'] ?? [];
    $defaultName = trim($trans[$defaultLang]['name'] ?? '');

    if ($defaultName === '') {
        $error = strtoupper($defaultLang) . ' dilinde kategori adı zorunludur.';
    }

    if (!$error) {
        $imageUpdated = false;
        $imageUrl = $category['ImageURL'];

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
                $imageUpdated = true;
            } else {
                $error = 'Resim yüklenirken hata oluştu.';
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Ana tabloyu güncelle (fallback)
                if ($imageUpdated) {
                    $upd = $pdo->prepare("UPDATE MenuCategories SET CategoryName=?, ImageURL=? WHERE CategoryID=? AND RestaurantID=?");
                    $upd->execute([$defaultName, $imageUrl, $id, $restaurantId]);
                } else {
                    $upd = $pdo->prepare("UPDATE MenuCategories SET CategoryName=? WHERE CategoryID=? AND RestaurantID=?");
                    $upd->execute([$defaultName, $id, $restaurantId]);
                }

                // Çevirileri güncelle (upsert)
                $ins = $pdo->prepare("
                    INSERT INTO MenuCategoryTranslations (CategoryID, LangCode, Name)
                    VALUES (:cid, :lang, :name)
                    ON DUPLICATE KEY UPDATE Name = VALUES(Name)
                ");
                foreach ($languages as $L) {
                    $lc = $L['LangCode'];
                    $name = trim($trans[$lc]['name'] ?? '');
                    if ($name !== '') {
                        $ins->execute([
                            ':cid'  => $id,
                            ':lang' => $lc,
                            ':name' => $name
                        ]);
                    } else {
                        // Boş gönderildiyse sil
                        $del = $pdo->prepare("DELETE FROM MenuCategoryTranslations WHERE CategoryID=? AND LangCode=?");
                        $del->execute([$id, $lc]);
                    }
                }

                $pdo->commit();
                header('Location: list.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Güncelleme hatası: ' . $e->getMessage();
            }
        }
    }
}

// Mevcut resim
$imageURL = $category['ImageURL'] ?? '';

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="container mt-5" style="max-width: 600px;">
    <h2 class="mb-4">Kategori Düzenle</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <?php foreach ($languages as $L): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $L['IsDefault'] ? 'active' : '' ?>"
                                data-bs-toggle="tab" data-bs-target="#tab-<?= htmlspecialchars($L['LangCode']) ?>"
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
                            <label>Kategori Adı (<?= strtoupper($lc) ?>)</label>
                            <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]"
                                   class="form-control"
                                   value="<?= htmlspecialchars($translations[$lc] ?? (($lc === $defaultLang) ? $category['CategoryName'] : '')) ?>"
                                   <?= $L['IsDefault'] ? 'required' : '' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
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
        </div>

        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">Güncelle</button>
            <a href="list.php" class="btn btn-secondary">Geri</a>
        </div>
    </form>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>