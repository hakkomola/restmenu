<?php
// restaurants/register.php
session_start();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Üye Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>

/* NAVBAR */
.navbar {
  box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.navbar-brand {
  font-weight: 700;
  color: #0d6efd !important;
}


</style>
</head>
<body>
    
<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
  <i class="bi bi-qr-code me-2 text-primary fs-3"></i>
  <span class="fw-bold" style="font-size:1.5rem;">Vov<span class="text-primary">Menu</span></span>
</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">

        <li class="nav-item"><a class="btn btn-outline-primary btn-sm ms-3" href="restaurants/login.php">Giriş</a></li>
        <li class="nav-item"><a class="btn btn-primary btn-sm ms-2" href="restaurants/register.php">Üye Ol</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container mt-5" style="max-width: 600px;">
    <h2 class="mb-4 text-center">Restoran Üyelik Başvurusu</h2>

    <div class="alert alert-info text-center p-4">
        Restoran hesabı oluşturmak için lütfen
        <strong><a href="mailto:info@vovmenu.com">info@vovmenu.com</a></strong>
        adresine e-posta gönderiniz.
    </div>

    <div class="text-center mt-4">
        <a href="mailto:info@vovmenu.com" class="btn btn-primary btn-lg">
            📧 Mail Gönder
        </a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
