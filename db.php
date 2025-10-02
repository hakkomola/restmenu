<?php
// prod db.php - Veritabanı bağlantısı


$host = 'gator3302.hostgator.com:3306';
$db   = 'modifero_restmenu';      //modifero_restmenu Senin verdiğin veritabanı adı 'restmenu'
$user = 'modifero_restmenu';          // modifero_restmenu MySQL kullanıcı adı 'root'
$pass = 'Pg9AI-I]@hZQ';    // 'Pg9AI-I]@hZQ' MySQL şifre (XAMPP default boş) '1q2w3e4r..'
$charset = 'utf8';



/*
$host = '127.0.0.1';
$db   = 'restmenu';      //modifero_restmenu Senin verdiğin veritabanı adı 'restmenu'
$user = 'root';          // modifero_restmenu MySQL kullanıcı adı 'root'
$pass = '1q2w3e4r..';    // 'Pg9AI-I]@hZQ' MySQL şifre (XAMPP default boş) '1q2w3e4r..'
$charset = 'utf8mb4';
*/

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
