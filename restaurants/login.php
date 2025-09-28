<?php
// restaurants/login.php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_SESSION['restaurant_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare('SELECT * FROM Restaurants WHERE Email = ?');
        $stmt->execute([$email]);
        $restaurant = $stmt->fetch();

        if ($restaurant && password_verify($password, $restaurant['PasswordHash'])) {
            $_SESSION['restaurant_id'] = $restaurant['RestaurantID'];
            $_SESSION['restaurant_name'] = $restaurant['Name'];
            header('Location: dashboard.php');
            exit;
        } else {
            $message = 'E-posta veya şifre hatalı.';
        }
    } else {
        $message = 'Lütfen e-posta ve şifre girin.';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Giriş Yap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4">Restoran Giriş Yap</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label>E-posta</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Şifre</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Giriş Yap</button>
        <a href="register.php" class="d-block mt-3 text-center">Henüz üye değil misiniz? Üye Ol</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
