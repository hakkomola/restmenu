<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$message = '';
$error = '';

include __DIR__ . '/../includes/navbar.php';

// Restoran bilgileri çek
$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$restaurant) {
    header('Location: logout.php');
    exit;
}

// Her iki resim için ayrı silme işlemleri
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

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_main']) && !isset($_POST['delete_background'])) {
    $name       = trim($_POST['name'] ?? '');
    $nameHTML   = trim($_POST['name_html'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($name === '' || $email === '') {
        $error = 'İsim ve e-posta zorunludur.';
    } else {
        $bgToSave = $restaurant['BackgroundImage'];
        $mainToSave = $restaurant['MainImage'];

        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

        // Ana görsel yükleme
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

        // Arka plan yükleme
        if (!empty($_FILES['bg_image']['name'])) {
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

        if ($error === '') {
            $sql = "UPDATE Restaurants 
                    SET Name=?, NameHTML=?, Email=?, Phone=?, Address=?, 
                        MainImage=?, BackgroundImage=?";
            $params = [$name, $nameHTML, $email, $phone, $address, $mainToSave, $bgToSave];

            if (!empty($password)) {
                $sql .= ", PasswordHash = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE RestaurantID = ?";
            $params[] = $restaurantId;

            $pdo->prepare($sql)->execute($params);
            $_SESSION['restaurant_name'] = $name;
            header('Location: dashboard.php');
            exit;
        }
    }
}

if (isset($_GET['success'])) $message = 'İşlem başarıyla gerçekleştirildi.';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Restoran Profili</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <label class="form-label">Yeni Şifre (boş bırakılırsa değişmez)</label>
            <input type="password" name="password" class="form-control">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
