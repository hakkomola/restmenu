<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$restaurantId = (int)$_SESSION['restaurant_id'];
$message = '';
$error   = '';

/* 🔹 Restoran bilgisi */
$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) {
    header('Location: logout.php');
    exit;
}

/* 🔹 Tüm kullanılabilir diller */
$allLangsStmt = $pdo->query("SELECT LangCode, LangName FROM Languages ORDER BY SortOrder ASC");
$allLanguages = $allLangsStmt->fetchAll(PDO::FETCH_ASSOC);

/* 🔹 Restoranın mevcut dil ayarları */
$selectedLangs = [];
$currentDefaultLang = $restaurant['DefaultLanguage'] ?? null;
$rlStmt = $pdo->prepare("SELECT LangCode, IsDefault FROM RestaurantLanguages WHERE RestaurantID = ?");
$rlStmt->execute([$restaurantId]);
foreach ($rlStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $selectedLangs[$r['LangCode']] = true;
    if (!empty($r['IsDefault'])) $currentDefaultLang = $r['LangCode'];
}

/* 🔹 Görsel silme */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_main']) || isset($_POST['delete_background'])) {
        $field = isset($_POST['delete_main']) ? 'MainImage' : 'BackgroundImage';
        $old = $restaurant[$field];
        if ($old) {
            $path = __DIR__ . '/../' . ltrim($old, '/');
            if (is_file($path)) @unlink($path);
            $pdo->prepare("UPDATE Restaurants SET $field=NULL WHERE RestaurantID=?")->execute([$restaurantId]);
        }
        header('Location: profile.php?success=1');
        exit;
    }
}

/* 🔹 Güncelleme */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_main']) && !isset($_POST['delete_background'])) {
    $name       = trim($_POST['name'] ?? '');
    $nameHTML   = trim($_POST['name_html'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $mapUrl     = trim($_POST['map_url'] ?? '');
    $password   = $_POST['password'] ?? '';
    $themeMode  = $_POST['theme_mode'] ?? 'auto';

    $postedLangs   = isset($_POST['langs']) && is_array($_POST['langs']) ? $_POST['langs'] : [];
    $postedDefault = $_POST['default_lang'] ?? '';

    $validCodes = array_column($allLanguages, 'LangCode');
    $postedLangs = array_values(array_intersect($postedLangs, $validCodes));

    if ($name === '' || $email === '') {
        $error = 'İsim ve e-posta zorunludur.';
    } elseif (empty($postedLangs)) {
        $error = 'En az bir dil seçmelisiniz.';
    } elseif ($postedDefault === '' || !in_array($postedDefault, $postedLangs, true)) {
        $error = 'Varsayılan dil seçilmeli ve seçili dillerden biri olmalı.';
    }

    $mainToSave = $restaurant['MainImage'];
    $bgToSave   = $restaurant['BackgroundImage'];

    if (!$error) {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        // Ana logo
        if (!empty($_FILES['main_image']['name'])) {
            $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['main_image']['name']));
            $target = $uploadsDir . 'main_' . time() . '_' . $safe;
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target)) {
                if ($mainToSave && is_file(__DIR__.'/../'.$mainToSave)) @unlink(__DIR__.'/../'.$mainToSave);
                $mainToSave = 'uploads/' . basename($target);
            } else $error = 'Ana görsel yüklenemedi.';
        }

        // Arka plan
        if (!$error && !empty($_FILES['bg_image']['name'])) {
            $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['bg_image']['name']));
            $target = $uploadsDir . 'bg_' . time() . '_' . $safe;
            if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $target)) {
                if ($bgToSave && is_file(__DIR__.'/../'.$bgToSave)) @unlink(__DIR__.'/../'.$bgToSave);
                $bgToSave = 'uploads/' . basename($target);
            } else $error = 'Arka plan yüklenemedi.';
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Restoran tablosu
            $sql = "UPDATE Restaurants 
                    SET Name=?, NameHTML=?, Email=?, Phone=?, Address=?, MapUrl=?, 
                        MainImage=?, BackgroundImage=?, DefaultLanguage=?, ThemeMode=?";
            $params = [$name,$nameHTML,$email,$phone,$address,$mapUrl,$mainToSave,$bgToSave,$postedDefault,$themeMode];

            if (!empty($password)) {
                $sql .= ", PasswordHash=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE RestaurantID=?";
            $params[] = $restaurantId;
            $pdo->prepare($sql)->execute($params);

            // Diller
            $pdo->prepare("DELETE FROM RestaurantLanguages WHERE RestaurantID=?")->execute([$restaurantId]);
            $ins = $pdo->prepare("INSERT INTO RestaurantLanguages (RestaurantID, LangCode, IsDefault) VALUES (?, ?, ?)");
            foreach ($postedLangs as $lc) {
                $ins->execute([$restaurantId, $lc, ($lc === $postedDefault ? 1 : 0)]);
            }

            $pdo->commit();
            $_SESSION['restaurant_name'] = $name;
            header('Location: profile.php?success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) $message = 'Bilgiler başarıyla güncellendi.';

$pageTitle = "Restoran Bilgilerim";
include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container mt-3" style="max-width:800px;">
  <h4 class="fw-semibold mb-4"><?= htmlspecialchars($pageTitle) ?></h4>

  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card shadow-sm p-4">
    <div class="mb-3">
      <label class="form-label">Restoran Adı</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($restaurant['Name'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Restoran Adı (HTML Başlık)</label>
      <textarea name="name_html" class="form-control" rows="3"><?= htmlspecialchars($restaurant['NameHTML'] ?? '') ?></textarea>
      <div class="form-text">HTML render edilir (örnek: &lt;h1&gt;Portside&lt;/h1&gt;)</div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">E-posta</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($restaurant['Email'] ?? '') ?>" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Telefon</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($restaurant['Phone'] ?? '') ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Adres</label>
      <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($restaurant['Address'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Google Maps Linki</label>
      <input type="text" name="map_url" class="form-control" value="<?= htmlspecialchars($restaurant['MapUrl'] ?? '') ?>">
    </div>

    <hr>

    <!-- 🌐 Diller -->
    <h6 class="fw-semibold mb-3">Desteklenen Diller</h6>
    <?php foreach ($allLanguages as $L): 
      $code = $L['LangCode']; $name = $L['LangName'];
      $checked = isset($selectedLangs[$code]);
      $isDef = ($code === $currentDefaultLang);
    ?>
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="form-check">
          <input type="checkbox" class="form-check-input lang-check" id="lang_<?= $code ?>" name="langs[]" value="<?= $code ?>" <?= $checked ? 'checked' : '' ?>>
          <label for="lang_<?= $code ?>" class="form-check-label"><?= htmlspecialchars($name) ?> (<?= strtoupper($code) ?>)</label>
        </div>
        <div class="form-check">
          <input type="radio" class="form-check-input default-radio" name="default_lang" id="def_<?= $code ?>" value="<?= $code ?>" <?= $isDef ? 'checked' : '' ?> <?= $checked ? '' : 'disabled' ?>>
          <label for="def_<?= $code ?>" class="form-check-label">Varsayılan</label>
        </div>
      </div>
    <?php endforeach; ?>

    <hr>

    <div class="mb-3">
      <label class="form-label">Tema Seçimi</label>
      <select name="theme_mode" class="form-select" style="max-width:250px;">
        <?php
        $theme = $restaurant['ThemeMode'] ?? 'auto';
        $opts = [
          'auto'  => 'Otomatik (Cihaz moduna göre)',
          'light' => 'Açık Tema',
          'dark'  => 'Koyu Tema'
        ];
        foreach ($opts as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $theme===$k?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <hr>

    <!-- 📷 Görseller -->
    <div class="mb-3">
      <label class="form-label">Logo / Ana Görsel</label>
      <?php if (!empty($restaurant['MainImage'])): ?>
        <div class="mb-2"><img src="../<?= htmlspecialchars($restaurant['MainImage']) ?>" style="max-width:300px;border-radius:6px;"></div>
        <button type="submit" name="delete_main" value="1" class="btn btn-danger btn-sm mb-2" onclick="return confirm('Ana görsel silinsin mi?')">Sil</button>
      <?php endif; ?>
      <input type="file" name="main_image" class="form-control" accept="image/*">
    </div>

    <div class="mb-3">
      <label class="form-label">Arka Plan Görseli</label>
      <?php if (!empty($restaurant['BackgroundImage'])): ?>
        <div class="mb-2"><img src="../<?= htmlspecialchars($restaurant['BackgroundImage']) ?>" style="max-width:300px;border-radius:6px;"></div>
        <button type="submit" name="delete_background" value="1" class="btn btn-danger btn-sm mb-2" onclick="return confirm('Arka plan silinsin mi?')">Sil</button>
      <?php endif; ?>
      <input type="file" name="bg_image" class="form-control" accept="image/*">
    </div>

    <div class="d-flex justify-content-end mt-4">
      <button type="submit" class="btn btn-primary">Kaydet</button>
      <a href="../restaurants/dashboard.php" class="btn btn-outline-secondary ms-2">İptal</a>
    </div>
  </form>
</div>

<script>
document.querySelectorAll('.lang-check').forEach(cb=>{
  cb.addEventListener('change', ()=>{
    const code = cb.value;
    const radio = document.getElementById('def_'+code);
    if(cb.checked){ radio.disabled=false; }
    else { radio.checked=false; radio.disabled=true; }
  });
});
</script>

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
