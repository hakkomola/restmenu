<?php
// includes/navbar.php

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: login.php');
    exit;
}

$restaurantName = $_SESSION['restaurant_name'] ?? 'Restoran';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container-fluid px-3 px-md-4">
    <a class="navbar-brand fw-semibold text-primary" href="../restaurants/dashboard.php">
        🍽️ VovMenu
    </a>

    <!-- Mobil menü butonu -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu"
      aria-controls="navbarMenu" aria-expanded="false" aria-label="Menüyü Aç">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Menü -->
    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="../restaurants/dashboard.php"><i class="bi bi-house me-1"></i>Ana Sayfa</a></li>
        <li class="nav-item"><a class="nav-link" href="../categories/list.php"><i class="bi bi-list-nested me-1"></i>Kategoriler</a></li>
        <li class="nav-item"><a class="nav-link" href="../subcategories/list.php"><i class="bi bi-diagram-3 me-1"></i>Alt Kategoriler</a></li>
        <li class="nav-item"><a class="nav-link" href="../items/list.php"><i class="bi bi-card-text me-1"></i>Menü Öğeleri</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/menu_tree.php"><i class="bi bi-lightning-charge me-1"></i>Kolay Menü</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/profile.php"><i class="bi bi-building me-1"></i>Restoran Bilgileri</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/tables.php"><i class="bi bi-grid-3x3-gap me-1"></i>Masalar</a></li>
        <li class="nav-item"><a class="nav-link" href="../restaurants/change_password.php"><i class="bi bi-lock me-1"></i>Şifre Değiştir</a></li>
        

      </ul>

      <!-- Sağ taraf: Hoşgeldin + Çıkış -->
      <div class="d-flex align-items-center">
        <span class="navbar-text me-3 text-dark small">
          <i class="bi bi-person-circle me-1"></i>
          <?= htmlspecialchars($restaurantName) ?>
        </span>
        <a href="../restaurants/logout.php" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-box-arrow-right me-1"></i> Çıkış
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- Bootstrap ICONS (sadece ikonlar için, JS değil!) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
