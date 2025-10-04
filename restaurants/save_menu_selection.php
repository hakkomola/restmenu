<?php
session_start();
require_once __DIR__ . '/../db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['restaurant_id'])) {
    die("Giriş yapılmamış!");
}
$newRestaurantId = $_SESSION['restaurant_id'];

if (!isset($_POST['selections'])) {
    die("Veri yok");
}
$selections = json_decode($_POST['selections'], true);

if(!$selections) die("Geçersiz veri");

$catMap = [];
$subMap = [];

try {
    // Kategoriler
    foreach($selections as $sel){
        if($sel['type'] === 'category'){
            $stmt = $pdo->prepare("SELECT CategoryID FROM MenuCategories WHERE RestaurantID=? AND CategoryName=?");
            $stmt->execute([$newRestaurantId, $sel['text']]);
            $exist = $stmt->fetchColumn();

            if($exist){
                $catMap[$sel['id']] = $exist;
            } else {
                $stmt = $pdo->prepare("INSERT INTO MenuCategories (RestaurantID, CategoryName) VALUES (?, ?)");
                $stmt->execute([$newRestaurantId, $sel['text']]);
                $catMap[$sel['id']] = $pdo->lastInsertId();
            }
        }
    }

    // Alt Kategoriler
    foreach($selections as $sel){
        if($sel['type'] === 'subcategory'){
            $oldCatId = $sel['parentCategoryId'];
            if(!isset($catMap[$oldCatId])) continue;
            $newCatId = $catMap[$oldCatId];

            $stmt = $pdo->prepare("SELECT SubCategoryID FROM SubCategories WHERE RestaurantID=? AND CategoryID=? AND SubCategoryName=?");
            $stmt->execute([$newRestaurantId, $newCatId, $sel['text']]);
            $exist = $stmt->fetchColumn();

            if($exist){
                $subMap[$sel['id']] = $exist;
            } else {
                $stmt = $pdo->prepare("INSERT INTO SubCategories (RestaurantID, CategoryID, SubCategoryName) VALUES (?, ?, ?)");
                $stmt->execute([$newRestaurantId, $newCatId, $sel['text']]);
                $subMap[$sel['id']] = $pdo->lastInsertId();
            }
        }
    }

    // Itemler
    foreach($selections as $sel){
        if($sel['type'] === 'item'){
            $oldCatId = $sel['parentCategoryId'];
            $oldSubId = $sel['parentSubId'];
            if(!isset($catMap[$oldCatId]) || !isset($subMap[$oldSubId])) continue;

            $newCatId = $catMap[$oldCatId];
            $newSubId = $subMap[$oldSubId];

            $stmt = $pdo->prepare("SELECT MenuItemID FROM MenuItems WHERE RestaurantID=? AND SubCategoryID=? AND MenuName=?");
            $stmt->execute([$newRestaurantId, $newSubId, $sel['text']]);
            $exist = $stmt->fetchColumn();

            if(!$exist){
                $stmt = $pdo->prepare("INSERT INTO MenuItems (RestaurantID, SubCategoryID, MenuName) VALUES (?, ?, ?)");
                $stmt->execute([$newRestaurantId, $newSubId, $sel['text']]);
            }
        }
    }

    echo "OK";

} catch(PDOException $e){
    echo "Hata: " . $e->getMessage();
}
