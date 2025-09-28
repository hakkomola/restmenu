<?php
// generate_qr.php
require_once __DIR__ . '/phpqrcode.php'; // PHP QR kütüphanesi tek dosya

// Hem 'cat' hem 'hash' parametresini kabul et
$hash = $_GET['cat'] ?? $_GET['hash'] ?? null;
if (!$hash) {
    die('Kategori hash eksik!');
}

// Menü linkini oluştur
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base = str_replace('/restaurants','', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')); // /restmenu

$menuLink = $scheme . '://' . $host . $base . '/menu.php?hash=' . $hash;

// QR kodu üret ve göster
header('Content-Type: image/png');
QRcode::png($menuLink, null, QR_ECLEVEL_L, 5);
