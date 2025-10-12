<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$hash = $_POST['hash'] ?? '';
$itemId = (int)($_POST['itemId'] ?? 0);
$optionId = (int)($_POST['optionId'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));

if (!$hash || !$itemId || !$optionId) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik veri.']);
    exit;
}

// opsiyon detaylarını DB'den çek
$stmt = $pdo->prepare("
  SELECT i.MenuName, o.Price, o.OptionName
  FROM MenuItemOptions o
  JOIN MenuItems i ON i.MenuItemID=o.MenuItemID
  WHERE o.OptionID=?
");
$stmt->execute([$optionId]);
$opt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$opt) {
    echo json_encode(['status'=>'error','message'=>'Ürün bulunamadı']);
    exit;
}

$price = (float)$opt['Price'];
$itemName = $opt['MenuName'].' - '.$opt['OptionName'];

$_SESSION['cart'][$hash][$optionId] = [
    'id' => $optionId,
    'name' => $itemName,
    'price' => $price,
    'qty' => ($_SESSION['cart'][$hash][$optionId]['qty'] ?? 0) + $qty
];

// toplamı hesapla
$total = 0;
foreach ($_SESSION['cart'][$hash] as $it) {
    $total += $it['price'] * $it['qty'];
}

echo json_encode(['status'=>'ok','total'=>$total]);
