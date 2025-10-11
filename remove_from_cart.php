<?php
session_start();
header('Content-Type: application/json');

$hash = $_POST['hash'] ?? '';
$key  = $_POST['key']  ?? '';

if (!$hash || !$key) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik parametre']);
    exit;
}

if (isset($_SESSION['cart'][$hash][$key])) {
    unset($_SESSION['cart'][$hash][$key]);

    // eğer sepet tamamen boşaldıysa hash'i de kaldır
    if (empty($_SESSION['cart'][$hash])) {
        unset($_SESSION['cart'][$hash]);
    }

    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Ürün bulunamadı']);
}
