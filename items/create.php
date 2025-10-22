<?php
// items/create.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('menu')) die('Erişim yetkiniz yok.');

$restaurantId  = (int)$_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null; // NOT NULL bekleniyor
$pageTitle     = "Yeni Menü Öğesi Ekle";

$message = '';
$errors  = [];

/** 🔹 Aktif şubeye göre kategoriler */
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

/** 🔹 Alt kategoriler (aktif şubeye göre, kategoriye bağlı) */
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

/** 🔹 Diller */
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
  // emniyet: en az TR olsun
  $languages = [['LangCode'=>'tr','IsDefault'=>1,'LangName'=>'Türkçe']];
}
$defaultLang = null;
foreach ($languages as $L) { if (!empty($L['IsDefault'])) { $defaultLang = $L['LangCode']; break; } }
if (!$defaultLang) $defaultLang = $languages[0]['LangCode'];

/** 🔹 MenuItemTranslations FK kolonu (MenuItemID/ItemID) emniyeti */
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

/** ===================== FORM POST ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
  if ($isAjax) header('Content-Type: application/json');

  $categoryId    = (int)($_POST['category_id'] ?? 0);
  $subCategoryId = (int)($_POST['sub_category_id'] ?? 0);
  $trans         = $_POST['trans'] ?? [];

  // seçenekler
  $optNewNames   = $_POST['options_new']['name']  ?? [];
  $optNewPrices  = $_POST['options_new']['price'] ?? [];
  $selectedDefault = $_POST['options_def']['IsDefault'] ?? null; // "new-<index>"
  $optNewTrPost  = $_POST['options_new_tr'] ?? []; // [lang][name] => array

  // ürün adı (varsayılan dil)
  $defName = trim($trans[$defaultLang]['name'] ?? '');
  $defDesc = trim($trans[$defaultLang]['desc'] ?? '');

  // validasyon
  if ($categoryId <= 0)      $errors[] = 'Lütfen bir ana kategori seçiniz.';
  if ($subCategoryId <= 0)   $errors[] = 'Lütfen bir alt kategori seçiniz.';
  if ($defName === '')       $errors[] = strtoupper($defaultLang) . ' dilinde ürün adı zorunludur.';

  // seçenek zorunluluğu ve default
  $hasAtLeastOneOption = false;
  foreach ($optNewNames as $nm) {
    if (trim($nm) !== '') { $hasAtLeastOneOption = true; break; }
  }
  if (!$hasAtLeastOneOption) $errors[] = 'En az bir seçenek eklemelisiniz.';
  if (!$selectedDefault)     $errors[] = 'Bir varsayılan seçenek seçmelisiniz.';

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // 1) Ürün (MenuItems) — fiyat yok; BranchID zorunlu
      $ins = $pdo->prepare("
        INSERT INTO MenuItems (RestaurantID, BranchID, SubCategoryID, MenuName, Description)
        VALUES (?, ?, ?, ?, ?)
      ");
      $ins->execute([$restaurantId, $currentBranch, $subCategoryId, $defName, ($defDesc ?: null)]);
      $menuItemId = (int)$pdo->lastInsertId();

      // 2) Çeviriler (MenuItemTranslations)
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
              ':name' => ($nm ?: $defName),
              ':desc' => ($ds ?: null),
            ]);
          }
        }
      }

      // 3) Seçenekler (MenuItemOptions)
      $newOids = []; // newIndex => OptionID
      $insO = $pdo->prepare("
        INSERT INTO MenuItemOptions (MenuItemID, OptionName, Price, IsDefault, SortOrder)
        VALUES (?, ?, ?, ?, ?)
      ");

      $defaultIndex = null;
      if (is_string($selectedDefault) && strpos($selectedDefault, 'new-') === 0) {
        $defaultIndex = substr($selectedDefault, 4); // "new-<index>" -> <index>
      }

      foreach ($optNewNames as $i => $nm) {
        $nm = trim($nm);
        if ($nm === '') continue;
        $prc = (float)($optNewPrices[$i] ?? 0.0);
        $isDef = ($defaultIndex !== null && (string)$i === (string)$defaultIndex) ? 1 : 0;
        $insO->execute([$menuItemId, $nm, $prc, $isDef, (int)$i]);
        $newOids[$i] = (int)$pdo->lastInsertId();
      }

      // 4) Seçenek çevirileri (MenuItemOptionTranslations)
      if ($newOids && $languages) {
        $insOTr = $pdo->prepare("
          INSERT INTO MenuItemOptionTranslations (OptionID, LangCode, Name)
          VALUES (?, ?, ?)
        ");
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

      // 5) Resimler (MenuImages)
      if (!empty($_FILES['images']['name'][0])) {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
        $insImg = $pdo->prepare('INSERT INTO MenuImages (MenuItemID, ImageURL) VALUES (?, ?)');
        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
          if (!$tmpName) continue;
          $fileName = time() . '_' . basename($_FILES['images']['name'][$i]);
          $target = $uploadsDir . $fileName;
          if (move_uploaded_file($tmpName, $target)) {
            $insImg->execute([$menuItemId, 'uploads/' . $fileName]);
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
      $message = 'Kayıt hatası: ' . htmlspecialchars($e->getMessage());
    }
  } else {
    if ($isAjax) {
      echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
      exit;
    }
    $message = implode('<br>', array_map('htmlspecialchars', $errors));
  }
}

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3" style="max-width: 980px;">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?= $message ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="bo-form card p-4 shadow-sm" id="itemCreateForm">
    <!-- 🔹 Kategori / Alt kategori -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label">Ana Kategori</label>
        <select name="category_id" id="categorySelect" class="form-select" required>
          <option value="">Seçiniz</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['CategoryID'] ?>"><?= htmlspecialchars($c['CategoryName']) ?></option>
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

    <!-- 🔹 Dil sekmeleri -->
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

        <!-- 🔹 Seçenekler -->
        <div class="border rounded p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="m-0">Seçenekler (<?= strtoupper($lc) ?>)</h6>
            <?php if ($isDef): ?>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addOptionBtn">+ Yeni Seçenek</button>
            <?php else: ?>
              <span class="text-muted">Yeni seçenek ekleme/silme yalnızca varsayılan dil sekmesinden yapılır.</span>
            <?php endif; ?>
          </div>
          <div id="options-<?= htmlspecialchars($lc) ?>-container">
            <!-- Varsayılan dilde: ad+fiyat+default radio+sil -->
            <!-- Diğer dillerde: sadece çeviri adı inputları (dinamik eklenecek) -->
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <hr class="my-4">

    <!-- 🔹 Resimler -->
    <div class="mb-3">
      <label class="form-label">Resimler (birden fazla seçebilirsiniz)</label>
      <input type="file" id="imagesInput" name="images[]" class="form-control" accept="image/*" multiple>
      <div class="form-text">Kaydetmeden önce seçtikleriniz aşağıda önizlenir. İstemediğiniz resmi × ile kaldırabilirsiniz.</div>
      <div id="imagesPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-success" type="submit">Kaydet</button>
      <button class="btn btn-primary" type="button" id="saveStayBtn">Ekle (Sayfada Kal)</button>
      <a href="list.php" class="btn btn-outline-secondary">Geri</a>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function(){
  // PHP -> JS
  const subMap      = <?= json_encode($subMap) ?>;
  const languages   = <?= json_encode(array_column($languages, null, 'LangCode')) ?>;
  const defaultLang = <?= json_encode($defaultLang) ?>;

  // 🔹 Kategori -> Alt kategori
  $('#categorySelect').on('change', function(){
    const catId = $(this).val();
    let html = '<option value="">Seçiniz</option>';
    if (catId && subMap[catId]) {
      subMap[catId].forEach(sc => {
        html += `<option value="${sc.SubCategoryID}">${sc.SubCategoryName}</option>`;
      });
    }
    $('#subCategorySelect').html(html);
  });

  // 🔹 Seçenekler (dinamik satırlar, varsayılan dilde yönetilir)
  const defContainer = $('#options-' + defaultLang + '-container');

  function addOptionRow(){
    const newIndex = defContainer.find('.option-row[data-new="1"]').length;

    // Varsayılan dil satırı (ad + fiyat + default radio + sil)
    defContainer.append(
      `<div class="option-row d-flex flex-wrap align-items-center gap-2 mb-2" data-new="1" data-new-index="${newIndex}">
         <input type="text" name="options_new[name][]"  class="form-control" style="min-width:240px" placeholder="Seçenek adı (<?= strtoupper($defaultLang) ?>)">
         <input type="number" step="0.01" name="options_new[price][]" class="form-control" style="max-width:140px" placeholder="Fiyat (₺)">
         <div class="form-check">
           <input class="form-check-input" type="radio" name="options_def[IsDefault]" value="new-${newIndex}" id="optdef_${newIndex}">
           <label class="form-check-label" for="optdef_${newIndex}">Varsayılan</label>
         </div>
         <button type="button" class="btn btn-outline-danger removeOptionBtn">&times;</button>
       </div>`
    );

    // İlk eklemede default'u otomatik seç
    const group = $('input[type="radio"][name="options_def[IsDefault]"]');
    if (group.filter(':checked').length === 0) {
      defContainer.find(`.option-row[data-new-index="${newIndex}"] input[type="radio"][name="options_def[IsDefault]"]`).prop('checked', true);
    }

    // Diğer diller için çeviri adı satırı ekle
    Object.keys(languages).forEach(function(lc){
      if (lc === defaultLang) return;
      $('#options-' + lc + '-container').append(
        `<div class="option-row mb-2" data-new="1" data-new-index="${newIndex}">
           <input type="text" name="options_new_tr[${lc}][name][]" class="form-control" placeholder="Seçenek adı (çeviri: ${lc.toUpperCase()})">
         </div>`
      );
    });
  }

  // Varsayılan dil sekmesine buton
  $(document).on('click', '#addOptionBtn', addOptionRow);

  // seçenek satırı silme (tüm dillerde aynı new-index’i kaldır)
  $(document).on('click', '.removeOptionBtn', function(){
    const idx = $(this).closest('.option-row').data('new-index');
    defContainer.find(`.option-row[data-new="1"][data-new-index="${idx}"]`).remove();
    Object.keys(languages).forEach(function(lc){
      if (lc === defaultLang) return;
      $(`#options-${lc}-container .option-row[data-new="1"][data-new-index="${idx}"]`).remove();
    });
  });

  // Sayfa ilk açılışında tek satır ekle
  $(window).on('load', function(){
    if (defContainer.length && defContainer.find('.option-row').length === 0) {
      addOptionRow();
    }
  });

  // 🔹 Resim önizleme + kaldırma
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

  // 🔹 AJAX "Ekle (Sayfada Kal)"
  $('#saveStayBtn').on('click', function(e){
    e.preventDefault();
    const formEl  = document.getElementById('itemCreateForm');
    const formData = new FormData(formEl);
    const btn = $(this);
    btn.prop('disabled', true).text('Kaydediliyor...');

    $.ajax({
      url: 'create.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      success: function(res){
        $('.alert').remove();
        if (res && res.status === 'success') {
          $('h4.fw-semibold').after('<div class="alert alert-success mt-3">✅ Ürün başarıyla eklendi. Sayfada çalışmaya devam edebilirsiniz.</div>');
          // Formu minimal temizlemek istersen:
          // formEl.reset(); $('#subCategorySelect').html('<option value="">Seçiniz</option>'); $('.option-row').remove(); addOptionRow();
        } else {
          $('h4.fw-semibold').after('<div class="alert alert-danger mt-3">⚠️ Hata: ' + (res.message || 'Bilinmeyen hata') + '</div>');
        }
      },
      error: function(xhr){
        $('.alert').remove();
        $('h4.fw-semibold').after('<div class="alert alert-danger mt-3">Sunucu hatası: ' + xhr.statusText + '</div>');
      },
      complete: function(){
        btn.prop('disabled', false).text('Ekle (Sayfada Kal)');
      }
    });
  });

})();
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
