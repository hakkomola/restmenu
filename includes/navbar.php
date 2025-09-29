<?php
// includes/navbar.php

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

$restaurantName = $_SESSION['restaurant_name'] ?? 'Restoran';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="../restaurants/dashboard.php">Ana Sayfa</a>
        <div class="d-flex">
            <span class="navbar-text me-3">Hoşgeldin, <?= htmlspecialchars($restaurantName) ?></span>
            <a href="logout.php" class="btn btn-light">Çıkış Yap</a> 
        </div>
    </div>
</nav>
