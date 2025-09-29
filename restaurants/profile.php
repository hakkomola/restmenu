<?php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/navbar.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$message = '';

// Restoran bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID=?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch();
if (!$restaurant) {
    die("Restoran bulunamadı!");
}

// Form gönderildiğinde güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    if (!$name || !$email) {
        $message = 'İsim ve e-posta boş olamaz.';
    } else {
        $update = $pdo->prepare("UPDATE Restaurants SET Name=?,  Phone=?, Address=? WHERE RestaurantID=?");
        $update->execute([$name,  $phone, $address, $restaurantId]);
        $message = 'Bilgiler güncellendi.';
        // Güncellenmiş verileri tekrar çek
        $stmt->execute([$restaurantId]);
        $restaurant = $stmt->fetch();
    }
    header('Location: dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restoran Bilgilerim</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 700px;">
    <h2 class="mb-4">Restoran Bilgilerim</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label>Restoran Adı</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($restaurant['Name']) ?>" required>
        </div>

        <div class="mb-3">
            <label>E-posta</label>
            <input type="email" name="email" disabled="disabled" class="form-control" value="<?= htmlspecialchars($restaurant['Email']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Telefon</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($restaurant['Phone']) ?>">
        </div>

        <div class="mb-3">
            <label>Adres</label>
            <textarea name="address" class="form-control"><?= htmlspecialchars($restaurant['Address']) ?></textarea>
        </div>

        <button class="btn btn-primary">Güncelle</button>
        <a href="../restaurants/dashboard.php" class="btn btn-secondary">Geri</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
