<?php
// items/edit.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId  = (int)$_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null; // NOT NULL bekleniyor
$pageTitle     = "Menü Öğesi Düzenle";

// === Parametre & Ürün ===
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($itemId <= 0) { header('Location: list.php'); exit; }

$qItem = $pdo->prepare("
  SELECT mi.*, sc.SubCategoryID AS SCID, sc.CategoryID AS CATID
  FROM MenuItems mi
  LEFT JOIN SubCategories sc ON sc.SubCategoryID = mi.SubCategoryID
  WHERE mi.MenuItemID = ? AND mi.RestaurantID = ?
");
$qItem->execute([$itemId, $restaurantId]);
$item = $qItem->fetch(PDO::FETCH_ASSOC);
if (!$item) { header('Location: list.php'); exit; }

$itemBranchId       = (int)$item['BranchID'];
$currentCategoryId  = (int)($item['CATID'] ?? 0);
$currentSubId       = (int)($item['SCID']  ?? 0);

// === Şube adları (uyarı metni için)
$branchNameById = [];
$brStmt = $pdo->prepare("SELECT BranchID, BranchName FROM RestaurantBranches WHERE RestaurantID = ?");
$brStmt->execute([$restaurantId]);
foreach ($brStmt->fetchAll(PDO::FETCH_ASSOC) as $br) {
  $branchNameById[(int)$br['BranchID']] = $br['BranchName'];
}
$origBranchName = $branchNameById[$itemBranchId]  ?? 'Bilinmiyor';
$currBranchName = $branchNameById[(int)$currentBranch] ?? 'Bilinmiyor';

// === Kategoriler (aktif şube)
$sqlCats = "
  SELECT c.CategoryID, c.CategoryName
  FROM MenuCategories c
  WHERE c.RestaurantID = ?
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

// === Alt kategoriler (aktif şube + kategori bağımlı)
$subMap = [];
foreach ($categories as $cat) {
  $stmtSub = $pdo->prepare("
    SELECT s.SubCategoryID, s.SubCategoryName
    FROM SubCategories s
    WHERE s.RestaurantID=? AND s.CategoryID=?
    ORDER BY s.SortOrder ASC, s.SubCategoryName ASC
  ");
  $stmtSub->execute([$restaurantId, $cat['CategoryID']]);
  $subMap[$cat['CategoryID']] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
}

// Eğer mevcut ürünün kategorisi aktif şubede değilse, en azından seçiliyi gösterebilmek için bir defalığına ekle
if ($currentCategoryId && !array_key_exists($currentCategoryId, array_column($categories, null, 'CategoryID'))) {
  $oneCat = $pdo->prepare("SELECT CategoryID, CategoryName FROM MenuCategories WHERE CategoryID=? AND RestaurantID=?");
  $oneCat->execute([$currentCategoryId, $restaurantId]);
  if ($cRow = $oneCat->fetch(PDO::FETCH_ASSOC)) {
    $categories[] = $cRow;
    // alt kategorilerini de ekle
    $stmtSub = $pdo->prepare("
      SELECT s.SubCategoryID, s.SubCategoryName
      FROM SubCategories s
      WHERE s.RestaurantID=? AND s.CategoryID=?
      ORDER BY s.SortOrder ASC, s.SubCategoryName ASC
    ");
    $stmtSub->execute([$restaurantId, $currentCategoryId]);
    $subMap[$currentCategoryId] = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
  }
}

// === Diller
$stmtLang = $pdo->prepare("
  SELECT rl.LangCode, rl.IsDefault, l.LangName
  FROM RestaurantLanguages rl
  JOIN Languages l ON l.LangCode = rl.LangCode
  WHERE rl.RestaurantID = ?
  ORDER BY rl.IsDefault DESC, rl.LangCode
");
$stmtLang->execute([$restaurantId]);
$languages = $stmtLang->fetchAll(PDO::FETCH_ASSOC);
if (!$languages) $languages = [['LangCode'=>'tr','IsDefault'=>1,'LangName'=>'Türkçe']];
$defaultLang = null;
foreach ($languages as $L) { if (!empty($L['IsDefault'])) { $defaultLang = $L['LangCode']; break; } }
if (!$defaultLang) $defaultLang = $languages[0]['LangCode'];

// === MenuItemTranslations FK kolonu (MenuItemID/ItemID) emniyeti
$fkCol = 'MenuItemID';
try {
  $colCheck = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'MenuItemTranslations'
      AND COLUMN_NAME IN ('MenuItemID','ItemID')
    LIMIT 1
  ");
  $colCheck->execute();
  $found = $colCheck->fetchColumn();
  if ($found) $fkCol = $found;
} catch (Exception $e) {}

// === Ürün çevirileri
$trItmStmt = $pdo->prepare("SELECT LangCode, Name, Description FROM MenuItemTranslations WHERE $fkCol = ?");
$trItmStmt->execute([$itemId]);
$itemTr = [];
foreach ($trItmStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $itemTr[$r['LangCode']] = ['name' => $r['Name'], 'desc' => $r['Description']];
}

// === Seçenekler + çevirileri
$optsStmt = $pdo->prepare("
  SELECT OptionID, OptionName, Price, IsDefault, SortOrder
  FROM MenuItemOptions
  WHERE MenuItemID = ?
  ORDER BY SortOrder, OptionID
");
$optsStmt->execute([$itemId]);
$options = $optsStmt->fetchAll(PDO::FETCH_ASSOC);
$optIds  = array_column($options, 'OptionID');

$optTr = []; // $optTr[LangCode][OptionID] = name
if ($optIds) {
  $in = implode(',', array_fill(0, count($optIds), '?'));
  $oTrStmt = $pdo->prepare("SELECT OptionID, LangCode, Name FROM MenuItemOptionTranslations WHERE OptionID IN ($in)");
  $oTrStmt->execute($optIds);
  foreach ($oTrStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $optTr[$r['LangCode']][$r['OptionID']] = $r['Name'];
  }
}

// === MenuImages PK kolonu
$imgPkCol = 'ImageID';
try {
  $c1 = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'MenuImages'
      AND COLUMN_NAME IN ('ImageID','MenuImageID','ID')
    ORDER BY FIELD(COLUMN_NAME,'ImageID','MenuImageID','ID')
    LIMIT 1
  ");
  $c1->execute();
  $found = $c1->fetchColumn();
  if ($found) { $imgPkCol = $found; }
  else {
    $c2 = $pdo->prepare("
      SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'MenuImages'
        AND CONSTRAINT_NAME = 'PRIMARY'
      LIMIT 1
    ");
    $c2->execute();
    $pk = $c2->fetchColumn();
    if ($pk) $imgPkCol = $pk;
  }
} catch (Exception $e) {}

// === Mevcut resimler
$imgStmt = $pdo->prepare("SELECT {$imgPkCol} AS ImgPK, ImageURL FROM MenuImages WHERE MenuItemID = ? ORDER BY {$imgPkCol}");
$imgStmt->execute([$itemId]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// === Tek resim silme
if (isset($_GET['delete_image'])) {
  $imgId = (int)$_GET['delete_image'];
  if ($imgId > 0) {
    $one = $pdo->prepare("SELECT {$imgPkCol} AS ImgPK, ImageURL FROM MenuImages WHERE {$imgPkCol}=? AND MenuItemID=?");
    $one->execute([$imgId, $itemId]);
    $img = $one->fetch(PDO::FETCH_ASSOC);
    if ($img) {
      $path = __DIR__ . '/../' . ltrim($img['ImageURL'], '/');
      if (is_file($path)) { @unlink($path); }
      $del = $pdo->prepare("DELETE FROM MenuImages WHERE {$imgPkCol}=? AND MenuItemID=?");
      $del->execute([$imgId, $itemId]);
    }
  }
  header("Location: edit.php?id=".$itemId);
  exit;
}

$error = '';

// === GÜNCELLE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $categoryId    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : $currentCategoryId;
  $subCategoryId = isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : $currentSubId;

  $trans   = $_POST['trans'] ?? [];
  $defName = trim($trans[$defaultLang]['name'] ?? '');
  $defDesc = trim($trans[$defaultLang]['desc'] ?? '');

  // seçenekler
  $optDefNames     = $_POST['options_def']['name']  ?? []; // [OptionID] => name
  $optDefPrices    = $_POST['options_def']['price'] ?? []; // [OptionID] => price
  $selectedDefault = $_POST['options_def']['IsDefault'] ?? null; // "23" | "new-0"

  $optDeletes   = $_POST['options_delete'] ?? [];          // [OptionID...]
  $optTrPost    = $_POST['options_tr']     ?? [];          // options_tr[lang]['name'][OptionID]

  // yeni seçenekler
  $optNewNames  = $_POST['options_new']['name']  ?? [];
  $optNewPrices = $_POST['options_new']['price'] ?? [];
  $optNewTrPost = $_POST['options_new_tr']       ?? [];    // [lang]['name'][]

  // şube değişim onayı
  $branchChangeAck = isset($_POST['branch_change_ack']) && $_POST['branch_change_ack'] === '1';

  // default seçimi ayrıştır
  $optNewDefaultIndex = null;  // "new-#" -> #
  $optDefIsDefault    = null;  // mevcut OptionID
  if ($selectedDefault !== null && $selectedDefault !== '') {
    if (is_string($selectedDefault) && strpos($selectedDefault, 'new-') === 0) {
      $optNewDefaultIndex = substr($selectedDefault, 4);
    } else {
      $optDefIsDefault = (int)$selectedDefault;
    }
  }

  // validasyon
  if ($defName === '')       $error = strtoupper($defaultLang) . ' dilinde ürün adı zorunludur.';
  elseif ($subCategoryId<=0) $error = 'Lütfen bir alt kategori seçin.';

  // en az 1 seçenek ve bir default şartı (mevcut+yeniler içinde)
  $hasAnyOption = false;
  if ($options) $hasAnyOption = true; // mevcut varsa
  foreach ($optNewNames as $nm) { if (trim($nm) !== '') { $hasAnyOption = true; break; } }
  if (!$hasAnyOption) $error = $error ?: 'En az bir seçenek olmalı.';

  if (!$error) {
    try {
      $pdo->beginTransaction();

      // 1) Şube değişiyorsa güvenli güncelleme (server-side garanti)
      $finalBranchId = $itemBranchId;
      if ((int)$currentBranch !== (int)$itemBranchId) {
        if ($branchChangeAck) {
          $finalBranchId = (int)$currentBranch;
        } else {
          // JS confirm kaçarsa: hata ver ve rollback
          throw new Exception("Şube değişikliği onaylanmadı. Kayıt yapılmadı.");
        }
      }

      // 2) Ürün (fiyat yok; sadece adı, açıklama, SubCategoryID, BranchID)
      $upd = $pdo->prepare("
        UPDATE MenuItems
        SET MenuName=?, Description=?, SubCategoryID=?, BranchID=?
        WHERE MenuItemID=? AND RestaurantID=?
      ");
      $upd->execute([$defName, ($defDesc !== '' ? $defDesc : null), $subCategoryId, $finalBranchId, $itemId, $restaurantId]);

      // 3) Ürün çevirileri (upsert/temizle)
      $insIt = $pdo->prepare("
        INSERT INTO MenuItemTranslations ($fkCol, LangCode, Name, Description)
        VALUES (:iid, :lang, :name, :desc)
        ON DUPLICATE KEY UPDATE Name = VALUES(Name), Description = VALUES(Description)
      ");
      $delIt = $pdo->prepare("DELETE FROM MenuItemTranslations WHERE $fkCol = ? AND LangCode = ?");
      foreach ($languages as $L) {
        $lc = $L['LangCode'];
        if ($lc === $defaultLang) continue;
        $nm = trim($trans[$lc]['name'] ?? '');
        $ds = trim($trans[$lc]['desc'] ?? '');
        if ($nm !== '' || $ds !== '') {
          $insIt->execute([':iid'=>$itemId, ':lang'=>$lc, ':name'=>($nm !== '' ? $nm : $defName), ':desc'=>($ds !== '' ? $ds : null)]);
        } else {
          $delIt->execute([$itemId, $lc]);
        }
      }

      // 4) Silinecek seçenekler
      if (!empty($optDeletes)) {
        $delO = $pdo->prepare("DELETE FROM MenuItemOptions WHERE OptionID = ? AND MenuItemID = ?");
        foreach ($optDeletes as $oid) { $oid = (int)$oid; if ($oid>0) $delO->execute([$oid, $itemId]); }
        $optDeletes = array_values(array_filter(array_map('intval', (array)$optDeletes)));
        if ($optDeletes) {
          $in = implode(',', array_fill(0, count($optDeletes), '?'));
          $delOTr = $pdo->prepare("DELETE FROM MenuItemOptionTranslations WHERE OptionID IN ($in)");
          $delOTr->execute($optDeletes);
        }
      }

      // 4.5) Yeni bir satır default seçilmişse, mevcutları 0'a çek
      if ($optNewDefaultIndex !== null) {
        $pdo->prepare("UPDATE MenuItemOptions SET IsDefault = 0 WHERE MenuItemID = ?")->execute([$itemId]);
      }

      // 5) Mevcut seçenekleri güncelle
      if (!empty($optDefNames)) {
        $updO = $pdo->prepare("UPDATE MenuItemOptions SET OptionName=?, Price=?, IsDefault=?, SortOrder=? WHERE OptionID=? AND MenuItemID=?");
        $sort = 0;
        foreach ($optDefNames as $oid => $nm) {
          $oid = (int)$oid; if ($oid<=0) continue;
          $nm  = trim($nm ?? '');
          $prc = isset($optDefPrices[$oid]) ? (float)$optDefPrices[$oid] : 0.0;
          $isDefOpt = ($optNewDefaultIndex !== null) ? 0 : (($oid === $optDefIsDefault) ? 1 : 0);
          $updO->execute([$nm, $prc, $isDefOpt, $sort++, $oid, $itemId]);
        }
      }

      // 6) Mevcut seçenek çevirileri
      if (!empty($optTrPost)) {
        $insOtr = $pdo->prepare("
          INSERT INTO MenuItemOptionTranslations (OptionID, LangCode, Name)
          VALUES (:oid, :lang, :name)
          ON DUPLICATE KEY UPDATE Name = VALUES(Name)
        ");
        $delOtr = $pdo->prepare("DELETE FROM MenuItemOptionTranslations WHERE OptionID=? AND LangCode=?");
        foreach ($languages as $L) {
          $lc = $L['LangCode'];
          if ($lc === $defaultLang) continue;
          $arr = $optTrPost[$lc]['name'] ?? [];
          foreach ($arr as $oid => $nm) {
            $oid = (int)$oid; if ($oid<=0) continue;
            $nm = trim($nm ?? '');
            if ($nm !== '') $insOtr->execute([':oid'=>$oid, ':lang'=>$lc, ':name'=>$nm]);
            else           $delOtr->execute([$oid, $lc]);
          }
        }
      }

      // 7) Yeni seçenekler
      $newOids = [];
      if (!empty($optNewNames)) {
        $insO = $pdo->prepare("INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, IsDefault, SortOrder) VALUES (?, ?, ?, ?, ?)");
        $existingCount = (int)$pdo->query("SELECT COUNT(*) FROM MenuItemOptions WHERE MenuItemID = ".$itemId)->fetchColumn();
        foreach ($optNewNames as $i => $nm) {
          $nm = trim($nm ?? ''); if ($nm==='') continue;
          $prc = isset($optNewPrices[$i]) ? (float)$optNewPrices[$i] : 0.0;
          $isDefOption = ($optNewDefaultIndex !== null && (string)$i === (string)$optNewDefaultIndex) ? 1 : 0;
          $insO->execute([$itemId, $nm, $prc, $isDefOption, $existingCount + $i]);
          $newOids[$i] = (int)$pdo->lastInsertId();
        }
      }
      if (!empty($newOids)) {
        $insNewTr = $pdo->prepare("
          INSERT INTO MenuItemOptionTranslations (OptionID, LangCode, Name)
          VALUES (:oid, :lang, :name)
          ON DUPLICATE KEY UPDATE Name = VALUES(Name)
        ");
        foreach ($languages as $L) {
          $lc = $L['LangCode']; if ($lc === $defaultLang) continue;
          $arr = $optNewTrPost[$lc]['name'] ?? [];
          foreach ($newOids as $i => $oid) {
            $nm = trim($arr[$i] ?? '');
            if ($nm==='' || !$oid) continue;
            $insNewTr->execute([':oid'=>$oid, ':lang'=>$lc, ':name'=>$nm]);
          }
        }
      }

      // 8) Yeni resimler
      if (!empty($_FILES['images']['name'][0])) {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        $insImg = $pdo->prepare("INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)");
        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
          if (!$tmpName) continue;
          $fileName = time() . '_' . basename($_FILES['images']['name'][$i]);
          $target   = $uploadsDir . $fileName;
          if (move_uploaded_file($tmpName, $target)) {
            $insImg->execute([$itemId, 'uploads/' . $fileName]);
          }
        }
      }

      $pdo->commit();
      header('Location: list.php');
      exit;

    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Güncelleme hatası: ' . htmlspecialchars($e->getMessage());
    }
  }
}

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3" style="max-width: 980px;">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="bo-form card p-4 shadow-sm" id="itemEditForm"
        data-original-branch-id="<?= (int)$itemBranchId ?>"
        data-current-branch-id="<?= (int)$currentBranch ?>"
        data-original-branch-name="<?= htmlspecialchars($origBranchName) ?>"
        data-current-branch-name="<?= htmlspecialchars($currBranchName) ?>">

    <!-- Kategori / Alt Kategori -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Ana Kategori</label>
        <select name="category_id" id="categorySelect" class="form-select" required>
          <option value="">Seçiniz</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['CategoryID'] ?>" <?= ($c['CategoryID']==$currentCategoryId?'selected':'') ?>>
              <?= htmlspecialchars($c['CategoryName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Alt Kategori</label>
        <select name="sub_category_id" id="subCategorySelect" class="form-select" required>
          <option value="">Seçiniz</option>
          <?php foreach ($subMap[$currentCategoryId] ?? [] as $sc): ?>
            <option value="<?= $sc['SubCategoryID'] ?>" <?= ($sc['SubCategoryID']==$currentSubId?'selected':'') ?>>
              <?= htmlspecialchars($sc['SubCategoryName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Dil sekmeleri -->
    <ul class="nav nav-tabs mb-3" role="tablist">
      <?php foreach ($languages as $L): ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= !empty($L['IsDefault']) ? 'active' : '' ?>"
                  data-bs-toggle="tab"
                  data-bs-target="#tab-<?= htmlspecialchars($L['LangCode']) ?>"
                  type="button" role="tab">
            <?= strtoupper($L['LangCode']) ?> - <?= htmlspecialchars($L['LangName']) ?>
            <?php if (!empty($L['IsDefault'])): ?><span class="badge text-bg-secondary ms-1">Varsayılan</span><?php endif; ?>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content">
      <?php foreach ($languages as $L):
        $lc = $L['LangCode']; $isDef = !empty($L['IsDefault']);
        $nameVal = $isDef ? ($item['MenuName'] ?? '') : ($itemTr[$lc]['name'] ?? '');
        $descVal = $isDef ? ($item['Description'] ?? '') : ($itemTr[$lc]['desc'] ?? '');
      ?>
      <div class="tab-pane fade <?= $isDef ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($lc) ?>">
        <div class="mb-3">
          <label class="form-label">Menü Adı (<?= strtoupper($lc) ?>)</label>
          <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" class="form-control"
                 value="<?= htmlspecialchars($nameVal) ?>" <?= $isDef ? 'required' : '' ?>>
        </div>
        <div class="mb-3">
          <label class="form-label">Açıklama (<?= strtoupper($lc) ?>)</label>
          <textarea name="trans[<?= htmlspecialchars($lc) ?>][desc]" class="form-control" rows="2"><?= htmlspecialchars($descVal) ?></textarea>
        </div>

        <!-- Seçenekler -->
        <div class="border rounded p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="m-0">Seçenekler (<?= strtoupper($lc) ?>)</h6>
            <?php if ($isDef): ?>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addNewOptionBtn">+ Yeni Seçenek</button>
            <?php else: ?>
              <span class="text-muted">Yeni satır ekleme/silme <b>varsayılan dil</b> sekmesinden yapılır.</span>
            <?php endif; ?>
          </div>

          <div id="options-<?= htmlspecialchars($lc) ?>-container">
            <?php if ($isDef): ?>
              <?php foreach ($options as $op): ?>
                <div class="option-row d-flex flex-wrap align-items-center gap-2 mb-2" data-oid="<?= (int)$op['OptionID'] ?>">
                  <input type="text"
                         name="options_def[name][<?= (int)$op['OptionID'] ?>]"
                         value="<?= htmlspecialchars($op['OptionName']) ?>"
                         class="form-control"
                         style="min-width:240px"
                         placeholder="Seçenek adı">
                  <input type="number" step="0.01"
                         name="options_def[price][<?= (int)$op['OptionID'] ?>]"
                         value="<?= htmlspecialchars($op['Price']) ?>"
                         class="form-control"
                         style="max-width:140px"
                         placeholder="Fiyat (₺)">
                  <div class="form-check">
                    <input class="form-check-input" type="radio"
                           name="options_def[IsDefault]"
                           value="<?= (int)$op['OptionID'] ?>"
                           id="optdef_exist_<?= (int)$op['OptionID'] ?>"
                           <?= ($op['IsDefault'] == 1 ? 'checked' : '') ?>>
                    <label class="form-check-label" for="optdef_exist_<?= (int)$op['OptionID'] ?>">Varsayılan</label>
                  </div>
                  <button type="button" class="btn btn-outline-danger removeExistingBtn" data-oid="<?= (int)$op['OptionID'] ?>">&times;</button>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach ($options as $op): ?>
                <div class="option-row mb-2" data-oid="<?= (int)$op['OptionID'] ?>">
                  <input type="text" class="form-control"
                         name="options_tr[<?= htmlspecialchars($lc) ?>][name][<?= (int)$op['OptionID'] ?>]"
                         value="<?= htmlspecialchars($optTr[$lc][$op['OptionID']] ?? '') ?>"
                         placeholder="Seçenek adı (çeviri: <?= strtoupper($lc) ?>)">
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <?php if ($isDef): ?>
            <div class="form-text">Var olan satırları düzenleyebilir veya silebilirsiniz. Yeni satırlar eklendiğinde diğer dillerde çeviri alanları otomatik oluşur.</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <hr class="my-4">

    <!-- Mevcut Resimler -->
    <div class="mb-2">
      <label class="form-label">Mevcut Resimler</label>
      <?php if (!empty($images)): ?>
        <div class="image-grid mb-2">
          <?php foreach ($images as $im): ?>
            <div class="image-card">
              <img src="../<?= htmlspecialchars(ltrim($im['ImageURL'],'/')) ?>" alt="Görsel">
              <a class="del" href="edit.php?id=<?= (int)$itemId ?>&delete_image=<?= (int)$im['ImgPK'] ?>"
                 onclick="return confirm('Bu resmi silmek istiyor musunuz?')">&times;</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-muted">Resim yok.</div>
      <?php endif; ?>
    </div>

    <!-- Yeni Resim Yükle + Önizleme -->
    <div class="mb-3">
      <label class="form-label">Yeni Resim Yükle (birden fazla seçebilirsiniz)</label>
      <input type="file" id="newImagesInput" name="images[]" class="form-control" accept="image/*" multiple>
      <div class="form-text">Seçtikleriniz kaydetmeden önce aşağıda önizlenir. İstemediğiniz resmi sağ üstteki × ile kaldırabilirsiniz.</div>
      <div id="newImagesPreview" class="image-grid mt-2"></div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-primary" id="submitBtn">Güncelle</button>
      <a href="list.php" class="btn btn-outline-secondary">Geri</a>
    </div>

    <!-- Silinecek mevcut seçenek id'leri -->
    <div id="deletedOptions"></div>
    <!-- Şube değişimi için sunucuya onay bilgisi -->
    <input type="hidden" name="branch_change_ack" id="branchChangeAck" value="0">
  </form>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function(){
  // PHP -> JS veri köprüsü
  const subMap      = <?= json_encode($subMap) ?>;
  const languages   = <?= json_encode(array_column($languages, null, 'LangCode')) ?>;
  const defaultLang = <?= json_encode($defaultLang) ?>;

  // Kategori -> Alt kategori
  $('#categorySelect').on('change', function(){
    const id = $(this).val();
    let html = '<option value="">Seçiniz</option>';
    if (id && subMap[id]) {
      subMap[id].forEach(sc => { html += `<option value="${sc.SubCategoryID}">${sc.SubCategoryName}</option>`; });
    }
    $('#subCategorySelect').html(html);
  });

  // === Seçenekler (varsayılan dilde ekle/sil)
  const defContainer = $('#options-' + defaultLang + '-container');

  function addNewOptionRow(){
    const newIndex = defContainer.find('.option-row[data-new="1"]').length;
    defContainer.append(
      `<div class="option-row d-flex flex-wrap align-items-center gap-2 mb-2" data-new="1" data-new-index="${newIndex}">
         <input type="text" name="options_new[name][]"  class="form-control" style="min-width:240px" placeholder="Seçenek adı (<?= strtoupper($defaultLang) ?>)">
         <input type="number" step="0.01" name="options_new[price][]" class="form-control" style="max-width:140px" placeholder="Fiyat (₺)">
         <div class="form-check">
           <input class="form-check-input" type="radio" name="options_def[IsDefault]" value="new-${newIndex}" id="optdef_new_${newIndex}">
           <label class="form-check-label" for="optdef_new_${newIndex}">Varsayılan</label>
         </div>
         <button type="button" class="btn btn-outline-danger removeNewOptionBtn">&times;</button>
       </div>`
    );

    // İlk eklemede default otomatik seç
    const group = $('input[type="radio"][name="options_def[IsDefault]"]');
    if (group.filter(':checked').length === 0) {
      defContainer.find(`.option-row[data-new-index="${newIndex}"] input[type="radio"][name="options_def[IsDefault]"]`).prop('checked', true);
    }

    // diğer diller için çeviri input’u
    Object.keys(languages).forEach(function(lc){
      if (lc === defaultLang) return;
      $('#options-' + lc + '-container').append(
        `<div class="option-row mb-2" data-new="1" data-new-index="${newIndex}">
           <input type="text" name="options_new_tr[${lc}][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ${lc.toUpperCase()})">
         </div>`
      );
    });
  }

  $(document).on('click', '#addNewOptionBtn', addNewOptionRow);

  // Yeni satır sil
  $(document).on('click', '.removeNewOptionBtn', function(){
    const idx = $(this).closest('.option-row').data('new-index');
    defContainer.find(`.option-row[data-new="1"][data-new-index="${idx}"]`).remove();
    Object.keys(languages).forEach(function(lc){
      if (lc === defaultLang) return;
      $(`#options-${lc}-container .option-row[data-new="1"][data-new-index="${idx}"]`).remove();
    });
  });

  // Mevcut satır sil -> gizli input ile server'a bildir
  $(document).on('click', '.removeExistingBtn', function(){
    const oid = $(this).data('oid');
    if (!confirm('Bu seçeneği silmek istiyor musunuz?')) return;
    $('#deletedOptions').append(`<input type="hidden" name="options_delete[]" value="${oid}">`);
    $(`.option-row[data-oid="${oid}"]`).remove();
    // varsa o id'nin çeviri satırları da kalksın
    Object.keys(languages).forEach(function(lc){
      if (lc === defaultLang) return;
      $(`#options-${lc}-container .option-row[data-oid="${oid}"]`).remove();
    });
  });

  // Resim önizleme
  const input   = document.getElementById('newImagesInput');
  const preview = document.getElementById('newImagesPreview');
  let selectedFiles = [];

  function syncInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    input.files = dt.files;
  }
  function renderPreviews(){
    preview.innerHTML = '';
    selectedFiles.forEach((file, idx) => {
      if (!file.type || !file.type.startsWith('image/')) return;
      const url  = URL.createObjectURL(file);
      const wrap = document.createElement('div');
      wrap.style.position = 'relative';

      const img  = document.createElement('img');
      img.src = url;
      img.className = 'rounded border';
      img.style.height = '70px';
      img.style.width  = 'auto';
      img.onload = function(){ URL.revokeObjectURL(url); };

      const btn  = document.createElement('button');
      btn.type = 'button';
      btn.textContent = '×';
      btn.title = 'Kaldır';
      btn.className = 'btn btn-sm btn-danger';
      btn.style.position = 'absolute';
      btn.style.top = '-10px';
      btn.style.right = '-10px';
      btn.style.borderRadius = '50%';
      btn.addEventListener('click', function(){
        selectedFiles.splice(idx, 1);
        syncInput();
        renderPreviews();
      });

      wrap.appendChild(img);
      wrap.appendChild(btn);
      preview.appendChild(wrap);
    });
  }
  if (input) {
    input.addEventListener('change', function(e){
      const newFiles = Array.from(e.target.files || []);
      newFiles.forEach(function(f){
        if (f && f.type && f.type.startsWith('image/')) selectedFiles.push(f);
      });
      syncInput();
      renderPreviews();
    });
  }

  // === Şube değişim uyarısı ===
  const form = document.getElementById('itemEditForm');
  const branchChangeAck = document.getElementById('branchChangeAck');

  $('#submitBtn').on('click', function(e){
    const origId  = parseInt(form.dataset.originalBranchId || '0', 10);
    const currId  = parseInt(form.dataset.currentBranchId || '0', 10);
    const origNm  = form.dataset.originalBranchName || '';
    const currNm  = form.dataset.currentBranchName || '';

    if (origId !== currId) {
      const ok = confirm(`Bu ürün şu anda "${origNm}" şubesine ait.\nKaydederseniz şube "${currNm}" olarak değiştirilecek.\nEmin misiniz?`);
      if (!ok) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
      branchChangeAck.value = '1';
    }
    // normal submit devam
    form.submit();
  });

})();
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
