<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId  = $_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null;
$id = (int)($_GET['id'] ?? 0);
if (!$id) header('Location: list.php');

// 🔹 Alt kategori bilgisi
$stmt = $pdo->prepare("
  SELECT sc.*, mc.CategoryName 
  FROM SubCategories sc
  JOIN MenuCategories mc ON sc.CategoryID = mc.CategoryID
  WHERE sc.SubCategoryID=? AND sc.RestaurantID=?
");
$stmt->execute([$id, $restaurantId]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) {
  header('Location: list.php');
  exit;
}

// 🔹 Orijinal ve mevcut şube adları
$origBranchName = '(Tüm Şubeler)';
if (!empty($sub['BranchID'])) {
  $stmtB = $pdo->prepare("SELECT BranchName FROM RestaurantBranches WHERE BranchID=? AND RestaurantID=?");
  $stmtB->execute([$sub['BranchID'], $restaurantId]);
  $origBranchName = $stmtB->fetchColumn() ?: $origBranchName;
}
$currBranchName = '(Tüm Şubeler)';
if (!empty($currentBranch)) {
  $stmtB = $pdo->prepare("SELECT BranchName FROM RestaurantBranches WHERE BranchID=? AND RestaurantID=?");
  $stmtB->execute([$currentBranch, $restaurantId]);
  $currBranchName = $stmtB->fetchColumn() ?: $currBranchName;
}

// 🔹 Aktif şube için kategoriler
$sqlCats = "
  SELECT c.CategoryID, c.CategoryName
  FROM MenuCategories c
  WHERE c.RestaurantID=?
";
$params = [$restaurantId];
if (!empty($currentBranch)) {
  $sqlCats .= " AND (c.BranchID = ? OR c.BranchID IS NULL)";
  $params[] = $currentBranch;
}
$sqlCats .= " ORDER BY c.SortOrder ASC, c.CategoryName ASC";
$stmtCats = $pdo->prepare($sqlCats);
$stmtCats->execute($params);
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Diller
$stmtLang = $pdo->prepare("
  SELECT rl.LangCode, rl.IsDefault, l.LangName
  FROM RestaurantLanguages rl
  JOIN Languages l ON l.LangCode = rl.LangCode
  WHERE rl.RestaurantID=?
  ORDER BY rl.IsDefault DESC, rl.LangCode
");
$stmtLang->execute([$restaurantId]);
$languages = $stmtLang->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Varsayılan dil
$defaultLang = null;
foreach ($languages as $L) {
  if ($L['IsDefault']) { $defaultLang = $L['LangCode']; break; }
}
if (!$defaultLang && $languages) $defaultLang = $languages[0]['LangCode'];

// 🔹 Çeviriler
$trs = $pdo->prepare("SELECT LangCode, Name FROM SubCategoryTranslations WHERE SubCategoryID=?");
$trs->execute([$id]);
$translations = [];
foreach ($trs->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $translations[$row['LangCode']] = $row['Name'];
}

$error = '';

// 🔹 POST işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $categoryId  = $_POST['category_id'] ?? $sub['CategoryID'];
  $trans       = $_POST['trans'] ?? [];
  $deleteImage = isset($_POST['delete_image']);
  $defaultName = trim($trans[$defaultLang]['name'] ?? '');

  if (!$categoryId) $error = 'Lütfen bir kategori seçiniz.';
  elseif ($defaultName === '') $error = strtoupper($defaultLang) . ' dilinde alt kategori adı zorunludur.';

  if (!$error) {
    $imageUrl = $sub['ImageURL'];
    $imageUpdated = false;

    // 🔸 Görsel silme
    if ($deleteImage && $imageUrl) {
      if (file_exists(__DIR__ . '/../' . $imageUrl)) unlink(__DIR__ . '/../' . $imageUrl);
      $imageUrl = null;
      $imageUpdated = true;
    }

    // 🔸 Yeni görsel yükleme
    if (!empty($_FILES['image']['name'])) {
      $uploadsDir = __DIR__ . '/../uploads/';
      if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

      $fileName = time() . '_' . basename($_FILES['image']['name']);
      $target = $uploadsDir . $fileName;

      if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        if (!empty($sub['ImageURL']) && file_exists(__DIR__ . '/../' . $sub['ImageURL'])) {
          unlink(__DIR__ . '/../' . $sub['ImageURL']);
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
            UPDATE SubCategories
            SET CategoryID=?, BranchID=?, SubCategoryName=?, ImageURL=?
            WHERE SubCategoryID=? AND RestaurantID=?
          ");
          $upd->execute([$categoryId, $currentBranch ?: null, $defaultName, $imageUrl, $id, $restaurantId]);
        } else {
          $upd = $pdo->prepare("
            UPDATE SubCategories
            SET CategoryID=?, BranchID=?, SubCategoryName=?
            WHERE SubCategoryID=? AND RestaurantID=?
          ");
          $upd->execute([$categoryId, $currentBranch ?: null, $defaultName, $id, $restaurantId]);
        }

        // 🔹 Çeviriler
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
            $pdo->prepare("DELETE FROM SubCategoryTranslations WHERE SubCategoryID=? AND LangCode=?")
                ->execute([$id, $lc]);
          }
        }

        $pdo->commit();
        header('Location: list.php?category_id=' . urlencode($categoryId));
        exit;
      } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Güncelleme hatası: ' . $e->getMessage();
      }
    }
  }
}

$pageTitle = "Alt Kategori Düzenle";
include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="bo-form card p-4 shadow-sm" id="subForm">
    <!-- 🔹 Gizli alanlar (şube değişiklik kontrolü) -->
    <input type="hidden" name="original_branch" value="<?= htmlspecialchars($sub['BranchID'] ?? '') ?>">
    <input type="hidden" name="current_branch" value="<?= htmlspecialchars($currentBranch ?? '') ?>">
    <input type="hidden" name="original_branch_name" value="<?= htmlspecialchars($origBranchName) ?>">
    <input type="hidden" name="current_branch_name" value="<?= htmlspecialchars($currBranchName) ?>">

    <!-- 🔹 Üst kategori seçimi -->
    <div class="mb-3">
      <label class="form-label">Üst Kategori</label>
      <select name="category_id" class="form-select" required>
        <option value="">Kategori Seçiniz</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['CategoryID'] ?>" <?= $cat['CategoryID']==$sub['CategoryID']?'selected':'' ?>>
            <?= htmlspecialchars($cat['CategoryName']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 🔹 Dil sekmeleri -->
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
            <label class="form-label">Alt Kategori Adı (<?= strtoupper($lc) ?>)</label>
            <input type="text"
                   name="trans[<?= htmlspecialchars($lc) ?>][name]"
                   class="form-control"
                   value="<?= htmlspecialchars($translations[$lc] ?? (($lc === $defaultLang) ? $sub['SubCategoryName'] : '')) ?>"
                   <?= $L['IsDefault'] ? 'required' : '' ?>>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- 🔹 Görsel -->
    <div class="mb-3 mt-2">
      <label class="form-label">Alt Kategori Görseli</label>
      <input type="file" name="image" id="imageInput" class="form-control" accept="image/*">

      <div id="imagePreview" class="mt-3 position-relative <?= $sub['ImageURL'] ? '' : 'd-none' ?>" style="max-width: 200px;">
        <img id="previewImg" src="../<?= htmlspecialchars($sub['ImageURL'] ?? '') ?>" class="img-thumbnail" style="width:100%; height:auto;">
        <button type="button" id="removeImage" 
                class="btn btn-sm btn-danger position-absolute top-0 end-0"
                style="border-radius:50%; width:28px; height:28px; line-height:14px;">
          &times;
        </button>
      </div>
      <input type="hidden" name="delete_image" id="deleteImageFlag" value="">
    </div>

    <div class="d-flex justify-content-end">
      <a href="list.php?category_id=<?= $sub['CategoryID'] ?>" class="btn btn-outline-secondary me-2">İptal</a>
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
  const form = document.getElementById('subForm');

  // 🔹 Görsel önizleme
  input.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewImg.src = e.target.result;
        previewDiv.classList.remove('d-none');
        deleteFlag.value = '';
      };
      reader.readAsDataURL(file);
    }
  });

  removeBtn.addEventListener('click', function () {
    input.value = '';
    previewDiv.classList.add('d-none');
    previewImg.src = '#';
    deleteFlag.value = '1';
  });

  // 🔹 Şube değişiklik uyarısı (isimli)
  form.addEventListener('submit', function (e) {
    const origId = document.querySelector('input[name="original_branch"]').value;
    const currId = document.querySelector('input[name="current_branch"]').value;
    const origName = document.querySelector('input[name="original_branch_name"]').value;
    const currName = document.querySelector('input[name="current_branch_name"]').value;

    if (origId !== currId) {
      const confirmChange = confirm(
        `Bu alt kategori şu anda "${origName}" şubesine ait.\n\n` +
        `Şube değiştirildi: ${origName} ➜ ${currName}\n\n` +
        `Bu alt kategori yeni şubeye taşınacak. Emin misiniz?`
      );
      if (!confirmChange) e.preventDefault();
    }
  });
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
