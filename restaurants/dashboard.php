<?php
// restaurants/dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Dashboard';
$restaurantId  = $_SESSION['restaurant_id'];
$currentBranch = $_SESSION['current_branch'] ?? null;
$isAdmin       = !empty($_SESSION['is_admin']);

// Şube filtresi (admin tüm şubeler modunda branch null olabilir)
$whereBranch = $currentBranch ? "AND BranchID = " . (int)$currentBranch : "";

// hızlı sayımlar
$counts = [
  'categories' => (int)$pdo->query("SELECT COUNT(*) FROM MenuCategories WHERE RestaurantID = {$restaurantId} {$whereBranch}")->fetchColumn(),
  'subcats'    => (int)$pdo->query("SELECT COUNT(*) FROM SubCategories WHERE RestaurantID = {$restaurantId} {$whereBranch}")->fetchColumn(),
  'items'      => (int)$pdo->query("SELECT COUNT(*) FROM MenuItems WHERE RestaurantID = {$restaurantId} {$whereBranch}")->fetchColumn(),
  'tables'     => (int)$pdo->query("SELECT COUNT(*) FROM RestaurantTables WHERE RestaurantID = {$restaurantId} {$whereBranch}")->fetchColumn(),
  'orders'     => (int)$pdo->query("SELECT COUNT(*) FROM Orders WHERE RestaurantID = {$restaurantId} {$whereBranch}")->fetchColumn(),
];

include __DIR__ . '/../includes/bo_header.php';


?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm text-center p-3">
      <div class="text-secondary small">Toplam Sipariş</div>
      <div class="fs-4 fw-semibold"><?= $counts['orders'] ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm text-center p-3">
      <div class="text-secondary small">Masalar</div>
      <div class="fs-4 fw-semibold"><?= $counts['tables'] ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm text-center p-3">
      <div class="text-secondary small">Menü Öğeleri</div>
      <div class="fs-4 fw-semibold"><?= $counts['items'] ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card border-0 shadow-sm text-center p-3">
      <div class="text-secondary small">Kategoriler</div>
      <div class="fs-4 fw-semibold"><?= $counts['categories'] ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">
        Son 10 Sipariş
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Masa</th>
              <th>Tarih</th>
              <th>Tutar</th>
              <th>Durum</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $query = "
            SELECT o.OrderID, t.Name AS TableName, o.CreatedAt, o.TotalPrice, s.Code AS Status
            FROM Orders o
            LEFT JOIN RestaurantTables t ON o.TableID = t.TableID
            LEFT JOIN OrderStatuses s ON o.StatusID = s.StatusID
            WHERE o.RestaurantID = ? " . ($currentBranch ? "AND o.TableID IN (SELECT TableID FROM RestaurantTables WHERE BranchID = $currentBranch)" : "") . "
            ORDER BY o.CreatedAt DESC
            LIMIT 10
          ";
          $stmt = $pdo->prepare($query);
          $stmt->execute([$restaurantId]);
          $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if ($orders) {
            foreach ($orders as $o) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars($o['OrderID']) . '</td>';
              echo '<td>' . htmlspecialchars($o['TableName'] ?? '-') . '</td>';
              echo '<td>' . date('d.m.Y H:i', strtotime($o['CreatedAt'])) . '</td>';
              echo '<td>' . number_format($o['TotalPrice'], 2) . ' ₺</td>';
              echo '<td><span class="badge bg-secondary">' . htmlspecialchars($o['Status'] ?? '-') . '</span></td>';
              echo '</tr>';
            }
          } else {
            echo '<tr><td colspan="5" class="text-center text-muted py-3">Henüz sipariş yok.</td></tr>';
          }
          ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

    <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">
        Aktif Masalar
      </div>
      <div class="card-body">
        <?php
        $stmt = $pdo->prepare("
          SELECT Name, Code
          FROM RestaurantTables
          WHERE RestaurantID = ? AND IsActive = 1 " . ($currentBranch ? "AND BranchID = $currentBranch" : "") . "
          ORDER BY Name
        ");
        $stmt->execute([$restaurantId]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($tables) {
          echo '<div class="d-flex flex-wrap gap-2">';
          foreach ($tables as $t) {
            echo '<span class="badge rounded-pill bg-success px-3 py-2">' . htmlspecialchars($t['Name']) . '</span>';
          }
          echo '</div>';
        } else {
          echo '<p class="text-muted small mb-0">Bu şubede aktif masa yok.</p>';
        }
        ?>
      </div>
    </div>
  </div>
</div>


<!-- Buraya mini widget'lar ekleyeceğiz (son siparişler, aktif masalar vb.) -->
<div class="row g-3">
  <?php if (can('orders')): ?>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white">
        <strong>Son Siparişler</strong>
      </div>
      <div class="card-body">
        <div class="text-muted small">Bu alanı bir sonraki adımda dolduracağız.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (can('tables')): ?>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white">
        <strong>Aktif Masalar</strong>
      </div>
      <div class="card-body">
        <div class="text-muted small">Bu alanı bir sonraki adımda dolduracağız.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
include __DIR__ . '/../includes/bo_footer.php';
