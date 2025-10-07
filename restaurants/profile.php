<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

$restaurantId = (int)$_SESSION['restaurant_id'];
$message = '';
$error = '';

// Restoran bilgileri
$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) {
    header('Location: logout.php');
    exit;
}

// Tüm kullanılabilir diller
try {
    $allLangsStmt = $pdo->query("SELECT LangCode, LangName FROM Languages ORDER BY SortOrder ASC");
    $allLanguages = $allLangsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allLanguages = [];
}

// Restoranın mevcut dil ayarları
$selectedLangs = [];
$currentDefaultLang = $restaurant['DefaultLanguage'] ?? null;
try {
    $rlStmt = $pdo->prepare("SELECT LangCode, IsDefault FROM RestaurantLanguages WHERE RestaurantID = ?");
    $rlStmt->execute([$restaurantId]);
    $rows = $rlStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $selectedLangs[$r['LangCode']] = true;
        if (!empty($r['IsDefault'])) {
            $currentDefaultLang = $r['LangCode'];
        }
    }
} catch (Exception $e) { /* yoksay */ }

// Görsel silme işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_main'])) {
        if (!empty($restaurant['MainImage'])) {
            $oldPath = __DIR__ . '/../' . ltrim($restaurant['MainImage'], '/');
            if (file_exists($oldPath)) @unlink($oldPath);
        }
        $pdo->prepare("UPDATE Restaurants SET MainImage = NULL WHERE RestaurantID = ?")->execute([$restaurantId]);
        header('Location: profile.php?success=1');
        exit;
    }

    if (isset($_POST['delete_background'])) {
        if (!empty($restaurant['BackgroundImage'])) {
            $oldPath = __DIR__ . '/../' . ltrim($restaurant['BackgroundImage'], '/');
            if (file_exists($oldPath)) @unlink($oldPath);
        }
        $pdo->prepare("UPDATE Restaurants SET BackgroundImage = NULL WHERE RestaurantID = ?")->execute([$restaurantId]);
        header('Location: profile.php?success=1');
        exit;
    }
}

// Güncelleme (ana form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_main']) && !isset($_POST['delete_background'])) {
    $name       = trim($_POST['name'] ?? '');
    $nameHTML   = trim($_POST['name_html'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $mapUrl     = trim($_POST['map_url'] ?? '');
    $password   = $_POST['password'] ?? '';

    // Dil seçimleri
    $postedLangs   = isset($_POST['langs']) && is_array($_POST['langs']) ? $_POST['langs'] : [];
    $postedDefault = $_POST['default_lang'] ?? '';

    // Sadece geçerli kodlara izin ver
    $validCodes = array_column($allLanguages, 'LangCode');
    $postedLangs = array_values(array_intersect($postedLangs, $validCodes));

    // Doğrulama
    if ($name === '' || $email === '') {
        $error = 'İsim ve e-posta zorunludur.';
    } elseif (empty($postedLangs)) {
        $error = 'En az bir dil seçmelisiniz.';
    } elseif ($postedDefault === '' || !in_array($postedDefault, $postedLangs, true)) {
        $error = 'Varsayılan dil seçilmeli ve seçili dillerden biri olmalı.';
    }

    // Görsel işlemleri (hata yoksa devam)
    $bgToSave   = $restaurant['BackgroundImage'];
    $mainToSave = $restaurant['MainImage'];

    if ($error === '') {
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        if (!empty($_FILES['main_image']['name'])) {
            $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['main_image']['name']));
            $fileName = 'main_' . time() . '_' . $safeName;
            $target = $uploadsDir . $fileName;
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target)) {
                if (!empty($restaurant['MainImage'])) {
                    $oldPath = __DIR__ . '/../' . ltrim($restaurant['MainImage'], '/');
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $mainToSave = 'uploads/' . $fileName;
            } else {
                $error = 'Ana görsel yüklenemedi.';
            }
        }

        if ($error === '' && !empty($_FILES['bg_image']['name'])) {
            $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['bg_image']['name']));
            $fileName = 'bg_' . time() . '_' . $safeName;
            $target = $uploadsDir . $fileName;
            if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $target)) {
                if (!empty($restaurant['BackgroundImage'])) {
                    $oldPath = __DIR__ . '/../' . ltrim($restaurant['BackgroundImage'], '/');
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $bgToSave = 'uploads/' . $fileName;
            } else {
                $error = 'Arka plan yüklenemedi.';
            }
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            // Restaurants güncelle (DefaultLanguage dahil)
            $sql = "UPDATE Restaurants SET Name=?, NameHTML=?, Email=?, Phone=?, Address=?, MapUrl=?, MainImage=?, BackgroundImage=?, DefaultLanguage=?";
            $params = [$name, $nameHTML, $email, $phone, $address, $mapUrl, $mainToSave, $bgToSave, $postedDefault];

            if (!empty($password)) {
                $sql .= ", PasswordHash = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE RestaurantID = ?";
            $params[] = $restaurantId;

            $pdo->prepare($sql)->execute($params);

            // RestaurantLanguages güncelle
            // 1) Seçili olmayanları sil
            if (!empty($postedLangs)) {
                $placeholders = implode(',', array_fill(0, count($postedLangs), '?'));
                $del = $pdo->prepare("DELETE FROM RestaurantLanguages WHERE RestaurantID=? AND LangCode NOT IN ($placeholders)");
                $del->execute(array_merge([$restaurantId], $postedLangs));
            } else {
                // Teorik olarak buraya girmeyecek (validation var), ama güvenlik için:
                $pdo->prepare("DELETE FROM RestaurantLanguages WHERE RestaurantID=?")->execute([$restaurantId]);
            }

            // 2) Seçili olanları ekle/güncelle
            $ins = $pdo->prepare("
                INSERT INTO RestaurantLanguages (RestaurantID, LangCode, IsDefault)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE IsDefault = VALUES(IsDefault)
            ");
            foreach ($postedLangs as $lc) {
                $ins->execute([$restaurantId, $lc, ($lc === $postedDefault ? 1 : 0)]);
            }

            $pdo->commit();

            $_SESSION['restaurant_name'] = $name;
            header('Location: ../restaurants/dashboard.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/navbar.php';
if (isset($_GET['success'])) $message = 'İşlem başarıyla gerçekleştirildi.';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Restoran Profili</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.lang-row { display:flex; align-items:center; gap:12px; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; }
.lang-row + .lang-row { margin-top:8px; }
.lang-row .spacer { flex:1; }
</style>
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:800px;">
    <h2 class="mb-4">Restoran Bilgilerim</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <div class="mb-3">
            <label class="form-label">Restoran Adı</label>
            <input type="text" name="name" class="form-control" 
                   value="<?= htmlspecialchars($restaurant['Name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Restoran Adı (HTML Başlık - isteğe bağlı)</label>
            <textarea name="name_html" class="form-control" rows="3"
                      placeholder="<h1>Portside</h1><div>Food - Drink - More</div>"><?= htmlspecialchars($restaurant['NameHTML'] ?? '') ?></textarea>
            <div class="form-text">Bu alan doldurulursa restaurant_info.php'de HTML render edilir.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">E-posta</label>
            <input type="email" name="email" class="form-control" 
                   value="<?= htmlspecialchars($restaurant['Email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-control" 
                   value="<?= htmlspecialchars($restaurant['Phone'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Adres</label>
            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($restaurant['Address'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Konum Linki (Google Maps URL)</label>
            <input type="text" name="map_url" class="form-control" 
                   placeholder="https://goo.gl/maps/..." 
                   value="<?= htmlspecialchars($restaurant['MapUrl'] ?? '') ?>">
            <div class="form-text">Müşterilerin haritada sizi bulabilmesi için Google Maps bağlantısını ekleyin.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Yeni Şifre (boş bırakılırsa değişmez)</label>
            <input type="password" name="password" class="form-control">
        </div>

        <hr>

        <!-- Diller -->
        <div class="mb-3">
            <label class="form-label">Kullanılacak Diller</label>
            <?php if (empty($allLanguages)): ?>
                <div class="alert alert-warning">Dil listesi bulunamadı. Lütfen sistem yöneticisine başvurun.</div>
            <?php else: ?>
                <?php foreach ($allLanguages as $L):
                    $code = $L['LangCode'];
                    $name = $L['LangName'];
                    $checked = isset($selectedLangs[$code]);
                    $isDefault = $checked && ($code === $currentDefaultLang);
                ?>
                    <div class="lang-row">
                        <div class="form-check">
                            <input class="form-check-input lang-check" type="checkbox"
                                   id="lang_<?= htmlspecialchars($code) ?>"
                                   name="langs[]" value="<?= htmlspecialchars($code) ?>"
                                   <?= $checked ? 'checked' : '' ?>>
                            <label class="form-check-label" for="lang_<?= htmlspecialchars($code) ?>">
                                <?= htmlspecialchars($name) ?> (<?= strtoupper(htmlspecialchars($code)) ?>)
                            </label>
                        </div>
                        <div class="spacer"></div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input default-radio" type="radio"
                                   name="default_lang" id="def_<?= htmlspecialchars($code) ?>"
                                   value="<?= htmlspecialchars($code) ?>"
                                   <?= $isDefault ? 'checked' : '' ?>
                                   <?= $checked ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="def_<?= htmlspecialchars($code) ?>">Varsayılan</label>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="form-text mt-2">
                    En az bir dil seçin ve biri <b>Varsayılan</b> olsun. Varsayılan dil menü/metin gösteriminde önceliklidir.
                </div>
            <?php endif; ?>
        </div>

        <hr>

        <!-- Ana Görsel -->
        <div class="mb-3">
            <label class="form-label">Restoran Ana Görsel / Logo</label>
            <?php if (!empty($restaurant['MainImage'])): ?>
                <div class="mb-2">
                    <img src="../<?= htmlspecialchars(ltrim($restaurant['MainImage'], '/')) ?>" 
                         alt="Ana Görsel" style="max-width:300px; border:1px solid #ccc; border-radius:6px;">
                </div>
                <button type="submit" name="delete_main" value="1"
                        class="btn btn-danger mb-2"
                        onclick="return confirm('Ana görseli silmek istiyor musunuz?')">
                    Ana Görseli Sil
                </button>
            <?php endif; ?>
            <input type="file" name="main_image" class="form-control mt-2" accept="image/*">
            <div class="form-text">Yeni bir ana görsel seçersen eski silinir.</div>
        </div>

        <!-- Arka Plan Görseli -->
        <div class="mb-3">
            <label class="form-label">Restoran Arka Plan Görseli</label>
            <?php if (!empty($restaurant['BackgroundImage'])): ?>
                <div class="mb-2">
                    <img src="../<?= htmlspecialchars(ltrim($restaurant['BackgroundImage'], '/')) ?>" 
                         alt="Arka Plan" style="max-width:300px; border:1px solid #ccc; border-radius:6px;">
                </div>
                <button type="submit" name="delete_background" value="1"
                        class="btn btn-danger mb-2"
                        onclick="return confirm('Arka plan resmini silmek istiyor musunuz?')">
                    Arka Planı Sil
                </button>
            <?php endif; ?>
            <input type="file" name="bg_image" class="form-control mt-2" accept="image/*">
            <div class="form-text">Yeni bir arka plan resmi seçersen eski otomatik silinir.</div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <a href="../restaurants/dashboard.php" class="btn btn-secondary">Geri</a>
        </div>
    </form>
</div>

<script>
(function(){
    // Checkbox -> Varsayılan radio enable/disable
    document.querySelectorAll('.lang-check').forEach(cb => {
        cb.addEventListener('change', () => {
            const code = cb.value;
            const radio = document.getElementById('def_' + code);
            if (cb.checked) {
                radio.removeAttribute('disabled');
            } else {
                radio.checked = false;
                radio.setAttribute('disabled', 'disabled');
            }
        });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
