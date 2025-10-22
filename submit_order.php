<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$hash = $_POST['hash'] ?? '';
$note = trim($_POST['note'] ?? '');

if (!$hash || empty($_SESSION['cart'][$hash])) {
    echo json_encode(['status' => 'error', 'message' => 'Boş sepet veya geçersiz istek.']);
    exit;
}

$cart = $_SESSION['cart'][$hash];

/* 🔹 Masa & restoran çözümü (branch dahil hash) */
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

$stmt = $pdo->query("SELECT TableID, RestaurantID, BranchID, Code, Name, IsActive FROM RestaurantTables");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$table = null;
foreach ($rows as $r) {
    $branchId = (int)($r['BranchID'] ?? 0);
    $calc = substr(hash('sha256', $r['RestaurantID'] . '|' . $branchId . '|' . $r['Code'] . '|' . RESTMENU_HASH_PEPPER), 0, 32);
    if (hash_equals($calc, $hash)) {
        $table = $r;
        break;
    }
}

if (!$table) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz masa.']);
    exit;
}
if (!$table['IsActive']) {
    echo json_encode(['status' => 'error', 'message' => 'Bu masa pasif durumda.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 🧾 Orders tablosuna ekle (TableID kullanıyoruz)
    $stmt = $pdo->prepare("
        INSERT INTO Orders (RestaurantID, TableID, OrderCode, Note, CreatedAt, StatusID, TotalPrice)
        VALUES (:rid, :tid, :code, :note, NOW(), 1, 0)
    ");
    $stmt->execute([
        ':rid'  => $table['RestaurantID'],
        ':tid'  => $table['TableID'],
        ':code' => $table['Code'],
        ':note' => $note
    ]);

    $orderId = $pdo->lastInsertId();

    // 🧮 Toplam hesaplama
    $totalPrice = 0.0;

    // 🧺 OrderItems ekleme
    $stmtItem = $pdo->prepare("
        INSERT INTO OrderItems (OrderID, OptionID, Quantity, BasePrice, StatusID, TotalPrice)
        VALUES (:oid, :opt, :qty, :price, 1, :total)
    ");

    foreach ($cart as $optionId => $item) {
        $qty = max(1, (int)($item['qty'] ?? 0));
        $price = (float)($item['price'] ?? 0);
        $lineTotal = $qty * $price;

        $stmtItem->execute([
            ':oid'   => $orderId,
            ':opt'   => $optionId,
            ':qty'   => $qty,
            ':price' => $price,
            ':total' => $lineTotal
        ]);

        $totalPrice += $lineTotal;
    }

    // 💰 Orders toplam güncelle
    $stmtUpd = $pdo->prepare("UPDATE Orders SET TotalPrice = :total WHERE OrderID = :oid");
    $stmtUpd->execute([':total' => $totalPrice, ':oid' => $orderId]);

    $pdo->commit();

    // 🧹 Sepeti temizle
    unset($_SESSION['cart'][$hash]);

    echo json_encode([
        'status' => 'ok',
        'order_id' => $orderId,
        'total' => number_format($totalPrice, 2, '.', '')
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Kayıt hatası: ' . $e->getMessage()]);
}
