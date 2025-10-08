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

/**
 * PUBLIC HASH ÜRETİMİ
 */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}
function table_public_hash(int $restaurantId, string $code): string {
    return substr(hash('sha256', $restaurantId . '|' . $code . '|' . RESTMENU_HASH_PEPPER), 0, 24);
}

// === MASA EKLEME / SİLME / TOGGLE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            try {
                $code = substr(md5(uniqid(mt_rand(), true)), 0, 16);
                $stmt = $pdo->prepare("INSERT INTO RestaurantTables (RestaurantID, Name, Code, CreatedAt) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$restaurantId, $name, $code]);
                $message = 'Masa başarıyla eklendi.';
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'uniq_rest_table_name') !== false) {
                    $error = 'Bu isimde bir masa zaten var.';
                } elseif (strpos($msg, 'uniq_code') !== false) {
                    $error = 'Kod çakışması oluştu, lütfen tekrar deneyin.';
                } else {
                    $error = 'Kayıt sırasında hata oluştu: ' . $msg;
                }
            }
        } else {
            $error = 'Lütfen masa adı girin.';
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['table_id'])) {
        $tableId = (int) $_POST['table_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM RestaurantTables WHERE TableID = ? AND RestaurantID = ?");
            $stmt->execute([$tableId, $restaurantId]);
            $message = 'Masa silindi.';
        } catch (Exception $e) {
            $error = 'Silme işlemi başarısız: ' . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'toggle' && isset($_POST['table_id'])) {
        $tableId = (int) $_POST['table_id'];
        try {
            $stmt = $pdo->prepare("UPDATE RestaurantTables SET IsActive = NOT IsActive WHERE TableID = ? AND RestaurantID = ?");
            $stmt->execute([$tableId, $restaurantId]);
            $message = 'Masa durumu güncellendi.';
        } catch (Exception $e) {
            $error = 'Durum güncellenemedi: ' . $e->getMessage();
        }
    }
}

// === MASALARI ÇEK ===
$stmt = $pdo->prepare("SELECT * FROM RestaurantTables WHERE RestaurantID = ? ORDER BY CreatedAt DESC");
$stmt->execute([$restaurantId]);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = str_replace('/restaurants','', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <title>Masalar | Restoran</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .table-actions form { display:inline-block; margin-right:.25rem; }
    .qr-preview img { width:100px; height:100px; object-fit:contain; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="../restaurants/dashboard.php">Ana Sayfa</a>
    <div class="d-flex">
      <span class="navbar-text me-3">Hoşgeldin, <?= htmlspecialchars($_SESSION['restaurant_name'] ?? 'Restoran') ?></span>
      <a href="../restaurants/logout.php" class="btn btn-light">Çıkış Yap</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <h1 class="h4 mb-3">Masa Tanımları</h1>

  <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Yeni Masa -->
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-3">Yeni Masa Ekle</h2>
          <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
              <label class="form-label">Masa Adı</label>
              <input type="text" name="name" class="form-control" maxlength="50" placeholder="Örn: Masa 1, Bahçe 3" required>
            </div>
            <button class="btn btn-primary">Ekle</button>
          </form>
        </div>
      </div>
      <div class="small text-muted mt-3">
        İpucu: “Pasif Yap” ile QR’ı geçici olarak devre dışı bırakabilirsiniz.
      </div>
    </div>

    <!-- Masa Listesi -->
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-3">Tanımlı Masalar</h2>

          <?php if (count($tables) > 0): ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Masa Adı</th>
                    <th>Durum</th>
                    <th>Link & QR</th>
                    <th>İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($tables as $i => $t):
                      $publicHash = table_public_hash($restaurantId, $t['Code']);
                      $linkLight  = $scheme.'://'.$host.$base.'/restaurant_info.php?hash='.urlencode($publicHash).'&theme=light';
                      $linkDark   = $scheme.'://'.$host.$base.'/restaurant_info.php?hash='.urlencode($publicHash).'&theme=dark';
                      $qrLight    = $scheme.'://'.$host.$base.'/generate_qr.php?hash='.urlencode($publicHash).'&theme=light';
                      $qrDark     = $scheme.'://'.$host.$base.'/generate_qr.php?hash='.urlencode($publicHash).'&theme=dark';
                  ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($t['Name']) ?></td>
                    <td><?= $t['IsActive'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>' ?></td>
                    <td class="qr-preview">
                      <div><strong>Light:</strong> <a href="<?= htmlspecialchars($linkLight) ?>" target="_blank">Link</a></div>
                      <img src="<?= htmlspecialchars($qrLight) ?>" alt="Light QR">
                      <div class="mt-2"><strong>Dark:</strong> <a href="<?= htmlspecialchars($linkDark) ?>" target="_blank">Link</a></div>
                      <img src="<?= htmlspecialchars($qrDark) ?>" alt="Dark QR">
                    </td>
                    <td class="table-actions">
                      <form method="post">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="table_id" value="<?= (int)$t['TableID'] ?>">
                        <button class="btn btn-sm <?= $t['IsActive'] ? 'btn-warning' : 'btn-success' ?>">
                          <?= $t['IsActive'] ? 'Pasif Yap' : 'Aktif Yap' ?>
                        </button>
                      </form>

                      <form method="post" onsubmit="return confirm('Bu masayı silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="table_id" value="<?= (int)$t['TableID'] ?>">
                        <button class="btn btn-sm btn-danger">Sil</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-muted">Henüz masa eklenmemiş.</div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
