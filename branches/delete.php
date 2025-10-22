<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!can('branches')) die('Erişim yetkiniz yok.');

$restaurantId = $_SESSION['restaurant_id'];
$userId = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

// Şube doğrulama
$stmt = $pdo->prepare("SELECT * FROM RestaurantBranches WHERE BranchID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    die('Şube bulunamadı veya erişim yetkiniz yok.');
}

// 🔹 1. Şubeyi sil
$stmt = $pdo->prepare("DELETE FROM RestaurantBranches WHERE BranchID = ? AND RestaurantID = ?");
$stmt->execute([$id, $restaurantId]);

// 🔹 2. O şube ilişkisini de (kullanıcı-şube tablosundan) temizle
$stmt2 = $pdo->prepare("DELETE FROM RestaurantBranchUsers WHERE BranchID = ?");
$stmt2->execute([$id]);

// 🔹 3. Session’daki şube listesini güncelle
$stmt3 = $pdo->prepare("
    SELECT b.BranchID, b.BranchName
    FROM RestaurantBranchUsers bu
    JOIN RestaurantBranches b ON b.BranchID = bu.BranchID
    WHERE bu.UserID = ?
    ORDER BY b.BranchName
");
$stmt3->execute([$userId]);
$_SESSION['branches'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// 🔹 4. Listeye dön
header('Location: list.php');
exit;
