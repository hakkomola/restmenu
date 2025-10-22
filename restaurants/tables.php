<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$restaurantId   = (int)$_SESSION['restaurant_id'];
$currentBranch  = $_SESSION['current_branch'] ?? null;
$message = '';
$error   = '';

/* 🔹 Restoran ve tema bilgisi */
$stmt = $pdo->prepare("SELECT ThemeMode FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
$themeMode = $restaurant['ThemeMode'] ?? 'auto';
if ($themeMode === 'auto') $themeMode = 'light'; // fallback

/* 🔹 HASH FONKSİYONU (RestaurantID + BranchID + MasaCode) */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}
function table_public_hash(int $restaurantId, ?int $branchId, string $code): string {
    $branchVal = $branchId ?? 0;
    return substr(hash('sha256', $restaurantId . '|' . $branchVal . '|' . $code . '|' . RESTMENU_HASH_PEPPER), 0, 32);
}

/* 🔹 İşlemler */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('Masa adı boş olamaz.');
            $code = substr(md5(uniqid(mt_rand(), true)), 0, 16);
            $pdo->prepare("INSERT INTO RestaurantTables (RestaurantID, BranchID, Name, Code, CreatedAt) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$restaurantId, $currentBranch, $name, $code]);
            $message = 'Masa başarıyla eklendi.';
        }

        if ($_POST['action'] === 'toggle') {
            $id = (int)$_POST['table_id'];
            $pdo->prepare("UPDATE RestaurantTables SET IsActive = NOT IsActive WHERE TableID=? AND RestaurantID=?")
                ->execute([$id, $restaurantId]);
            $message = 'Masa durumu değiştirildi.';
        }

        if ($_POST['action'] === 'delete') {
            $id = (int)$_POST['table_id'];
            $pdo->prepare("DELETE FROM RestaurantTables WHERE TableID=? AND RestaurantID=?")
                ->execute([$id, $restaurantId]);
            $message = 'Masa silindi.';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/* 🔹 Masalar */
$query = "SELECT * FROM RestaurantTables WHERE RestaurantID = ?";
$params = [$restaurantId];
if ($currentBranch) {
    $query .= " AND (BranchID IS NULL OR BranchID = ?)";
    $params[] = $currentBranch;
}
$query .= " ORDER BY CreatedAt DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 🔹 URL bazları */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = str_replace('/restaurants', '', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

include __DIR__ . '/../includes/bo_header.php';
?>

<div class="container py-4">
  <h4 class="fw-semibold mb-4">Masa Tanımları</h4>

  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="row g-4">
    <!-- Yeni Masa -->
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-3 fw-semibold">Yeni Masa Ekle</h6>
          <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="mb-3">
              <label class="form-label">Masa Adı</label>
              <input type="text" name="name" class="form-control" maxlength="50" placeholder="Örn: Masa 1" required>
            </div>
            <button class="btn btn-primary">Ekle</button>
          </form>
        </div>
      </div>
      <small class="text-muted d-block mt-3">İpucu: “Pasif Yap” seçeneği QR kodu devre dışı bırakır.</small>
    </div>

    <!-- Masa Listesi -->
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-3 fw-semibold">Tanımlı Masalar</h6>
          <?php if ($tables): ?>
            <div class="table-responsive">
              <table class="table align-middle table-bordered">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Masa Adı</th>
                    <th>Durum</th>
                    <th>QR Kod</th>
                    <th style="width:200px;">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($tables as $i => $t): 
                    $branchParam = $t['BranchID'] ?? $currentBranch ?? 0;
                    // 🔹 BranchID de hash içine dahil ediliyor
                    $hash = table_public_hash($restaurantId, (int)$branchParam, $t['Code']);
                    $link = "$scheme://$host$base/restaurant_info.php?hash=" . urlencode($hash) . "&theme=" . urlencode($themeMode);
                    $qr   = "$scheme://$host$base/generate_qr.php?hash=" . urlencode($hash) . "&theme=" . urlencode($themeMode);
                  ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($t['Name']) ?></strong></td>
                    <td><?= $t['IsActive'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>' ?></td>
                    <td class="text-center">
                      <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="d-block mb-2">QR Görünümü</a>
                      <img src="<?= htmlspecialchars($qr) ?>" alt="QR Kod" style="width:120px;height:120px;border:1px solid #ddd;border-radius:6px;">
                      <div class="mt-2">
                        <a href="table_qr.php?hash=<?= urlencode($hash) ?>&theme=<?= $themeMode ?>" 
                           target="_blank" class="btn btn-sm btn-outline-dark w-100">
                          Yazdır
                        </a>
                      </div>
                    </td>
                    <td class="text-center">
                      <form method="post" style="display:inline-block;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="table_id" value="<?= (int)$t['TableID'] ?>">
                        <button class="btn btn-sm <?= $t['IsActive'] ? 'btn-warning' : 'btn-success' ?>">
                          <?= $t['IsActive'] ? 'Pasif Yap' : 'Aktif Yap' ?>
                        </button>
                      </form>
                      <form method="post" onsubmit="return confirm('Bu masayı silmek istiyor musunuz?');" style="display:inline-block;">
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

<?php include __DIR__ . '/../includes/bo_footer.php'; ?>
