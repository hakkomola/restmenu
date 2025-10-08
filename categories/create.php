<?php
// categories/create.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
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

if (!$languages) {
    $error = 'Bu restoran için en az bir dil tanımlanmalı (Ayarlar > Diller).';
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $trans = $_POST['trans'] ?? [];
    $imagePath = null;

    // Varsayılan dilde ad zorunlu
    $defaultName = trim($trans[$defaultLang]['name'] ?? '');
    if ($defaultName === '') {
        $error = strtoupper($defaultLang) . ' dilinde kategori adı zorunludur.';
    }

    // Görsel yükleme işlemi
    if (!$error && !empty($_FILES['image']['name'])) {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $target = $uploadsDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imagePath = 'uploads/' . $fileName;
        } else {
            $error = 'Resim yüklenirken hata oluştu.';
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Ana kategori (fallback için CategoryName hala tutulur)
            $stmt = $pdo->prepare('INSERT INTO MenuCategories (RestaurantID, CategoryName, ImageURL) VALUES (?, ?, ?)');
            $stmt->execute([$restaurantId, $defaultName, $imagePath]);
            $categoryId = (int)$pdo->lastInsertId();

            // Çevirileri kaydet
            $ins = $pdo->prepare("
                INSERT INTO MenuCategoryTranslations (CategoryID, LangCode, Name, Description)
                VALUES (:cid, :lang, :name, NULL)
                ON DUPLICATE KEY UPDATE Name = VALUES(Name)
            ");
            foreach ($languages as $L) {
                $langCode = $L['LangCode'];
                $name = trim($trans[$langCode]['name'] ?? '');
                if ($name !== '') {
                    $ins->execute([
                        ':cid'  => $categoryId,
                        ':lang' => $langCode,
                        ':name' => $name
                    ]);
                }
            }

            $pdo->commit();
            header('Location: list.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Kayıt sırasında hata: ' . $e->getMessage();
        }
    }
}
// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>




<div class="container mt-5" style="max-width: 600px;">
    <h2 class="mb-4">Yeni Kategori Ekle</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($languages): ?>
    <form action="create.php" method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <?php foreach ($languages as $i => $L): ?>
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
                                   class="form-control" <?= $L['IsDefault'] ? 'required' : '' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-3 mt-3">
                <label>Resim Yükle</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
        </div>

        <div class="card-footer d-flex gap-2">
            <button class="btn btn-success">Kaydet</button>
            <a href="list.php" class="btn btn-secondary">Geri</a>
        </div>
    </form>
    <?php endif; ?>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
