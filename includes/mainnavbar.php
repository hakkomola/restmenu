<?php
// includes/mainnavbar.php
?>
<head>
    <link rel="icon" type="image/png" href="../images/menufav.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-semibold text-primary" href="../index.php">
            <i class="bi bi-list-ul me-1"></i> Vov Menu
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
            aria-controls="mainNavbar" aria-expanded="false" aria-label="Menüyü Aç">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item me-2">
                    <a href="../restaurants/login.php" class="btn btn-outline-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Giriş Yap
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../restaurants/register.php" class="btn btn-primary text-white">
                        <i class="bi bi-person-plus me-1"></i> Üye Ol
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
