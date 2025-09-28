<?php
// db.php - Veritabanı bağlantısı
$host = '127.0.0.1';
$db   = 'restmenu';      // Senin verdiğin veritabanı adı
$user = 'root';          // MySQL kullanıcı adı
$pass = '1q2w3e4r..';              // MySQL şifre (XAMPP default boş)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hata mesajlarını göster
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch işlemleri array şeklinde
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}
