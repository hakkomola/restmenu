<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branch_id'])) {
    $branchId = (int)$_POST['branch_id'];

    require_once __DIR__ . '/../db.php';
    $restaurantId = $_SESSION['restaurant_id'];

    // Şube kontrolü
    $stmt = $pdo->prepare("SELECT BranchID FROM RestaurantBranches WHERE BranchID=? AND RestaurantID=?");
    $stmt->execute([$branchId, $restaurantId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($branch) {
        $_SESSION['current_branch'] = $branchId;
    } else {
        unset($_SESSION['current_branch']);
    }

    // 🔹 Geldiği sayfaya geri yönlendir
    $referer = $_SERVER['HTTP_REFERER'] ?? '/restaurants/dashboard.php';

    // Güvenlik kontrolü (domain dışı URL'leri engelle)
    if (strpos($referer, $_SERVER['HTTP_HOST']) === false) {
        $referer = '/restaurants/dashboard.php';
    }

    header("Location: $referer");
    exit;
}

header('Location: /restaurants/dashboard.php');
exit;
