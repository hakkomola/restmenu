<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: ../restaurants/login.php');
    exit;
}

$restaurantId = (int)$_SESSION['restaurant_id'];
$message = '';
$errors  = [];

/** Kategoriler */
$catStmt = $pdo->prepare("SELECT * FROM MenuCategories WHERE RestaurantID = ? ORDER BY CategoryName ASC");
$catStmt->execute([$restaurantId]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

/** Alt kategoriler */
$subCategoriesMap = [];
foreach ($categories as $cat) {
    $subStmt = $pdo->prepare("SELECT * FROM SubCategories WHERE CategoryID = ? ORDER BY SubCategoryName ASC");
    $subStmt->execute([$cat['CategoryID']]);
    $subCategoriesMap[$cat['CategoryID']] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
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
} catch (Exception $e) {
    $languages = [];
}
if (!$languages) {
    $languages = [['LangCode'=>'tr','IsDefault'=>1,'LangName'=>'Türkçe']];
}
$defaultLang = null;
foreach ($languages as $L) { if (!empty($L['IsDefault'])) { $defaultLang = $L['LangCode']; break; } }
if (!$defaultLang) $defaultLang = $languages[0]['LangCode'];

/** FK tespiti */
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

/** === FORM GÖNDERİMİ === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) header('Content-Type: application/json');

    $subCategoryId = (int)($_POST['sub_category_id'] ?? 0);
    $price         = (float)($_POST['price'] ?? 0.0);
    $trans         = $_POST['trans'] ?? [];

    $defName = trim($trans[$defaultLang]['name'] ?? '');
    $defDesc = trim($trans[$defaultLang]['desc'] ?? '');

    $optNewNames   = $_POST['options_new']['name']  ?? [];
    $optNewPrices  = $_POST['options_new']['price'] ?? [];
    $optNewTrPost  = $_POST['options_new_tr']       ?? [];
    $selectedDefault = $_POST['options_def']['IsDefault'] ?? null;
    $optNewDefaultIndex = (is_string($selectedDefault) && strpos($selectedDefault, 'new-') === 0)
        ? substr($selectedDefault, 4)
        : null;

    if ($defName === '') $errors[] = strtoupper($defaultLang) . ' dilinde ürün adı zorunludur.';
    if ($subCategoryId <= 0) $errors[] = 'Lütfen bir alt kategori seçin.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1️⃣ Ürün
            $ins = $pdo->prepare('INSERT INTO MenuItems (SubCategoryID, RestaurantID, MenuName, Description, Price) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$subCategoryId, $restaurantId, $defName, ($defDesc ?: null), $price]);
            $menuItemId = (int)$pdo->lastInsertId();

            // 2️⃣ Çeviriler
            if (!empty($languages)) {
                $insTr = $pdo->prepare("INSERT INTO MenuItemTranslations ($fkCol, LangCode, Name, Description) VALUES (:iid, :lang, :name, :desc)");
                foreach ($languages as $L) {
                    $lc = $L['LangCode'];
                    if ($lc === $defaultLang) continue;
                    $nm = trim($trans[$lc]['name'] ?? '');
                    $ds = trim($trans[$lc]['desc'] ?? '');
                    if ($nm !== '' || $ds !== '') {
                        $insTr->execute([
                            ':iid'  => $menuItemId,
                            ':lang' => $lc,
                            ':name' => ($nm ?: $defName),
                            ':desc' => ($ds ?: null),
                        ]);
                    }
                }
            }

            // 3️⃣ Seçenekler
            $newOids = [];
            if (!empty($optNewNames)) {
                $insO = $pdo->prepare('INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, IsDefault, SortOrder) VALUES (?, ?, ?, ?, ?)');
                foreach ($optNewNames as $i => $nm) {
                    $nm = trim($nm);
                    if ($nm === '') continue;
                    $prc = (float)($optNewPrices[$i] ?? 0.0);
                    $isDef = ($optNewDefaultIndex !== null && (string)$i === (string)$optNewDefaultIndex) ? 1 : 0;
                    $insO->execute([$menuItemId, $nm, $prc, $isDef, $i]);
                    $newOids[$i] = (int)$pdo->lastInsertId();
                }
            }

            // 4️⃣ Seçenek çevirileri
            if ($newOids && $languages) {
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

            // 5️⃣ Resimler
            if (!empty($_FILES['images']['name'][0])) {
                $uploadsDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
                $insImg = $pdo->prepare('INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)');
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if (!$tmpName) continue;
                    $fileName = time().'_'.basename($_FILES['images']['name'][$i]);
                    $target = $uploadsDir.$fileName;
                    if (move_uploaded_file($tmpName, $target)) {
                        $insImg->execute([$menuItemId, 'uploads/'.$fileName]);
                    }
                }
            }

            $pdo->commit();

            if ($isAjax) {
                echo json_encode(['status' => 'success']);
                exit;
            }

            header('Location: list.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            $message = 'Kayıt hatası: ' . $e->getMessage();
        }
    } else {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
            exit;
        }
        $message = implode('<br>', array_map('htmlspecialchars', $errors));
    }
}

$pageTitle = "Yeni Menü Öğesi Ekle";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>


<div class="container mt-5" style="max-width: 900px;">
    <h2 class="mb-4">Yeni Menü Öğesi Ekle</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= $message ?></div>
    <?php endif; ?>

    <!-- === FORM === -->
    <form method="post" enctype="multipart/form-data" class="card shadow-sm">
        <div class="card-body">

            <!-- Kategori / Alt kategori (SEKMELERİN DIŞINDA, TEKİL) -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Ana Kategori</label>
                    <select name="category_id" id="categorySelect" class="form-select" required>
                        <option value="">Seçiniz</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alt Kategori</label>
                    <select name="sub_category_id" id="subCategorySelect" class="form-select" required>
                        <option value="">Seçiniz</option>
                    </select>
                </div>
            </div>

            <!-- Diller -->
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

            <!-- İçerik -->
            <div class="tab-content">
                <?php foreach ($languages as $L):
                    $lc = $L['LangCode']; $isDef = !empty($L['IsDefault']);
                ?>
                <div class="tab-pane fade <?= $isDef ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($lc) ?>">

                    <div class="mb-3">
                        <label class="form-label">Menü Adı (<?= strtoupper($lc) ?>)</label>
                        <input type="text" name="trans[<?= htmlspecialchars($lc) ?>][name]" class="form-control" <?= $isDef ? 'required' : '' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Açıklama (<?= strtoupper($lc) ?>)</label>
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
                            <?php if (!$isDef): ?>
                                <!-- Diğer diller: yalnızca çeviri adı satırları buraya dinamik eklenecek -->
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <input type="hidden" step="0.01" name="price" class="form-control">
                <div class="form-text">Bu fiyat seçenek belirtilmediğinde geçerlidir.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Resimler (birden fazla seçebilirsiniz)</label>
                <input type="file" id="imagesInput" name="images[]" class="form-control" accept="image/*" multiple>
                <div class="form-text">Kaydetmeden önce seçtikleriniz aşağıda önizlenir. İstemediğiniz resmi sağ üstteki × ile kaldırabilirsiniz.</div>
                <div id="imagesPreview" class="image-grid mt-2"></div>
            </div>

        </div>
       <div class="card-footer d-flex gap-2">
  <button class="btn btn-success" type="submit">Kaydet</button>
  <button class="btn btn-primary" type="button" id="saveStayBtn">Ekle (Sayfada Kal)</button>
  <a href="list.php" class="btn btn-secondary">Geri</a>
</div>

    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
(function(){
    // PHP -> JS veri
    const subCategoriesMap = <?= json_encode($subCategoriesMap) ?>;
    const languages       = <?= json_encode(array_column($languages, null, 'LangCode')) ?>;
    const defaultLang     = <?= json_encode($defaultLang) ?>;

    // === Kategori -> Alt kategori
    $('#categorySelect').on('change', function(){
        const catId = $(this).val();
        let html = '<option value="">Seçiniz</option>';
        if (catId && subCategoriesMap[catId]){
            subCategoriesMap[catId].forEach(sub => {
                html += '<option value="' + sub.SubCategoryID + '">' + sub.SubCategoryName + '</option>';
            });
        }
        $('#subCategorySelect').html(html);
    });

    // === Seçenekler (dinamik satırlar)
    const defContainer = $('#options-' + defaultLang + '-container');

    function addOptionRow(){
      const newIndex = defContainer.find('.option-row[data-new="1"]').length;

      // Varsayılan dil satırı (ad + fiyat + tek-grup radio + sil)
      defContainer.append(
        '<div class="option-row" data-new="1" data-new-index="' + newIndex + '">' +
          '<input type="text" name="options_new[name][]" class="form-control" placeholder="Seçenek adı (<?= strtoupper($defaultLang) ?>)">' +
          '<input type="number" step="0.01" name="options_new[price][]" class="form-control" placeholder="Fiyat (₺)">' +
          '<input type="radio" name="options_def[IsDefault]" value="new-' + newIndex + '">' +
          '<label>Varsayılan</label>' +
          '<button type="button" class="btn btn-outline-danger removeOptionBtn">&times;</button>' +
        '</div>'
      );

      // İlk eklemede default'u otomatik seç
      const group = $('input[type="radio"][name="options_def[IsDefault]"]');
      if (group.filter(':checked').length === 0) {
        defContainer
          .find('.option-row[data-new-index="' + newIndex + '"] input[type="radio"][name="options_def[IsDefault]"]')
          .prop('checked', true);
      }

      // Diğer diller için çeviri satırı
      Object.keys(languages).forEach(function(lc){
        if (lc === defaultLang) return;
        $('#options-' + lc + '-container').append(
          '<div class="option-row" data-new="1" data-new-index="' + newIndex + '">' +
            '<input type="text" name="options_new_tr[' + lc + '][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ' + lc.toUpperCase() + ')">' +
          '</div>'
        );
      });
    }

    // Varsayılan dil sekmesindeki buton
    $(document).on('click', '#addOptionBtn', addOptionRow);

    // Yeni seçenek satırı silme (varsayılan + diğer diller eş dizin)
    $(document).on('click', '.removeOptionBtn', function(){
        const $row = $(this).closest('.option-row');
        const idx = $row.data('new-index');
        // aynı index'teki tüm dillerdeki new satırları kaldır
        defContainer.find('.option-row[data-new="1"][data-new-index="' + idx + '"]').remove();
        Object.keys(languages).forEach(function(lc){
            if (lc === defaultLang) return;
            $('#options-' + lc + '-container .option-row[data-new="1"][data-new-index="' + idx + '"]').remove();
        });
    });

    // --- sayfa açılışında bir kez otomatik seçenek satırı ekle ---
    $(window).on('load', function(){
      if (defContainer.length && defContainer.find('.option-row').length === 0) {
        addOptionRow();
      }
    });

    // === Resim önizleme + X ile kaldırma
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
            img.onload = function(){ URL.revokeObjectURL(url); };

            const btn  = document.createElement('button');
            btn.type = 'button';
            btn.className = 'img-remove';
            btn.innerHTML = '&times;';
            btn.title = 'Bu resmi kaldır';
            btn.addEventListener('click', function(){
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
        input.addEventListener('change', function(e){
            const newFiles = Array.from(e.target.files || []);
            newFiles.forEach(function(f){
                if (f && f.type && f.type.startsWith('image/')) selectedFiles.push(f);
            });
            syncInput();
            renderPreviews();
        });
    }
})();

// === AJAX "Ekle (Sayfada Kal)" ===
$(document).on('click', '#saveStayBtn', function(e){
    e.preventDefault();
    const form = $('form')[0];
    const formData = new FormData(form);
    const btn = $(this);
    btn.prop('disabled', true).text('Kaydediliyor...');

    $.ajax({
        url: 'create.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            $('.alert').remove();
            if (res && res.status === 'success') {
                $('h2.mb-4').after('<div class="alert alert-success mt-3">✅ Ürün başarıyla eklendi. Sayfa yenilenmeden devam edebilirsiniz.</div>');
            } else {
                $('h2.mb-4').after('<div class="alert alert-danger mt-3">⚠️ Hata: ' + (res.message || 'Bilinmeyen hata') + '</div>');
            }
        },
        error: function(xhr){
            $('.alert').remove();
            $('h2.mb-4').after('<div class="alert alert-danger mt-3">Sunucu hatası: ' + xhr.statusText + '</div>');
        },
        complete: function(){
            btn.prop('disabled', false).text('Ekle (Sayfada Kal)');
        }
    });
});


</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
