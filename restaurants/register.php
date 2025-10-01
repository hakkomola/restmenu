<?php
// restaurants/register.php
session_start();
include __DIR__ . '/../includes/mainnavbar.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Üye Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
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
