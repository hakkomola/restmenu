<?php
// restaurants/login.php
session_start();
require_once __DIR__ . '/../db.php';
include __DIR__ . '/../includes/mainnavbar.php';

if (isset($_SESSION['restaurant_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Google reCAPTCHA secret (prod'ta env değişkeni önerilir)
$recaptcha_secret_key = '6LfbCNsrAAAAALP3KsC9Tb50lMmQ6LEZQ_O0y-tf';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // Basit boş alan kontrolleri
    if ($email === '' || $password === '') {
        $message = 'Lütfen e-posta ve şifre girin.';
    } elseif (empty($recaptchaResponse)) {
        $message = 'Lütfen reCAPTCHA\'yı onaylayın.';
    } else {
        // reCAPTCHA doğrulaması (Google ile server-side)
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $recaptcha_secret_key,
            'response' => $recaptchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        // cURL ile doğrula
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $curlResponse = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlResponse === false) {
            $message = 'reCAPTCHA doğrulaması sırasında hata oluştu. Lütfen tekrar deneyin.';
        } else {
            $result = json_decode($curlResponse, true);
            if (!isset($result['success']) || $result['success'] !== true) {
                // Opsiyonel: $result içindeki 'error-codes' kontrol edilebilir
                $message = 'reCAPTCHA doğrulanamadı. Lütfen tekrar deneyin.';
            } else {
                // reCAPTCHA geçti -> veritabanı kontrolü
                try {
                    $stmt = $pdo->prepare('SELECT * FROM Restaurants WHERE Email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($restaurant && password_verify($password, $restaurant['PasswordHash'])) {
                        // Aktif alanı farklı isimlerde olabilir. Önce 'aktif', sonra 'Active', yoksa varsayılan 1
                        $isActive = 1;
                        if (isset($restaurant['aktif'])) {
                            $isActive = (int)$restaurant['aktif'];
                        } elseif (isset($restaurant['Active'])) {
                            $isActive = (int)$restaurant['Active'];
                        } elseif (isset($restaurant['IsActive'])) { // ihtimale karışık isim
                            $isActive = (int)$restaurant['IsActive'];
                        }

                        if ($isActive === 1) {
                            // Başarılı giriş
                            $_SESSION['restaurant_id'] = $restaurant['RestaurantID'];
                            $_SESSION['restaurant_name'] = $restaurant['Name'] ?? '';
                            header('Location: dashboard.php');
                            exit;
                        } else {
                            $message = 'Hesabınız henüz aktif değil. Lütfen e-postanızı kontrol edip doğrulama linkine tıklayın veya yöneticinize başvurun.';
                        }
                    } else {
                        $message = 'E-posta veya şifre hatalı.';
                    }
                } catch (PDOException $e) {
                    // Hata detayını loglayıp kullanıcıya genel mesaj ver
                    error_log('DB error on login: ' . $e->getMessage());
                    $message = 'Sunucu ile bağlantı kurulurken hata oluştu. Lütfen daha sonra tekrar deneyin.';
                }
            }
        }
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
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
            <input type="email" name="email" class="form-control" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Şifre</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <!-- reCAPTCHA widget -->
        <div class="mb-3 text-center">
            <div class="g-recaptcha" data-sitekey="6LfbCNsrAAAAAIEP-1Rt3VGocRhskPA0JGXtVBo0"></div>
        </div>

        <button class="btn btn-primary w-100" type="submit">Giriş Yap</button>
        <a href="register.php" class="d-block mt-3 text-center">Henüz üye değil misiniz? Üye Ol</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
