<?php
session_start();
require_once __DIR__ . '/db.php';

$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang'] ?? 'tr';
$hash  = $_GET['hash'] ?? null;
if (!$hash) die('Geçersiz bağlantı!');

$cart = $_SESSION['cart'][$hash] ?? [];
$total = 0;
foreach ($cart as $key => $item) {
    $qty = (int)($item['qty'] ?? 0);
    $price = (float)($item['price'] ?? 0);
    $total += $price * $qty;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sepetim</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Ibarra+Real+Nova:wght@400;600;700&display=swap" rel="stylesheet">
<link href="assets/menu.css" rel="stylesheet">
</head>
<body class="menu-body" data-theme="<?= htmlspecialchars($theme) ?>">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">🛒 Sepetiniz</h4>
    <a href="menu_order.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>" class="btn btn-outline-secondary btn-sm">Menüye Dön</a>
  </div>

  <?php if (empty($cart)): ?>
    <div class="alert alert-info">Sepetiniz boş.</div>
  <?php else: ?>
    <form id="orderForm" action="submit_order.php" method="POST">
      <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">

      <?php foreach ($cart as $optionId => $item): ?>
        <?php
        $stmtOpt = $pdo->prepare("SELECT MenuItemID FROM MenuItemOptions WHERE OptionID = ? LIMIT 1");
        $stmtOpt->execute([$optionId]);
        $menuItemId = $stmtOpt->fetchColumn();

        $thumb = 'assets/no-image.png';
        if ($menuItemId) {
            $imgStmt = $pdo->prepare("SELECT ImageURL FROM MenuImages WHERE MenuItemID = ? LIMIT 1");
            $imgStmt->execute([$menuItemId]);
            $img = $imgStmt->fetchColumn();
            if ($img) $thumb = ltrim($img, '/');
        }
        ?>
        <div class="cart-card mb-3 p-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <img src="<?= htmlspecialchars($thumb) ?>" alt="Ürün" class="cart-thumb" style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($item['name'] ?? ('Ürün #' . $menuItemId)) ?></div>
              <small class="text-muted"><?= number_format((float)$item['price'], 2) ?> ₺</small>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm update-cart"
                    data-id="<?= $optionId ?>" data-item-id="<?= $menuItemId ?>" data-dir="-1">−</button>
            <span class="fw-semibold qty"><?= (int)($item['qty'] ?? 0) ?></span>
            <button type="button" class="btn btn-outline-secondary btn-sm update-cart"
                    data-id="<?= $optionId ?>" data-item-id="<?= $menuItemId ?>" data-dir="1">+</button>
            <button type="button" class="btn btn-outline-danger btn-sm remove-cart"
                    data-id="<?= $optionId ?>" data-item-id="<?= $menuItemId ?>">×</button>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="mb-3">
        <label for="note" class="form-label fw-semibold">Sipariş Notu (isteğe bağlı):</label>
        <textarea name="note" id="note" class="form-control" rows="3" placeholder="Örneğin: içecekler soğuk olsun..."></textarea>
      </div>

      <div class="cart-footer d-flex justify-content-between align-items-center">
        <strong>Toplam: ₺<span id="cartTotal"><?= number_format($total, 2, ',', '.') ?></span></strong>
        <button type="submit" class="btn btn-success px-4">Siparişi Gönder</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
async function addMany(optionId, itemId, count) {
  const form = new FormData();
  form.append('hash', '<?= $hash ?>');
  form.append('itemId', itemId);
  form.append('optionId', optionId);
  form.append('qty', count);
  const res = await fetch('add_to_cart.php', { method: 'POST', body: form });
  return res.json();
}

async function removeKey(optionId) {
  const form = new FormData();
  form.append('hash', '<?= $hash ?>');
  form.append('key', optionId);
  const res = await fetch('remove_from_cart.php', { method: 'POST', body: form });
  return res.json();
}

async function updateCart(optionId, dir, itemId, currentQty) {
  try {
    if (dir > 0) {
      const data = await addMany(optionId, itemId, 1);
      if (data?.status === 'ok') return location.reload();
      return alert(data.message || 'İşlem başarısız');
    }

    if (currentQty <= 1) {
      const data = await removeKey(optionId);
      if (data?.status === 'ok') return location.reload();
      return alert(data.message || 'Silme başarısız');
    }

    const del = await removeKey(optionId);
    if (del?.status !== 'ok') return alert(del.message || 'Silme başarısız');

    const addBack = await addMany(optionId, itemId, currentQty - 1);
    if (addBack?.status === 'ok') return location.reload();
    alert(addBack.message || 'İşlem başarısız');
  } catch (e) {
    console.error(e);
    alert('Bağlantı hatası');
  }
}

document.addEventListener('click', (e) => {
  const up = e.target.closest('.update-cart');
  const rm = e.target.closest('.remove-cart');

  if (up) {
    const card = up.closest('.cart-card');
    const qtyEl = card?.querySelector('.qty');
    const currentQty = parseInt(qtyEl?.textContent || '0', 10) || 0;
    const dir = parseInt(up.dataset.dir, 10);
    updateCart(up.dataset.id, dir, up.dataset.itemId, currentQty);
  }

  if (rm) {
    const form = new FormData();
    form.append('hash', '<?= $hash ?>');
    form.append('key', rm.dataset.id);
    fetch('remove_from_cart.php', { method: 'POST', body: form })
      .then(r => r.json())
      .then(data => { if (data?.status === 'ok') location.reload(); else alert(data.message || 'Silme başarısız'); })
      .catch(err => { console.error(err); alert('Bağlantı hatası'); });
  }
});

// 🟢 Sipariş Gönderme (popup + yönlendirme)
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('orderForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    try {
      const res = await fetch('submit_order.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.status === 'ok') showOrderSuccess(data.order_id, data.total);
      else alert(data.message || 'Sipariş gönderilemedi!');
    } catch (err) {
      console.error(err);
      alert('Bağlantı hatası.');
    }
  });
});

function showOrderSuccess(orderId, total) {
  const popup = document.createElement('div');
  popup.innerHTML = `
    <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-75" style="z-index:9999;">
      <div class="bg-white rounded-4 shadow p-4 text-center" style="max-width:320px;">
        <h5 class="mb-3 fw-semibold text-success">✅ Siparişiniz alındı!</h5>
        <p class="mb-1 text-muted small">Sipariş Numaranız: <strong>#${orderId}</strong></p>
        <p class="mb-3 text-muted small">Toplam: ₺${parseFloat(total).toLocaleString('tr-TR', {minimumFractionDigits:2})}</p>
        <p class="text-secondary small mb-3">Teşekkür ederiz, siparişiniz hazırlanıyor.</p>
        <button id="okBtn" class="btn btn-success px-4">Tamam</button>
      </div>
    </div>
  `;
  document.body.appendChild(popup);

  // ✅ Tamam butonuna basılınca menüye dön
  const okBtn = popup.querySelector('#okBtn');
  okBtn.addEventListener('click', () => {
    popup.remove();
    window.location.href = `orders.php?hash=<?= urlencode($hash) ?>&theme=<?= urlencode($theme) ?>&lang=<?= urlencode($lang) ?>`;

  });
}

</script>
</body>
</html>
