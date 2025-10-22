<?php
session_start();
require_once __DIR__ . '/../db.php';

// Giriş yapılmamışsa login'e dön
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcının yetkili olduğu şubeleri session’dan al
$branches = $_SESSION['branches'] ?? [];
if (empty($branches)) {
    die('Bu kullanıcıya atanmış herhangi bir şube bulunamadı.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchId = (int)($_POST['branch_id'] ?? 0);

    // Seçilen şube geçerli mi?
    $valid = false;
    foreach ($branches as $b) {
        if ((int)$b['BranchID'] === $branchId) {
            $valid = true;
            break;
        }
    }

    if ($valid) {
        $_SESSION['current_branch'] = $branchId;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Geçersiz şube seçimi.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Şube Seçimi - VovMenu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
  <div class="container text-center" style="max-width: 500px;">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-4">
        <h4 class="mb-3 fw-semibold text-primary">
          <i class="bi bi-building me-2"></i>Şube Seçimi
        </h4>
        <p class="text-muted mb-4">
          Lütfen işlem yapmak istediğiniz şubeyi seçin.
        </p>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="mb-4">
            <select name="branch_id" class="form-select form-select-lg border-primary" required>
              <option value="">Bir şube seçin...</option>
              <?php foreach ($branches as $b): ?>
                <option value="<?= $b['BranchID'] ?>">
                  <?= htmlspecialchars($b['BranchName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="bi bi-check-circle me-1"></i> Şubeyi Seç ve Devam Et
          </button>

          <a href="logout.php" class="btn btn-link text-secondary d-block mt-3">
            <i class="bi bi-box-arrow-left me-1"></i> Çıkış Yap
          </a>
        </form>
      </div>
    </div>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
