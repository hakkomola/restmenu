<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// 🧩 Parametreler
$itemId   = isset($_POST['item_id'])   ? (int)$_POST['item_id']   : 0;
$optionId = isset($_POST['option_id']) ? (int)$_POST['option_id'] : 0;
$qty      = isset($_POST['quantity'])  ? max(1, (int)$_POST['quantity']) : 1;
$hash     = $_POST['hash'] ?? '';

if (!$itemId || !$optionId || !$hash) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik parametre.']);
    exit;
}

/* ==== HASH DOĞRULAMA ==== */
function resolve_table_by_hash(PDO $pdo, string $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, Code, IsActive FROM RestaurantTables");
    foreach ($stmt as $r) {
        $variants = [
            substr(hash('sha256', $r['RestaurantID'].'|'.$r['Code'].'|CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING'), 0, 24),
            md5($r['RestaurantID'].'-'.$r['Code']),
            md5($r['RestaurantID'].$r['Code']),
            md5($r['Code']),
            $r['Code']
        ];
        foreach ($variants as $v) {
            if (hash_equals($v, $hash)) {
                return $r;
            }
        }
    }
    return null;
}

$table = resolve_table_by_hash($pdo, $hash);
if (!$table || !$table['IsActive']) {
    echo json_encode(['status' => 'error', 'message' => 'Masa pasif veya geçersiz.']);
    exit;
}

/* ==== ÜRÜN & OPSİYON GETİR ==== */
$stmt = $pdo->prepare("
    SELECT 
        mi.MenuItemID, 
        COALESCE(mt.Name, mi.MenuName) AS ItemName, 
        moo.OptionID,
        COALESCE(mot.Name, moo.OptionName) AS OptionName, 
        moo.Price
    FROM MenuItemOptions moo
    JOIN MenuItems mi ON mi.MenuItemID = moo.MenuItemID
    LEFT JOIN MenuItemTranslations mt ON mt.MenuItemID = mi.MenuItemID
    LEFT JOIN MenuItemOptionTranslations mot ON mot.OptionID = moo.OptionID
    WHERE moo.OptionID = ?
    LIMIT 1
");
$stmt->execute([$optionId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode(['status' => 'error', 'message' => 'Ürün bulunamadı.']);
    exit;
}

/* ==== SESSION SEPETİ ==== */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['cart'][$hash])) $_SESSION['cart'][$hash] = [];

$key = $item['MenuItemID'] . '-' . $item['OptionID'];

if (isset($_SESSION['cart'][$hash][$key])) {
    // aynı ürün ve opsiyon varsa miktar artır
    $_SESSION['cart'][$hash][$key]['quantity'] += $qty;
} else {
    $_SESSION['cart'][$hash][$key] = [
        'item_id'     => $item['MenuItemID'],
        'name'        => $item['ItemName'],
        'option_id'   => $item['OptionID'],
        'option_name' => $item['OptionName'],
        'price'       => (float)$item['Price'],
        'quantity'    => $qty
    ];
}

/* ==== BAŞARILI ==== */
echo json_encode(['status' => 'ok']);
