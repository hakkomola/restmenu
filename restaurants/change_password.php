<?php
session_start();
if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

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

// 🔹 HEADER ve NAVBAR dahil
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';


?>


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
            <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
            <a href="../restaurants/dashboard.php" class="btn btn-secondary">Geri</a>

        </div>
    </form>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>