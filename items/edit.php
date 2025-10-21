<?php
// items/edit.php
session_start();
require_once __DIR__ . '/../db.php';

// Debug amaçlı: ?debug=1
if (isset($_GET['debug'])) { ini_set('display_errors', 1); error_reporting(E_ALL); }

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = (int)$_SESSION['restaurant_id'];
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($itemId <= 0) { header('Location: list.php'); exit; }

/** Ürün + bağlı olduğu alt/ana kategori */
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

/** Diller */
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
} catch (Exception $e) { $languages = []; }
if (!$languages) { $languages = [['LangCode'=>'tr','IsDefault'=>1,'LangName'=>'Türkçe']]; }
$defaultLang = null;
foreach ($languages as $L) { if (!empty($L['IsDefault'])) { $defaultLang = $L['LangCode']; break; } }
if (!$defaultLang) $defaultLang = $languages[0]['LangCode'];

/** MenuItemTranslations FK kolon adı (MenuItemID/ItemID) */
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

/** Ürün çevirileri */
$trItmStmt = $pdo->prepare("SELECT LangCode, Name, Description FROM MenuItemTranslations WHERE $fkCol = ?");
$trItmStmt->execute([$itemId]);
$itemTr = [];
foreach ($trItmStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $itemTr[$r['LangCode']] = ['name' => $r['Name'], 'desc' => $r['Description']];
}

/** Seçenekler + çevirileri */
$optsStmt = $pdo->prepare("SELECT OptionID, OptionName, Price, IsDefault, SortOrder FROM MenuItemOptions WHERE MenuItemID = ? ORDER BY SortOrder, OptionID");
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

/** MenuImages PK kolonunu tespit (ImageID / MenuImageID / ID) */
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

/** Mevcut resimler */
$imgStmt = $pdo->prepare("SELECT {$imgPkCol} AS ImgPK, ImageURL FROM MenuImages WHERE MenuItemID = ? ORDER BY {$imgPkCol}");
$imgStmt->execute([$itemId]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

/** Tek resim silme */
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

/** Güncelle (POST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : $currentCategoryId;
    $subCategoryId = isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : $currentSubId;

    $trans   = $_POST['trans'] ?? []; // trans[lang][name|desc]
    $defName = trim($trans[$defaultLang]['name'] ?? '');
    $defDesc = trim($trans[$defaultLang]['desc'] ?? '');
    $priceVal = isset($_POST['price']) ? (float)$_POST['price'] : (float)($item['Price'] ?? 0);

    // Seçenekler (mevcut)
    $optDefNames  = $_POST['options_def']['name']  ?? []; // [OptionID] => name
    $optDefPrices = $_POST['options_def']['price'] ?? []; // [OptionID] => price

    // TEK GRUP: seçili radio tek bir yerde gelir
    $selectedDefault = $_POST['options_def']['IsDefault'] ?? null; // "23" veya "new-0" gibi

    $optDeletes   = $_POST['options_delete']       ?? []; // [OptionID...]
    $optTrPost    = $_POST['options_tr']           ?? []; // options_tr[lang]['name'][OptionID]

    // Yeni seçenekler
    $optNewNames  = $_POST['options_new']['name']  ?? [];
    $optNewPrices = $_POST['options_new']['price'] ?? [];
    $optNewTrPost = $_POST['options_new_tr']       ?? []; // [lang]['name'][]

    // seçimin türünü ayırt et
    $optNewDefaultIndex = null;   // "new-#" gelirse buraya index
    $optDefIsDefault    = null;   // sayı gelirse mevcut OptionID
    if ($selectedDefault !== null && $selectedDefault !== '') {
        if (is_string($selectedDefault) && strpos($selectedDefault, 'new-') === 0) {
            $optNewDefaultIndex = substr($selectedDefault, 4); // 'new-' sonrası
        } else {
            $optDefIsDefault = (int)$selectedDefault;
        }
    }

    if ($defName === '')       $error = strtoupper($defaultLang) . ' dilinde ürün adı zorunludur.';
    elseif ($subCategoryId<=0) $error = 'Lütfen bir alt kategori seçin.';

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // 1) Ürün
            $upd = $pdo->prepare("UPDATE MenuItems SET MenuName=?, Description=?, Price=?, SubCategoryID=? WHERE MenuItemID=? AND RestaurantID=?");
            $upd->execute([$defName, ($defDesc !== '' ? $defDesc : null), $priceVal, $subCategoryId, $itemId, $restaurantId]);

            // 2) Ürün çevirileri (upsert/temizle)
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

            // 3) Silinecek seçenekler
            if (!empty($optDeletes)) {
                $delO = $pdo->prepare("DELETE FROM MenuItemOptions WHERE OptionID = ? AND MenuItemID = ?");
                foreach ($optDeletes as $oid) { $oid = (int)$oid; if ($oid>0) $delO->execute([$oid, $itemId]); }
                // Çevirileri de temizle (CASCADE yoksa)
                $optDeletes = array_values(array_filter(array_map('intval', (array)$optDeletes)));
                if ($optDeletes) {
                    $in = implode(',', array_fill(0, count($optDeletes), '?'));
                    $delOTr = $pdo->prepare("DELETE FROM MenuItemOptionTranslations WHERE OptionID IN ($in)");
                    $delOTr->execute($optDeletes);
                }
            }

            // 3.5) Eğer yeni bir satır default seçilmişse, mevcutların hepsini 0'a çek
            if ($optNewDefaultIndex !== null) {
                $pdo->prepare("UPDATE MenuItemOptions SET IsDefault = 0 WHERE MenuItemID = ?")->execute([$itemId]);
            }

            // 4) Mevcut seçenekleri güncelle
            if (!empty($optDefNames)) {
                $updO = $pdo->prepare("UPDATE MenuItemOptions SET OptionName=?, Price=?, IsDefault=?, SortOrder=? WHERE OptionID=? AND MenuItemID=?");
                $sort = 0;
                foreach ($optDefNames as $oid => $nm) {
                    $oid = (int)$oid; if ($oid<=0) continue;
                    $nm  = trim($nm ?? '');
                    $prc = isset($optDefPrices[$oid]) ? (float)$optDefPrices[$oid] : 0.0;

                    // Yeni default seçilmişse mevcutların tümü 0; değilse seçilen mevcut id 1
                    $isDefOpt = ($optNewDefaultIndex !== null) ? 0 : (($oid === $optDefIsDefault) ? 1 : 0);

                    $updO->execute([$nm, $prc, $isDefOpt, $sort++, $oid, $itemId]);
                }
            }

            // 5) Mevcut seçenek çevirileri
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
                $insO = $pdo->prepare("INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, IsDefault, SortOrder) VALUES (?, ?, ?, ?, ?)");
                $existingCount = (int)$pdo->query("SELECT COUNT(*) FROM MenuItemOptions WHERE MenuItemID = ".$itemId)->fetchColumn();
                foreach ($optNewNames as $i => $nm) {
                    $nm = trim($nm ?? ''); if ($nm==='') continue;
                    $prc = isset($optNewPrices[$i]) ? (float)$optNewPrices[$i] : 0.0;

                    // new-# seçilmişse yalnızca o satır 1 olur
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

            // 7) Yeni resimler
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
            $error = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Menü Öğesi Düzenle";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="container mt-5" style="max-width: 980px;">
    <h2 class="mb-4">Menü Öğesi Düzenle</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">

<div class="row g-3 mb-3">
        <div class="col-md-6">
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

                    <div class="col-md-6">
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

                    </div>
    
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
                    <!-- Kategori / Alt Kategori (Sadece bir kez gösterilecek alanlar; ama basit tutmak için her tabda aynı id'yi kullanıyoruz) -->
                    <?php if ($isDef): ?>
                    
                    <?php endif; ?>

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
                                <span class="text-muted">Yeni satır ekleme/silme <b>varsayılan dil</b> sekmesinden yapılır.</span>
                            <?php endif; ?>
                        </div>

                        <div id="options-<?= htmlspecialchars($lc) ?>-container">
                            <?php if ($isDef): ?>
                                <?php foreach ($options as $op): ?>
                                    <div class="option-row" data-oid="<?= (int)$op['OptionID'] ?>">
                                        <input type="text" 
                                               name="options_def[name][<?= (int)$op['OptionID'] ?>]"
                                               value="<?= htmlspecialchars($op['OptionName']) ?>"
                                               class="form-control"
                                               placeholder="Seçenek adı">
                                        <input type="number" step="0.01" 
                                               name="options_def[price][<?= (int)$op['OptionID'] ?>]"
                                               value="<?= htmlspecialchars($op['Price']) ?>"
                                               class="form-control"
                                               placeholder="Fiyat (₺)">
                                        <input type="radio" 
                                               name="options_def[IsDefault]"
                                               value="<?= (int)$op['OptionID'] ?>"
                                               <?= ($op['IsDefault'] == 1 ? 'checked' : '') ?>>
                                        <label>Varsayılan</label>     
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
                            <div class="form-text">Var olan satırları burada düzenleyebilir/silebilirsiniz. Yeni satırlar eklendiğinde diğer dillerde çeviri alanları otomatik oluşur.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">

            <!-- Varsayılan fiyat -->
            <div class="mb-3">
               
                <input type="hidden" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($item['Price'] ?? 0) ?>">
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

<!-- jQuery (sadece bu sayfada gerekli dinamikler için) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
  // PHP'den JS'e veri köprüsü
  const subMap       = <?= json_encode($subMap) ?>;
  const languages    = <?= json_encode(array_column($languages, null, 'LangCode')) ?>;
  const defaultLang  = <?= json_encode($defaultLang) ?>;
  const currentCatId = <?= (int)$currentCategoryId ?>;
  const currentSubId = <?= (int)$currentSubId ?>;
</script>

<!-- Sayfaya özel JS (cache'i atlamak için versiyonla) -->
<script src="/assets/js/items_edit.js?v=<?= time() ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
