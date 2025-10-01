<?php
// restaurants/login.php
session_start();
require_once __DIR__ . '/../db.php';

// --- reCAPTCHA ayarları ---
$recaptcha_site_key = '6LdSadsrAAAAAGfXILtvyPIOQvDxj93ZIpQmXIEC';    // Google'dan aldığın site key
$recaptcha_secret_key = '6LdSadsrAAAAAPbdH4_Tgr28n8aM6cFysnUJhYFN'; // Google'dan aldığın secret key

if (isset($_SESSION['restaurant_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';

/**
 * reCAPTCHA doğrulama fonksiyonu.
 */
function verify_recaptcha($secret, $response, $remote_ip = null) {
    if (empty($response)) return false;

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $response
    ];
    if ($remote_ip) $data['remoteip'] = $remote_ip;

    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($result);
        return ($json && isset($json->success) && $json->success === true);
    } else {
        $opts = ['http' =>
            [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];
        $context  = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) return false;
        $json = json_decode($result);
        return ($json && isset($json->success) && $json->success === true);
    }
}

// Form gönderimi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // reCAPTCHA kontrolü
    if (empty($recaptcha_response)) {
        $message = 'Lütfen "Ben robot değilim" doğrulamasını yapın.';
    } else {
        $recaptcha_ok = verify_recaptcha($recaptcha_secret_key, $recaptcha_response, $_SERVER['REMOTE_ADDR'] ?? null);

        if (!$recaptcha_ok) {
            $message = 'reCAPTCHA doğrulaması başarısız. Lütfen tekrar deneyin.';
        } else {
            // Normal login akışı
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
    <!-- reCAPTCHA script -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4">Restoran Giriş Yap</h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <div class="mb-3">
            <label>E-posta</label>
            <input type="email" name="email" class="form-control" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
        </div>
        <div class="mb-3">
            <label>Şifre</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <!-- reCAPTCHA widget -->
        <div class="mb-3">
            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptcha_site_key) ?>"></div>
        </div>

        <button class="btn btn-primary w-100" type="submit">Giriş Yap</button>
        <a href="register.php" class="d-block mt-3 text-center">Henüz üye değil misiniz? Üye Ol</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
