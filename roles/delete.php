<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: list.php');
    exit;
}

$restaurantId = $_SESSION['restaurant_id'];
$isAdmin = !empty($_SESSION['is_admin']);

// doğrulama
$stmt = $pdo->prepare("SELECT * FROM RestaurantRoles WHERE RoleID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$role) { die('Rol bulunamadı veya erişim izniniz yok.'); }

// silme
$stmt = $pdo->prepare("DELETE FROM RestaurantRoles WHERE RoleID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);


header('Location: list.php');
exit;
