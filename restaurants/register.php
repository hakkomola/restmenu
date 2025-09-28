<?php
// restaurants/register.php
session_start();

require_once dirname(__DIR__) . '/db.php';    // ✅


if (isset($_SESSION['restaurant_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    if ($name && $email && $password) {
        // E-posta zaten kayıtlı mı kontrol et
        $stmt = $pdo->prepare('SELECT * FROM Restaurants WHERE Email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $message = 'Bu e-posta zaten kayıtlı.';
        } else {
            // Şifreyi hashle
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO Restaurants (Name, Email, PasswordHash, Phone, Address) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $passwordHash, $phone, $address]);
            $_SESSION['restaurant_id'] = $pdo->lastInsertId();
            $_SESSION['restaurant_name'] = $name;
            header('Location: dashboard.php');
            exit;
        }
    } else {
        $message = 'Lütfen tüm zorunlu alanları doldurun.';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Üye Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4">Restoran Üye Ol</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label>Restoran Adı *</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>E-posta *</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Şifre *</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Telefon</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="mb-3">
            <label>Adres</label>
            <textarea name="address" class="form-control"></textarea>
        </div>
        <button class="btn btn-success w-100">Üye Ol</button>
        <a href="login.php" class="d-block mt-3 text-center">Zaten üye misiniz? Giriş Yap</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
