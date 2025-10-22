<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('users')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$userId = (int)($_GET['id'] ?? 0);

if ($userId > 0) {
    // Kullanıcı gerçekten bu restorana mı ait, kontrol et
    $stmt = $pdo->prepare("SELECT UserID FROM RestaurantUsers WHERE UserID=? AND RestaurantID=?");
    $stmt->execute([$userId, $restaurantId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        // 🔹 İlişkili kayıtları sil
        $pdo->prepare("DELETE FROM RestaurantBranchUsers WHERE UserID=?")->execute([$userId]);
        $pdo->prepare("DELETE FROM RestaurantUserRoles WHERE UserID=?")->execute([$userId]);

        // 🔹 Ana kullanıcıyı sil
        $pdo->prepare("DELETE FROM RestaurantUsers WHERE UserID=? AND RestaurantID=?")->execute([$userId, $restaurantId]);
    }
}

// 🔁 Listeye dön
header('Location: list.php');
exit;
