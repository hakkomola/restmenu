<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$userId = (int)($_GET['id'] ?? 0);

if ($userId) {
    $stmt = $pdo->prepare("DELETE FROM RestaurantUsers WHERE UserID=? AND RestaurantID=?");
    $stmt->execute([$userId, $restaurantId]);
}

header("Location: list.php");
exit;
