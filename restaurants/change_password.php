<?php
session_start();
if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

$restaurantId = $_SESSION['restaurant_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword     = trim($_POST['oldPassword'] ?? '');
    $newPassword     = trim($_POST['newPassword'] ?? '');
    $confirmPassword = trim($_POST['confirmPassword'] ?? '');

    if ($newPassword === '' || $confirmPassword === '' || $oldPassword === '') {
        $error = 'Tüm alanları doldurun.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Yeni şifreler eşleşmiyor.';
    } else {
        // Mevcut şifre kontrolü
        $stmt = $pdo->prepare("SELECT PasswordHash FROM Restaurants WHERE RestaurantID = ?");
        $stmt->execute([$restaurantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($oldPassword, $row['PasswordHash'])) {
            $error = 'Mevcut şifre hatalı.';
        } else {
            // Güncelle
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE Restaurants SET PasswordHash = ? WHERE RestaurantID = ?");
            $upd->execute([$newHash, $restaurantId]);
            $message = 'Şifre başarıyla güncellendi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Şifre Değiştir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:500px;">
    <h2 class="mb-4 text-center">Şifre Değiştir</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
        <div class="mb-3">
            <label class="form-label">Mevcut Şifre</label>
            <input type="password" name="oldPassword" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Yeni Şifre</label>
            <input type="password" name="newPassword" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Yeni Şifre (Tekrar)</label>
            <input type="password" name="confirmPassword" class="form-control" required>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="../restaurants/dashboard.php" class="btn btn-secondary">Geri</a>
            <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
