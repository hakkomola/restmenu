<?php
// restaurants/login.php
session_start();
require_once __DIR__ . '/../db.php';

// --- reCAPTCHA ayarları ---
$recaptcha_site_key   = '6LdSadsrAAAAAGfXILtvyPIOQvDxj93ZIpQmXIEC';
$recaptcha_secret_key = '6LdSadsrAAAAAPbdH4_Tgr28n8aM6cFysnUJhYFN';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';

function verify_recaptcha($secret, $response, $remote_ip = null) {
    if (empty($response)) return false;
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = ['secret' => $secret, 'response' => $response];
    if ($remote_ip) $data['remoteip'] = $remote_ip;

    $options = ['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'timeout' => 5
    ]];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return false;
    $json = json_decode($result);
    return ($json && isset($json->success) && $json->success === true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response)) {
        $message = 'Lütfen "Ben robot değilim" doğrulamasını yapın.';
    } elseif (!verify_recaptcha($recaptcha_secret_key, $recaptcha_response, $_SERVER['REMOTE_ADDR'] ?? null)) {
        $message = 'reCAPTCHA doğrulaması başarısız. Lütfen tekrar deneyin.';
    } elseif ($email && $password) {

        // 🔹 Kullanıcı + Roller + İzinleri al
        $stmt = $pdo->prepare("
            SELECT u.*,
                   GROUP_CONCAT(r.RoleName SEPARATOR ',') AS RoleNames,
                   GROUP_CONCAT(r.Permissions SEPARATOR '|||') AS AllPermissionsJson
            FROM RestaurantUsers u
            LEFT JOIN RestaurantUserRoles ur ON ur.UserID = u.UserID
            LEFT JOIN RestaurantRoles r ON r.RoleID = ur.RoleID
            WHERE u.Email = ? AND u.IsActive = 1
            GROUP BY u.UserID
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['PasswordHash'])) {

            // 🔹 Rol bazlı izinleri birleştir
            $permissions = [];
            if (!empty($user['AllPermissionsJson'])) {
                $jsonList = explode('|||', $user['AllPermissionsJson']);
                foreach ($jsonList as $jsonStr) {
                    $permArray = json_decode($jsonStr, true);
                    if (is_array($permArray)) {
                        foreach ($permArray as $key => $val) {
                            $permissions[$key] = ($permissions[$key] ?? false) || (bool)$val;
                        }
                    }
                }
            }

            // 🔹 Kullanıcının yetkili olduğu şubeleri getir
            $stmt2 = $pdo->prepare("
                SELECT b.BranchID, b.BranchName
                FROM RestaurantBranchUsers bu
                JOIN RestaurantBranches b ON b.BranchID = bu.BranchID
                WHERE bu.UserID = ?
                ORDER BY b.BranchName
            ");
            $stmt2->execute([$user['UserID']]);
            $branches = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // 🔹 Oturum verileri
            $_SESSION['user_id']        = $user['UserID'];
            $_SESSION['restaurant_id']  = $user['RestaurantID'];
            $_SESSION['role_name']      = $user['RoleNames'];
            $_SESSION['permissions']    = $permissions;
            $_SESSION['branches']       = $branches;

            // ✅ sadece kendi restoranında “Admin” rolü varsa is_admin = true
            $_SESSION['is_admin'] = false;
            if (!empty($user['RoleNames'])) {
                $roles = array_map('trim', explode(',', $user['RoleNames']));
                if (in_array('Admin', $roles, true)) {
                    $_SESSION['is_admin'] = true;
                }
            }

            // 🔹 Şube seçimi / yönlendirme
            if (count($branches) === 1) {
                $_SESSION['current_branch'] = $branches[0]['BranchID'];
                header('Location: dashboard.php');
                exit;
            } elseif (count($branches) > 1) {
                header('Location: select_branch.php');
                exit;
            } else {
                $message = 'Bu kullanıcıya henüz bir şube atanmadı.';
            }

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
  <title>Restoran Girişi - VovMenu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:500px;">
  <h3 class="text-center mb-4">Restoran Girişi</h3>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="mb-3">
      <label for="email" class="form-label">E-posta</label>
      <input type="email" name="email" id="email" class="form-control" required value="<?= htmlspecialchars($email ?? '') ?>">
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Şifre</label>
      <input type="password" name="password" id="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptcha_site_key) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
    <a href="register.php" class="d-block text-center mt-3">Henüz üye değil misiniz? Üye Ol</a>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
