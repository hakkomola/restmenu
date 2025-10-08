<?php
// items/edit.php
session_start();
require_once __DIR__ . '/../db.php';

// İsteğe bağlı hata çıktısı: ?debug=1
if (isset($_GET['debug'])) { ini_set('display_errors', 1); error_reporting(E_ALL); }

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = (int)$_SESSION['restaurant_id'];
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($itemId <= 0) { header('Location: list.php'); exit; }

/** Ürün + alt kategori (LEFT JOIN; bağ kopuk olsa da açılır) */
$qItem = $pdo->prepare("
    SELECT mi.*, sc.SubCategoryID AS SCID, sc.CategoryID AS CATID
    FROM MenuItems mi
    LEFT JOIN SubCategories sc ON sc.SubCategoryID = mi.SubCategoryID
    WHERE mi.MenuItemID = ? AND mi.RestaurantID = ?
");
$qItem->execute([$itemId, $restaurantId]);
$item = $qItem->fetch(PDO::FETCH_ASSOC);
if (!$item) { header('Location: list.php'); exit; }

$currentCategoryId = (int)($item['CATID'] ?? 0);
$currentSubId      = (int)($item['SCID']  ?? 0);

/** Kategoriler + alt kategoriler */
$catStmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC");
$catStmt->execute([$restaurantId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$subMap = [];
foreach ($categories as $cat) {
    $st = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID = ? ORDER BY SubCategoryName ASC");
    $st->execute([$cat['CategoryID']]);
    $subMap[$cat['CategoryID']] = $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Diller (RestaurantLanguages + Languages). Boşsa TR fallback. */
try {
    $langStmt = $pdo->prepare("
        SELECT rl.LangCode, rl.IsDefault, l.LangName
        FROM RestaurantLanguages rl
        JOIN Languages l ON l.LangCode = rl.LangCode
        WHERE rl.RestaurantID = ?
        ORDER BY rl.IsDefault DESC, rl.LangCode
    ");
    $langStmt->execute([$restaurantId]);
    $languages = $langStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $languages = [];
}
if (!$languages) { $languages = [['LangCode'=>'tr','IsDefault'=>1,'LangName'=>'Türkçe']]; }
$defaultLang = null;
foreach ($languages as $L) { if (!empty($L['IsDefault'])) { $defaultLang = $L['LangCode']; break; } }
if (!$defaultLang) $defaultLang = $languages[0]['LangCode'];

/** MenuItemTranslations FK kolonu (MenuItemID/ItemID) tespiti */
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
} catch (Exception $e) { /* varsayılan kalsın */ }

/** Ürün çevirileri */
$trItmStmt = $pdo->prepare("SELECT LangCode, Name, Description FROM MenuItemTranslations WHERE $fkCol = ?");
$trItmStmt->execute([$itemId]);
$itemTr = [];
foreach ($trItmStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $itemTr[$r['LangCode']] = ['name' => $r['Name'], 'desc' => $r['Description']];
}

/** Seçenekler + çevirileri */
$optsStmt = $pdo->prepare("SELECT OptionID, OptionName, Price, SortOrder FROM MenuItemOptions WHERE MenuItemID = ? ORDER BY SortOrder, OptionID");
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

/** MenuImages PK kolonunu tespit et (ImageID / MenuImageID / ID) */
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
} catch (Exception $e) { /* varsayılan kalsın */ }

/** Mevcut resimler (dinamik PK) */
$imgStmt = $pdo->prepare("SELECT {$imgPkCol} AS ImgPK, ImageURL FROM MenuImages WHERE MenuItemID = ? ORDER BY {$imgPkCol}");
$imgStmt->execute([$itemId]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

/** Tekil resim silme (GET) */
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

/** Güncelle */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : $currentCategoryId;
    $subCategoryId = isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : $currentSubId;

    $trans = $_POST['trans'] ?? []; // trans[lang][name|desc]
    $defName = trim($trans[$defaultLang]['name'] ?? '');
    $defDesc = trim($trans[$defaultLang]['desc'] ?? '');
    $priceVal = isset($_POST['price']) ? (float)$_POST['price'] : (float)($item['Price'] ?? 0);

    // Seçenekler
    $optDefNames  = $_POST['options_def']['name']  ?? []; // [OptionID] => name
    $optDefPrices = $_POST['options_def']['price'] ?? []; // [OptionID] => price
    $optDeletes   = $_POST['options_delete']       ?? []; // [OptionID...]
    $optTrPost    = $_POST['options_tr']           ?? []; // options_tr[lang]['name'][OptionID] => name

    // Yeni seçenekler
    $optNewNames  = $_POST['options_new']['name']  ?? []; // []
    $optNewPrices = $_POST['options_new']['price'] ?? []; // []
    $optNewTrPost = $_POST['options_new_tr']       ?? []; // [lang]['name'][]

    if ($defName === '')       $error = strtoupper($defaultLang) . ' dilinde ürün adı zorunludur.';
    elseif ($subCategoryId<=0) $error = 'Lütfen bir alt kategori seçin.';

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // 1) Ürün ana kayıt
            $upd = $pdo->prepare("UPDATE MenuItems SET MenuName=?, Description=?, Price=?, SubCategoryID=? WHERE MenuItemID=? AND RestaurantID=?");
            $upd->execute([$defName, ($defDesc !== '' ? $defDesc : null), $priceVal, $subCategoryId, $itemId, $restaurantId]);

            // 2) Ürün çevirileri (upsert / temizle)
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

            // 3) Seçenek silme
            if (!empty($optDeletes)) {
                $delO = $pdo->prepare("DELETE FROM MenuItemOptions WHERE OptionID = ? AND MenuItemID = ?");
                foreach ($optDeletes as $oid) {
                    $oid = (int)$oid; if ($oid>0) $delO->execute([$oid, $itemId]);
                }
                // CASCADE yoksa çevirileri de temizle
                $optDeletes = array_values(array_filter(array_map('intval', (array)$optDeletes)));
                if ($optDeletes) {
                    $in = implode(',', array_fill(0, count($optDeletes), '?'));
                    $delOTr = $pdo->prepare("DELETE FROM MenuItemOptionTranslations WHERE OptionID IN ($in)");
                    $delOTr->execute($optDeletes);
                }
            }

            // 4) Mevcut seçenekleri güncelle
            if (!empty($optDefNames)) {
                $updO = $pdo->prepare("UPDATE MenuItemOptions SET OptionName=?, Price=?, SortOrder=? WHERE OptionID=? AND MenuItemID=?");
                $sort = 0;
                foreach ($optDefNames as $oid => $nm) {
                    $oid = (int)$oid; if ($oid<=0) continue;
                    $nm  = trim($nm ?? '');
                    $prc = isset($optDefPrices[$oid]) ? (float)$optDefPrices[$oid] : 0.0;
                    $updO->execute([$nm, $prc, $sort++, $oid, $itemId]);
                }
            }

            // 5) Seçenek çevirileri (mevcut)
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

            // 6) Yeni seçenekler
            $newOids = [];
            if (!empty($optNewNames)) {
                $insO = $pdo->prepare("INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, SortOrder) VALUES (?, ?, ?, ?)");
                $existingCount = (int)$pdo->query("SELECT COUNT(*) FROM MenuItemOptions WHERE MenuItemID = ".$itemId)->fetchColumn();
                foreach ($optNewNames as $i => $nm) {
                    $nm = trim($nm ?? ''); if ($nm==='') continue;
                    $prc = isset($optNewPrices[$i]) ? (float)$optNewPrices[$i] : 0.0;
                    $insO->execute([$itemId, $nm, $prc, $existingCount + $i]);
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

            // 7) Yeni resimler (çoklu)
            if (!empty($_FILES['images']['name'][0])) {
                $uploadsDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

                $insImg = $pdo->prepare("INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)");
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if (!$tmpName) continue;
                    $fileName = time() . '_' . basename($_FILES['images']['name'][$i]);
                    $target   = $uploadsDir . $fileName;
                    if (move_uploaded_file($tmpName, $target)) {
                        $imageUrl = 'uploads/' . $fileName;
                        $insImg->execute([$itemId, $imageUrl]);
                    }
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

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menü Öğesi Düzenle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
.option-row { display: flex; gap: 10px; margin-bottom: 8px; align-items: center; }
.option-row input { flex: 1; }
.muted { opacity: .8; font-size: .9rem; }

/* Resim grid + önizleme X butonu */
.image-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; }
.image-card { position:relative; border:1px solid #ddd; border-radius:8px; overflow:hidden; background:#fff; }
.image-card img { width:100%; height:100px; object-fit:cover; display:block; }
.image-card a.del { position:absolute; top:6px; right:6px; text-decoration:none; background:#dc3545; color:#fff; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-weight:700; }
.image-card .img-remove {
  position:absolute; top:6px; right:6px;
  width:26px; height:26px; border-radius:50%;
  background:#dc3545; color:#fff; border:none;
  display:flex; align-items:center; justify-content:center;
  font-weight:700; line-height:1; cursor:pointer;

  /* ==== Yumuşak görsel düzen iyileştirmeleri (işlevsel olmayan) ==== */

/* Sekmeler okunaklı dursun */
.nav-tabs .nav-link {
  border-radius: 8px 8px 0 0;
  font-weight: 500;
}
.tab-content {
  border: 1px solid #dee2e6;
  border-top: none;
  border-radius: 0 0 10px 10px;
  padding: 16px;
  background: #fff;
}

/* Seçenek satırları mobilde taşmasın */
.option-row {
  flex-wrap: wrap;
}
.option-row input {
  min-width: 140px; /* dar ekranda kırılma için alt limit */
}

/* Resim ızgarası daha dengeli */
.image-grid {
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}
.image-card {
  border-radius: 10px;
  transition: transform .15s ease-in-out;
  box-shadow: 0 2px 6px rgba(0,0,0,.05);
}
.image-card:hover {
  transform: scale(1.02);
}

/* Silme butonu daha tıklanabilir kalsın */
.image-card a.del,
.image-card .img-remove {
  width: 28px; height: 28px;
  font-size: 16px;
}

/* Küçük ekran ayarları */
@media (max-width: 576px) {
  .option-row { gap: 8px; }
  .option-row input { flex: 1 1 100%; }
  .tab-content { padding: 12px; }
}

}
.image-card .img-remove:focus { outline:2px solid rgba(220,53,69,.4); }
</style>
</head>
<body>
<div class="container mt-5" style="max-width: 980px;">
    <h2 class="mb-4">Menü Öğesi Düzenle</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">

            <!-- Dil sekmeleri -->
            <ul class="nav nav-tabs mb-3" role="tablist">
                <?php foreach ($languages as $L): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= !empty($L['IsDefault']) ? 'active' : '' ?>" data-bs-toggle="tab"
                                data-bs-target="#tab-<?= htmlspecialchars($L['LangCode']) ?>" type="button" role="tab">
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
                           <!-- Kategori / Alt Kategori -->
            <div class="mb-3">
                <label>Ana Kategori</label>
                <select name="category_id" id="categorySelect" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['CategoryID'] ?>" <?= ($cat['CategoryID'] == $currentCategoryId ? 'selected' : '') ?>>
                            <?= htmlspecialchars($cat['CategoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label>Alt Kategori</label>
                <select name="sub_category_id" id="subCategorySelect" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($subMap[$currentCategoryId] ?? [] as $sc): ?>
                        <option value="<?= $sc['SubCategoryID'] ?>" <?= ($sc['SubCategoryID'] == $currentSubId ? 'selected' : '') ?>>
                            <?= htmlspecialchars($sc['SubCategoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
              <div class="mb-3">
                            <label>Menü Adı (<?= strtoupper($lc) ?>)</label>
                            <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" class="form-control"
                                   value="<?= htmlspecialchars($nameVal) ?>" <?= $isDef ? 'required' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label>Açıklama (<?= strtoupper($lc) ?>)</label>
                            <textarea name="trans[<?= htmlspecialchars($lc) ?>][desc]" class="form-control" rows="2"><?= htmlspecialchars($descVal) ?></textarea>
                        </div>

                        <!-- Seçenekler -->
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="m-0">Seçenekler (<?= strtoupper($lc) ?>)</h6>
                                <?php if ($isDef): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addNewOptionBtn">+ Yeni Seçenek</button>
                                <?php else: ?>
                                    <span class="muted">Yeni satır ekleme/silme <b>varsayılan dil</b> sekmesinden yapılır.</span>
                                <?php endif; ?>
                            </div>

                            <div id="options-<?= htmlspecialchars($lc) ?>-container">
                                <?php if ($isDef): ?>
                                    <?php foreach ($options as $op): ?>
                                        <div class="option-row" data-oid="<?= (int)$op['OptionID'] ?>">
                                            <input type="text" class="form-control"
                                                   name="options_def[name][<?= (int)$op['OptionID'] ?>]"
                                                   value="<?= htmlspecialchars($op['OptionName']) ?>"
                                                   placeholder="Seçenek adı">
                                            <input type="number" step="0.01" class="form-control"
                                                   name="options_def[price][<?= (int)$op['OptionID'] ?>]"
                                                   value="<?= htmlspecialchars($op['Price']) ?>"
                                                   placeholder="Fiyat (₺)">
                                            <button type="button" class="btn btn-outline-danger removeExistingBtn" data-oid="<?= (int)$op['OptionID'] ?>">&times;</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($options as $op): ?>
                                        <div class="option-row" data-oid="<?= (int)$op['OptionID'] ?>">
                                            <input type="text" class="form-control"
                                                   name="options_tr[<?= htmlspecialchars($lc) ?>][name][<?= (int)$op['OptionID'] ?>]"
                                                   value="<?= htmlspecialchars($optTr[$lc][$op['OptionID']] ?? '') ?>"
                                                   placeholder="Seçenek adı (çeviri)">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($isDef): ?>
                                <div class="form-text">Var olan satırları burada düzenleyebilir/silebilirsiniz. Yeni satırlar eklediğinizde diğer dillerde çeviri alanları otomatik oluşur.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">

            <!-- Varsayılan fiyat -->
            <div class="mb-3">
                <label>Varsayılan Fiyat (₺)</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($item['Price'] ?? 0) ?>">
            </div>

       

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

        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary">Güncelle</button>
            <a href="list.php" class="btn btn-secondary">Geri</a>
        </div>

        <!-- Silinecek mevcut seçenek id'leri -->
        <div id="deletedOptions"></div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
(function(){
    const subMap = <?= json_encode($subMap) ?>;
    const languages = <?= json_encode(array_column($languages, null, 'LangCode')) ?>;
    const defaultLang = <?= json_encode($defaultLang) ?>;

    // Kategori -> alt kategori
    $('#categorySelect').on('change', function(){
        const catId = $(this).val();
        let html = '<option value="">Seçiniz</option>';
        if (catId && subMap[catId]) {
            subMap[catId].forEach(sc => { html += `<option value="${sc.SubCategoryID}">${sc.SubCategoryName}</option>`; });
        }
        $('#subCategorySelect').html(html);
    });

    // Mevcut seçenek silme
    $(document).on('click', '.removeExistingBtn', function(){
        const oid = $(this).data('oid');
        if (!oid) return;
        // Varsayılan dil satırını sil
        $(`#options-${defaultLang}-container .option-row[data-oid="${oid}"]`).remove();
        // Diğer dillerdeki çeviri satırlarını sil
        Object.keys(languages).forEach(lc => {
            if (lc === defaultLang) return;
            $(`#options-${lc}-container .option-row[data-oid="${oid}"]`).remove();
        });
        // Sunucuya bildir
        $('#deletedOptions').append(`<input type="hidden" name="options_delete[]" value="${oid}">`);
    });

    // Yeni seçenek ekle (create ile aynı davranış)
    $('#addNewOptionBtn').on('click', function(){
        const defC = $(`#options-${defaultLang}-container`);
        const newIndex = defC.find('.option-row[data-new="1"]').length;

        defC.append(`
            <div class="option-row" data-new="1" data-new-index="${newIndex}">
                <input type="text" name="options_new[name][]" class="form-control" placeholder="Seçenek adı (<?= strtoupper($defaultLang) ?>)">
                <input type="number" step="0.01" name="options_new[price][]" class="form-control" placeholder="Fiyat (₺)">
                <button type="button" class="btn btn-outline-danger removeNewBtn">&times;</button>
            </div>
        `);

        Object.keys(languages).forEach(lc => {
            if (lc === defaultLang) return;
            $(`#options-${lc}-container`).append(`
                <div class="option-row" data-new="1" data-new-index="${newIndex}">
                    <input type="text" name="options_new_tr[${lc}][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ${lc.toUpperCase()})">
                </div>
            `);
        });
    });

    // Yeni seçenek satırı kaldır
    $(document).on('click', '.removeNewBtn', function(){
        const $row = $(this).closest('.option-row');
        const idx = $row.data('new-index');
        $row.remove();
        Object.keys(languages).forEach(lc => {
            if (lc === defaultLang) return;
            $(`#options-${lc}-container .option-row[data-new-index="${idx}"]`).remove();
        });
    });

    // Yeni resimler: önizleme + X ile kaldırma (DataTransfer)
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
            const card = document.createElement('div');
            card.className = 'image-card';

            const img  = document.createElement('img');
            img.src = url;
            img.onload = () => URL.revokeObjectURL(url);

            const btn  = document.createElement('button');
            btn.type = 'button';
            btn.className = 'img-remove';
            btn.innerHTML = '&times;';
            btn.title = 'Bu resmi kaldır';
            btn.addEventListener('click', () => {
                selectedFiles.splice(idx, 1);
                syncInput();
                renderPreviews();
            });

            card.appendChild(img);
            card.appendChild(btn);
            preview.appendChild(card);
        });
    }

    if (input) {
        input.addEventListener('change', e => {
            const newFiles = Array.from(e.target.files || []);
            newFiles.forEach(f => { if (f.type && f.type.startsWith('image/')) selectedFiles.push(f); });
            syncInput();
            renderPreviews();
        });
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
