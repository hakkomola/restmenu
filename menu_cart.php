<?php
session_start();
require_once __DIR__ . '/db.php';

$hash  = $_GET['hash']  ?? null;
$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang']  ?? 'tr';

if (!$hash) die('Geçersiz bağlantı.');

$cartKey = 'cart_' . $hash;
$cart = $_SESSION[$cartKey] ?? [];

// UI metinleri
$tx = [
  'tr' => [
    'title' => 'Sepetim',
    'empty' => 'Sepetiniz boş.',
    'item' => 'Ürün',
    'option' => 'Seçenek',
    'qty' => 'Adet',
    'price' => 'Fiyat',
    'total' => 'Toplam',
    'remove' => 'Sil',
    'back' => 'Menüye Dön',
    'submit' => 'Siparişi Gönder'
  ],
  'en' => [
    'title' => 'My Cart',
    'empty' => 'Your cart is empty.',
    'item' => 'Item',
    'option' => 'Option',
    'qty' => 'Qty',
    'price' => 'Price',
    'total' => 'Total',
    'remove' => 'Remove',
    'back' => 'Back to Menu',
    'submit' => 'Submit Order'
  ]
][strtolower($lang)] ?? $tx['tr'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tx['title']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Ibarra+Real+Nova:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
  font-family: "Ibarra Real Nova", sans-serif;
  <?= $theme === 'dark'
      ? 'background-color:#121212;color:#f1f1f1;'
      : 'background-color:#f8f9fa;color:#222;'
  ?>
}
.table th, .table td { vertical-align: middle; }
.card { border:none; border-radius:12px; padding:20px;
  <?= $theme === 'dark' ? 'background:#1e1e1e;color:#eee;' : 'background:#fff;color:#222;' ?>
  box-shadow:0 2px 8px <?= $theme === 'dark' ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.08)' ?>;
}
.btn-theme {
  <?= $theme === 'dark' 
    ? 'background:#ff9800;color:#000;border-color:#ff9800;' 
    : 'background:#007bff;color:#fff;border-color:#007bff;' ?>
}
.btn-theme:hover {
  <?= $theme === 'dark'
    ? 'background:#e68a00;color:#000;' 
    : 'background:#0069d9;' ?>
}
</style>
</head>
<body class="py-4">

<div class="container">
  <h1 class="text-center mb-4"><?= htmlspecialchars($tx['title']) ?></h1>

  <div class="card">
    <?php if (empty($cart)): ?>
      <p class="text-center my-4"><?= htmlspecialchars($tx['empty']) ?></p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th><?= htmlspecialchars($tx['item']) ?></th>
              <th><?= htmlspecialchars($tx['option']) ?></th>
              <th><?= htmlspecialchars($tx['qty']) ?></th>
              <th><?= htmlspecialchars($tx['price']) ?></th>
              <th><?= htmlspecialchars($tx['total']) ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php 
          $grandTotal = 0;
          foreach ($cart as $i => $row): 
            $grandTotal += $row['total'];
          ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['option_name']) ?></td>
              <td><?= (int)$row['qty'] ?></td>
              <td><?= number_format($row['price'], 2) ?> ₺</td>
              <td><?= number_format($row['total'], 2) ?> ₺</td>
              <td>
                <form method="post" action="remove_from_cart.php" style="display:inline;">
                  <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">
                  <input type="hidden" name="index" value="<?= (int)$i ?>">
                  <button class="btn btn-sm btn-danger"><?= htmlspecialchars($tx['remove']) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <hr>
      <h4 class="text-end"><?= htmlspecialchars($tx['total']) ?>: <strong><?= number_format($grandTotal, 2) ?> ₺</strong></h4>
      <div class="d-flex justify-content-between mt-4">
        <a href="menu_order.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>" class="btn btn-outline-secondary">
          ← <?= htmlspecialchars($tx['back']) ?>
        </a>
        <form method="post" action="submit_order.php">
          <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">
          <button type="submit" class="btn btn-theme"><?= htmlspecialchars($tx['submit']) ?> →</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
