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

// 🔍 Masa & restoran çöz
$stmt = $pdo->prepare("SELECT RestaurantID, Code FROM RestaurantTables");
$stmt->execute();
$table = null;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $variants = [
        substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING'), 0, 24),
        md5($r['RestaurantID'].'-'.$r['Code']),
        md5($r['RestaurantID'].$r['Code']),
        md5($r['Code']),
        $r['Code']
    ];
    foreach ($variants as $v) {
        if (hash_equals($v, $hash)) {
            $table = $r;
            break 2;
        }
    }
}

if (!$table) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz masa.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 🧾 Orders tablosuna ekle
    $stmt = $pdo->prepare("
        INSERT INTO Orders (RestaurantID, OrderCode, Note, CreatedAt, StatusId)
        VALUES (:rid, :code, :note, NOW(), 1)
    ");
    $stmt->execute([
        ':rid'  => $table['RestaurantID'],
        ':code' => $table['Code'],
        ':note' => $note
    ]);

    $orderId = $pdo->lastInsertId();

    // 🧺 OrderItems tablosuna ekle
    $stmtItem = $pdo->prepare("
        INSERT INTO OrderItems (OrderID, OptionID, Quantity, BasePrice)
        VALUES (:oid, :opt, :qty, :price)
    ");

    foreach ($cart as $item) {
        // quantity veya price eksikse güvenli şekilde tamamla
        $qty = isset($item['quantity']) && (int)$item['quantity'] > 0 ? (int)$item['quantity'] : 1;
        $price = isset($item['price']) ? (float)$item['price'] : 0.00;

        $stmtItem->execute([
            ':oid'   => $orderId,
            ':opt'   => $item['option_id'] ?? null,
            ':qty'   => $qty,
            ':price' => $price
        ]);
    }

    $pdo->commit();

    // 🧹 Sepeti temizle
    unset($_SESSION['cart'][$hash]);

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Kayıt hatası: '.$e->getMessage()]);
}
