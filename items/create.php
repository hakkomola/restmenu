<?php
// items/create.php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = (int)$_SESSION['restaurant_id'];
$message = '';
$errors  = [];

/** Kategoriler + alt kategoriler */
$catStmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC");
$catStmt->execute([$restaurantId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$subCategoriesMap = [];
foreach ($categories as $cat) {
    $subStmt = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID = ? ORDER BY SubCategoryName ASC");
    $subStmt->execute([$cat['CategoryID']]);
    $subCategoriesMap[$cat['CategoryID']] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
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
if (!$languages) {
    $languages = [['LangCode'=>'tr','IsDefault'=>1,'LangName'=>'Türkçe']];
}
$defaultLang = null;
foreach ($languages as $L) { if (!empty($L['IsDefault'])) { $defaultLang = $L['LangCode']; break; } }
if (!$defaultLang) $defaultLang = $languages[0]['LangCode'];

/** MenuItemTranslations FK kolonu tespiti (MenuItemID/ItemID) */
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subCategoryId = isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : 0;
    $price         = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;

    $trans = $_POST['trans'] ?? []; // trans[lang][name|desc]
    $defName = trim($trans[$defaultLang]['name'] ?? '');
    $defDesc = trim($trans[$defaultLang]['desc'] ?? '');

    // Yeni seçenekler (varsayılan dil)
    $optNewNames  = $_POST['options_new']['name']  ?? [];
    $optNewPrices = $_POST['options_new']['price'] ?? [];
    // Yeni seçenek çevirileri
    $optNewTrPost = $_POST['options_new_tr'] ?? []; // options_new_tr[lang]['name'][]

    if ($defName === '') {
        $errors[] = strtoupper($defaultLang) . ' dilinde ürün adı zorunludur.';
    }
    if ($subCategoryId <= 0) {
        $errors[] = 'Lütfen bir alt kategori seçin.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1) Ürünü ekle
            $ins = $pdo->prepare('INSERT INTO MenuItems (SubCategoryID, RestaurantID, MenuName, Description, Price) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$subCategoryId, $restaurantId, $defName, ($defDesc !== '' ? $defDesc : null), $price]);
            $menuItemId = (int)$pdo->lastInsertId();

            // 2) Ürün çevirileri
            if (!empty($languages)) {
                $insTr = $pdo->prepare("
                    INSERT INTO MenuItemTranslations ($fkCol, LangCode, Name, Description)
                    VALUES (:iid, :lang, :name, :desc)
                ");
                foreach ($languages as $L) {
                    $lc = $L['LangCode'];
                    if ($lc === $defaultLang) continue;
                    $nm = trim($trans[$lc]['name'] ?? '');
                    $ds = trim($trans[$lc]['desc'] ?? '');
                    if ($nm !== '' || $ds !== '') {
                        $insTr->execute([
                            ':iid'  => $menuItemId,
                            ':lang' => $lc,
                            ':name' => ($nm !== '' ? $nm : $defName),
                            ':desc' => ($ds !== '' ? $ds : null),
                        ]);
                    }
                }
            }

            // 3) Yeni seçenekler (varsayılan dil)
            $newOids = [];
            if (!empty($optNewNames)) {
                $insO = $pdo->prepare('INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, SortOrder) VALUES (?, ?, ?, ?)');
                foreach ($optNewNames as $i => $nm) {
                    $nm = trim($nm ?? '');
                    if ($nm === '') continue;
                    $prc = isset($optNewPrices[$i]) ? (float)$optNewPrices[$i] : 0.0;
                    $insO->execute([$menuItemId, $nm, $prc, $i]);
                    $newOids[$i] = (int)$pdo->lastInsertId();
                }
            }

            // 4) Yeni seçenek çevirileri
            if (!empty($newOids) && !empty($languages)) {
                $insOTr = $pdo->prepare('INSERT INTO MenuItemOptionTranslations (OptionID, LangCode, Name) VALUES (?, ?, ?)');
                foreach ($languages as $L) {
                    $lc = $L['LangCode'];
                    if ($lc === $defaultLang) continue;
                    $arr = $optNewTrPost[$lc]['name'] ?? [];
                    foreach ($newOids as $i => $oid) {
                        $nm = trim($arr[$i] ?? '');
                        if ($nm === '' || !$oid) continue;
                        $insOTr->execute([$oid, $lc, $nm]);
                    }
                }
            }

            // 5) Resimler (çoklu)
            if (!empty($_FILES['images']['name'][0])) {
                $uploadsDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

                $insImg = $pdo->prepare('INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)');
                foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                    if (!$tmpName) continue;
                    $fileName = time() . '_' . basename($_FILES['images']['name'][$index]);
                    $target   = $uploadsDir . $fileName;

                    if (move_uploaded_file($tmpName, $target)) {
                        $imageUrl = 'uploads/' . $fileName;
                        $insImg->execute([$menuItemId, $imageUrl]);
                    }
                }
            }

            $pdo->commit();
            header('Location: list.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Kayıt hatası: ' . $e->getMessage();
        }
    } else {
        $message = implode('<br>', array_map('htmlspecialchars', $errors));
    }
}

include __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yeni Menü Öğesi Ekle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
  background-color: #f8f9fa;
}
.container {
  background: #fff;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Form düzeni */
.option-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 8px;
  align-items: center;
}
.option-row input {
  flex: 1 1 45%;
  min-width: 130px;
}

/* Sekmeler */
.nav-tabs .nav-link {
  border-radius: 8px 8px 0 0;
}
.tab-content {
  background: #fff;
  border: 1px solid #dee2e6;
  border-top: none;
  padding: 20px;
  border-radius: 0 0 10px 10px;
}

/* Görsel önizleme ızgarası */
.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}
.image-card {
  position: relative;
  border: 1px solid #ddd;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  transition: transform 0.2s ease;
}
.image-card:hover {
  transform: scale(1.03);
}
.image-card img {
  width: 100%;
  height: 100px;
  object-fit: cover;
  display: block;
}
.image-card .img-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #dc3545;
  color: #fff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
}
.image-card .img-remove:focus {
  outline: 2px solid rgba(220,53,69,.4);
}

/* Küçük ekranlar */
@media (max-width: 576px) {
  .option-row {
    flex-direction: column;
    align-items: stretch;
  }
  .option-row input {
    flex: 1 1 100%;
  }
  .container {
    padding: 15px;
  }
}
</style>

</head>
<body>
<div class="container mt-5" style="max-width: 900px;">
    <h2 class="mb-4">Yeni Menü Öğesi Ekle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= $message ?></div>
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
                ?>
                    <div class="tab-pane fade <?= $isDef ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($lc) ?>">
                        <!-- Ana kategori seçimi -->
            <div class="mb-3">
                <label>Ana Kategori</label>
                <select name="category_id" id="categorySelect" class="form-select" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Alt kategori seçimi -->
            <div class="mb-3">
                <label>Alt Kategori</label>
                <select name="sub_category_id" id="subCategorySelect" class="form-select" required>
                    <option value="">Seçiniz</option>
                </select>
            </div> 
                    
                    <div class="mb-3">
                            <label>Menü Adı (<?= strtoupper($lc) ?>)</label>
                            <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" class="form-control" <?= $isDef ? 'required' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label>Açıklama (<?= strtoupper($lc) ?>)</label>
                            <textarea name="trans[<?= htmlspecialchars($lc) ?>][desc]" class="form-control"></textarea>
                        </div>

                        <!-- Seçenekler -->
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="m-0">Seçenekler (<?= strtoupper($lc) ?>)</h6>
                                <?php if ($isDef): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addOptionBtn">+ Yeni Seçenek</button>
                                <?php else: ?>
                                    <span class="text-muted">Yeni seçenek ekleme/kaldırma varsayılan dil sekmesinden yapılır.</span>
                                <?php endif; ?>
                            </div>

                            <div id="options-<?= htmlspecialchars($lc) ?>-container">
                                <?php if ($isDef): ?>
                                    <!-- Varsayılan dil: ad + fiyat satırları buraya dinamik eklenecek -->
                                <?php else: ?>
                                    <!-- Diğer diller: yalnızca çeviri adı satırları buraya dinamik eklenecek -->
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">

            <!-- Varsayılan fiyat -->
            <div class="mb-3">
                <label>Varsayılan Fiyat (₺)</label>
                <input type="number" step="0.01" name="price" class="form-control">
                <div class="form-text">Bu fiyat seçenek belirtilmediğinde geçerlidir.</div>
            </div>

           

            <!-- Resimler -->
            <div class="mb-3">
                <label class="form-label">Resimler (birden fazla seçebilirsiniz)</label>
                <input type="file" id="imagesInput" name="images[]" class="form-control" accept="image/*" multiple>
                <div class="form-text">Kaydetmeden önce seçtikleriniz aşağıda önizlenir. İstemediğiniz resmi sağ üstteki × ile kaldırabilirsiniz.</div>
                <div id="imagesPreview" class="image-grid mt-2"></div>
            </div>

        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-success">Kaydet</button>
            <a href="list.php" class="btn btn-secondary">Geri</a>
        </div>
    </form>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(function(){
    const subCategoriesMap = <?= json_encode($subCategoriesMap) ?>;
    const languages = <?= json_encode(array_column($languages, null, 'LangCode')) ?>;
    const defaultLang = <?= json_encode($defaultLang) ?>;

    // Kategori -> Alt kategori
    $('#categorySelect').on('change', function(){
        let catId = $(this).val();
        let html = '<option value="">Seçiniz</option>';
        if (catId && subCategoriesMap[catId]){
            subCategoriesMap[catId].forEach(sub => {
                html += `<option value="${sub.SubCategoryID}">${sub.SubCategoryName}</option>`;
            });
        }
        $('#subCategorySelect').html(html);
    });

    // --- Seçenekler (dinamik satırlar) ---
    const defContainer = $(`#options-${defaultLang}-container`);

    function addOptionRow(){
        // Varsayılan dil için satır
        defContainer.append(`
            <div class="option-row" data-new="1">
                <input type="text" name="options_new[name][]" class="form-control" placeholder="Seçenek adı (<?= strtoupper($defaultLang) ?>)">
                <input type="number" step="0.01" name="options_new[price][]" class="form-control" placeholder="Fiyat (₺)">
                <button type="button" class="btn btn-outline-danger removeOptionBtn">&times;</button>
            </div>
        `);

        // Diğer diller için çeviri satırları
        Object.keys(languages).forEach(lc => {
            if (lc === defaultLang) return;
            $(`#options-${lc}-container`).append(`
                <div class="option-row" data-new="1">
                    <input type="text" name="options_new_tr[${lc}][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ${lc.toUpperCase()})">
                </div>
            `);
        });
    }

    $('#addOptionBtn').on('click', addOptionRow);

    $(document).on('click', '.removeOptionBtn', function(){
        const idx = $(this).closest('.option-row').index(); // eş dizinli çeviri satırlarını da kaldır
        defContainer.find('.option-row[data-new="1"]').eq(idx).remove();
        Object.keys(languages).forEach(lc => {
            if (lc === defaultLang) return;
            $(`#options-${lc}-container .option-row[data-new="1"]`).eq(idx).remove();
        });
    });

    // --- Resim önizleme + X ile kaldırma ---
    const input   = document.getElementById('imagesInput');
    const preview = document.getElementById('imagesPreview');
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
            newFiles.forEach(f => {
                if (f && f.type && f.type.startsWith('image/')) selectedFiles.push(f);
            });
            syncInput();
            renderPreviews();
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
