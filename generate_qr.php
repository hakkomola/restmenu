<?php
// generate_qr.php
require_once __DIR__ . '/phpqrcode.php';
require_once __DIR__ . '/db.php';

// === Aynı hash fonksiyonu (tables.php ve restaurant_info.php ile birebir aynı olmalı) ===
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

/**
 * Hash'ten Restaurant + Branch + Table bilgilerini çözen fonksiyon
 */
function find_restaurant_and_table($pdo, string $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, BranchID, Code, IsActive FROM RestaurantTables");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $rid  = (int)$r['RestaurantID'];
        $bid  = (int)($r['BranchID'] ?? 0);
        $calc = substr(hash('sha256', $rid . '|' . $bid . '|' . $r['Code'] . '|' . RESTMENU_HASH_PEPPER), 0, 32);
        if (hash_equals($calc, $hash)) {
            return $r;
        }
    }
    return null;
}

// --- Parametreler ---
$hash  = $_GET['cat'] ?? $_GET['hash'] ?? null;
$theme = $_GET['theme'] ?? 'light';
if (!$hash) die('Masa hash eksik!');

// --- Hash çözümü ---
$table = find_restaurant_and_table($pdo, $hash);
if (!$table) die('Geçersiz masa hash!');
if (!$table['IsActive']) die('Bu masa pasif durumda.');

// --- Menü linkini oluştur ---
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = str_replace('/restaurants','', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

// restaurant_info.php’ye yönlendiren güvenli QR
$menuLink = $scheme . '://' . $host . $base . '/restaurant_info.php?hash=' . urlencode($hash) . '&theme=' . urlencode($theme);

// --- QR kodu üret ---
header('Content-Type: image/png');
QRcode::png($menuLink, null, QR_ECLEVEL_L, 5);
