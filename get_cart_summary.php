<?php
session_start();
header('Content-Type: application/json');

$hash = $_GET['hash'] ?? '';
if (!$hash || empty($_SESSION['cart'][$hash])) {
    echo json_encode(['status'=>'ok','count'=>0,'total'=>'0.00','items'=>[]]);
    exit;
}

$cart = $_SESSION['cart'][$hash];
$total = 0;
$count = 0;
$items = [];

foreach ($cart as $key => $item) {
    $price = isset($item['price']) ? (float)$item['price'] : 0;
    $qty   = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    $name  = $item['name'] ?? '';
    $opt   = $item['option_name'] ?? '';

    $subtotal = $price * $qty;
    $total += $subtotal;
    $count += $qty;

    $items[] = [
        'key'         => $key,
        'name'        => $name,
        'option_name' => $opt,
        'price'       => number_format($price, 2),
        'qty'         => $qty
    ];
}

echo json_encode([
    'status' => 'ok',
    'count'  => $count,
    'total'  => number_format($total, 2),
    'items'  => $items
]);
