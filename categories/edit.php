<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$id = (int)($_GET['id'] ?? 0);
if (!$id) header('Location: list.php');

// 🔹 Kategori bilgisi
$stmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
$stmt->execute([$id, $restaurantId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$category) {
    header('Location: list.php');
    exit;
}

// 🔹 Şubeler
$stmtB = $pdo->prepare("SELECT BranchID, BranchName FROM RestaurantBranches WHERE RestaurantID=? ORDER BY BranchName ASC");
$stmtB->execute([$restaurantId]);
$branches = $stmtB->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Diller
$stmt = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, rl.LangCode
");
$stmt->execute([$restaurantId]);
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Varsayılan dil
$defaultLang = null;
foreach ($languages as $L) {
    if ($L['IsDefault']) { $defaultLang = $L['LangCode']; break; }
}
if (!$defaultLang && $languages) $defaultLang = $languages[0]['LangCode'];

// 🔹 Mevcut çeviriler
$trs = $pdo->prepare("SELECT LangCode, Name FROM MenuCategoryTranslations WHERE CategoryID=?");
$trs->execute([$id]);
$translations = [];
foreach ($trs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $translations[$row['LangCode']] = $row['Name'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchId = $_POST['BranchID'] !== '' ? (int)$_POST['BranchID'] : null;
    $trans = $_POST['trans'] ?? [];
    $defaultName = trim($trans[$defaultLang]['name'] ?? '');
    $deleteImage = isset($_POST['delete_image']); // 🔸 X ile silme kontrolü

    if ($defaultName === '') {
        $error = strtoupper($defaultLang) . ' dilinde kategori adı zorunludur.';
    }

    if (!$error) {
        $imageUpdated = false;
        $imageUrl = $category['ImageURL'];

        // 🔸 Resim silme
        if ($deleteImage && $imageUrl) {
            if (file_exists(__DIR__ . '/../' . $imageUrl)) unlink(__DIR__ . '/../' . $imageUrl);
            $imageUrl = null;
            $imageUpdated = true;
        }

        // 🔸 Yeni resim yükleme
        if (!empty($_FILES['image']['name'])) {
            $uploadsDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $target = $uploadsDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
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

                // 🔹 Güncelleme
                if ($imageUpdated) {
                    $upd = $pdo->prepare("
                        UPDATE MenuCategories
                        SET BranchID=?, CategoryName=?, ImageURL=?
                        WHERE CategoryID=? AND RestaurantID=?
                    ");
                    $upd->execute([$branchId, $defaultName, $imageUrl, $id, $restaurantId]);
                } else {
                    $upd = $pdo->prepare("
                        UPDATE MenuCategories
                        SET BranchID=?, CategoryName=?
                        WHERE CategoryID=? AND RestaurantID=?
                    ");
                    $upd->execute([$branchId, $defaultName, $id, $restaurantId]);
                }

                // 🔹 Çeviriler (upsert)
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
                        $pdo->prepare("DELETE FROM MenuCategoryTranslations WHERE CategoryID=? AND LangCode=?")
                            ->execute([$id, $lc]);
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

$pageTitle = "Kategori Düzenle";
include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="bo-form card p-4 shadow-sm">

    <!-- Şube seçimi -->
    <div class="mb-3">
      <label class="form-label">Şube</label>
      <select name="BranchID" class="form-select">
        <option value="">Tüm Şubeler</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $category['BranchID'] == $b['BranchID'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['BranchName']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Dil sekmeleri -->
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
            <label class="form-label">Kategori Adı (<?= strtoupper($lc) ?>)</label>
            <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]"
                   class="form-control"
                   value="<?= htmlspecialchars($translations[$lc] ?? (($lc === $defaultLang) ? $category['CategoryName'] : '')) ?>"
                   <?= $L['IsDefault'] ? 'required' : '' ?>>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Resim yükleme / silme -->
    <div class="mb-3 mt-2">
      <label class="form-label">Kategori Görseli</label>
      <input type="file" name="image" id="imageInput" class="form-control" accept="image/*">

      <div id="imagePreview" class="mt-3 position-relative <?= $category['ImageURL'] ? '' : 'd-none' ?>" style="max-width: 200px;">
        <img id="previewImg" src="../<?= htmlspecialchars($category['ImageURL'] ?? '') ?>" class="img-thumbnail" style="width:100%; height:auto;">
        <button type="button" id="removeImage" 
                class="btn btn-sm btn-danger position-absolute top-0 end-0"
                style="border-radius:50%; width:28px; height:28px; line-height:14px;">
          &times;
        </button>
      </div>
      <input type="hidden" name="delete_image" id="deleteImageFlag" value="">
    </div>

    <div class="d-flex justify-content-end">
      <a href="list.php" class="btn btn-outline-secondary me-2">İptal</a>
      <button type="submit" class="btn btn-primary">Güncelle</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('imageInput');
  const previewDiv = document.getElementById('imagePreview');
  const previewImg = document.getElementById('previewImg');
  const removeBtn = document.getElementById('removeImage');
  const deleteFlag = document.getElementById('deleteImageFlag');

  input.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewImg.src = e.target.result;
        previewDiv.classList.remove('d-none');
        deleteFlag.value = ''; // yeni resim seçilince silme bayrağını temizle
      };
      reader.readAsDataURL(file);
    }
  });

  removeBtn.addEventListener('click', function () {
    input.value = '';
    previewDiv.classList.add('d-none');
    previewImg.src = '#';
    deleteFlag.value = '1'; // 🔹 form gönderildiğinde resmi sil
  });
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
