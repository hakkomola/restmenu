<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId  = $_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null;
$pageTitle     = "Yeni Alt Kategori Oluştur";

$message = '';
$error   = '';

// 🔹 Kategoriler (aktif şubeye göre)
$sql = "
  SELECT c.CategoryID, c.CategoryName
  FROM MenuCategories c
  WHERE c.RestaurantID = ?
";
$params = [$restaurantId];
if (!empty($currentBranch)) {
  $sql .= " AND (c.BranchID = ? OR c.BranchID IS NULL)";
  $params[] = $currentBranch;
}
$sql .= " ORDER BY c.SortOrder ASC, c.CategoryName ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Seçili kategori
$selectedCategoryId = $_GET['category_id'] ?? null;

// 🔹 Diller
$stmtLang = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, rl.LangCode
");
$stmtLang->execute([$restaurantId]);
$languages = $stmtLang->fetchAll(PDO::FETCH_ASSOC);

if (!$languages) {
  $error = 'Bu restoran için en az bir dil tanımlanmalı (Ayarlar > Diller).';
}

$defaultLang = null;
foreach ($languages as $L) {
  if ($L['IsDefault']) { $defaultLang = $L['LangCode']; break; }
}
if (!$defaultLang && $languages) $defaultLang = $languages[0]['LangCode'];

// 🔹 Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
  $categoryId = $_POST['category_id'] ?? null;
  $trans = $_POST['trans'] ?? [];

  $defaultName = trim($trans[$defaultLang]['name'] ?? '');
  if (!$categoryId) $error = 'Lütfen bir kategori seçiniz.';
  elseif ($defaultName === '') $error = strtoupper($defaultLang) . ' dilinde alt kategori adı zorunludur.';

  // 🔸 Görsel yükleme
  $imagePath = null;
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

      // 🔹 Ana kayıt
      $stmt = $pdo->prepare("
        INSERT INTO SubCategories (RestaurantID, BranchID, CategoryID, SubCategoryName, ImageURL)
        VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->execute([$restaurantId, $currentBranch ?: null, $categoryId, $defaultName, $imagePath]);
      $subId = (int)$pdo->lastInsertId();

      // 🔹 Çeviriler
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
      header('Location: list.php?category_id=' . urlencode($categoryId));
      exit;
    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Kayıt hatası: ' . $e->getMessage();
    }
  }
}

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($languages): ?>
  <form method="post" enctype="multipart/form-data" class="bo-form card p-4 shadow-sm">

    <!-- 🔹 Kategori seçimi -->
    <div class="mb-3">
      <label class="form-label">Üst Kategori</label>
      <select name="category_id" class="form-select" required>
        <option value="">Kategori Seçiniz</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['CategoryID'] ?>" <?= $cat['CategoryID']==$selectedCategoryId?'selected':'' ?>>
            <?= htmlspecialchars($cat['CategoryName']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 🔹 Dil sekmeleri -->
    <ul class="nav nav-tabs mb-3" role="tablist">
      <?php foreach ($languages as $i => $L): ?>
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
          <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" class="form-control" <?= $L['IsDefault'] ? 'required' : '' ?>>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 🔹 Görsel yükleme -->
    <div class="mb-3 mt-2">
      <label class="form-label">Resim Yükle</label>
      <input type="file" name="image" id="imageInput" class="form-control" accept="image/*">
      <div id="imagePreview" class="mt-3 position-relative d-none" style="max-width: 200px;">
        <img id="previewImg" src="#" class="img-thumbnail" style="width:100%; height:auto;">
        <button type="button" id="removeImage"
          class="btn btn-sm btn-danger position-absolute top-0 end-0"
          style="border-radius:50%; width:28px; height:28px; line-height:14px;">
          &times;
        </button>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <a href="list.php<?= $selectedCategoryId ? '?category_id=' . $selectedCategoryId : '' ?>" class="btn btn-outline-secondary me-2">İptal</a>
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('imageInput');
  const previewDiv = document.getElementById('imagePreview');
  const previewImg = document.getElementById('previewImg');
  const removeBtn = document.getElementById('removeImage');

  input.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewImg.src = e.target.result;
        previewDiv.classList.remove('d-none');
      };
      reader.readAsDataURL(file);
    }
  });

  removeBtn.addEventListener('click', function () {
    input.value = '';
    previewDiv.classList.add('d-none');
    previewImg.src = '#';
  });
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
