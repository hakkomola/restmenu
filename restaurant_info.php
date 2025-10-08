<?php
require_once __DIR__ . '/db.php';

$hash  = $_GET['hash']  ?? null;
$theme = $_GET['theme'] ?? 'light';
$lang  = $_GET['lang']  ?? null;

if (!$hash) die('Geçersiz bağlantı!');

// === Aynı hash fonksiyonları (tables.php ve table_qr.php ile birebir aynı olmalı) ===
if (!defined('RESTMENU_HASH_PEPPER')) {
    define('RESTMENU_HASH_PEPPER', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_STRING');
}

function find_restaurant_and_table($pdo, $hash) {
    $stmt = $pdo->query("SELECT RestaurantID, Code, Name, IsActive FROM RestaurantTables");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $generated = substr(hash('sha256', $r['RestaurantID'] . '|' . $r['Code'] . '|' . RESTMENU_HASH_PEPPER), 0, 24);
        if (hash_equals($generated, $hash)) {
            return $r; // Bu hash'e ait masa bulundu
        }
    }
    return null;
}

$table = find_restaurant_and_table($pdo, $hash);
if (!$table) die('Masa tanınamadı veya geçersiz bağlantı.');
if (!$table['IsActive']) die('Bu masa şu anda pasif durumda.');

// Restoran bilgilerini getir
$stmt = $pdo->prepare("SELECT * FROM Restaurants WHERE RestaurantID = ?");
$stmt->execute([$table['RestaurantID']]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) die('Restoran bulunamadı!');

$name      = $r['Name'] ?? '';
$nameHTML  = $r['NameHTML'] ?? '';
$addr      = $r['Address'] ?? '';
$phone     = $r['Phone'] ?? '';
$email     = $r['Email'] ?? '';
$map       = $r['MapUrl'] ?? '';
$main      = !empty($r['MainImage']) ? ltrim($r['MainImage'], '/') : 'uploads/default_cover.jpg';
if (!$lang) $lang = $r['DefaultLanguage'] ?: 'tr';

// --- Dil metinleri ---
$texts = [
  'tr' => [
    'address' => 'Adres',
    'phone' => 'Telefon',
    'email' => 'E-posta',
    'location' => '📍 Konumu Gör'
  ],
  'en' => [
    'address' => 'Address',
    'phone' => 'Phone',
    'email' => 'Email',
    'location' => '📍 View Location'
  ]
];
$t = $texts[$lang] ?? $texts['tr'];

// Restoranın desteklediği diller
$langStmt = $pdo->prepare("
    SELECT rl.LangCode, rl.IsDefault, l.LangName
    FROM RestaurantLanguages rl
    JOIN Languages l ON l.LangCode = rl.LangCode
    WHERE rl.RestaurantID = ?
    ORDER BY rl.IsDefault DESC, l.LangName ASC
");
$langStmt->execute([$table['RestaurantID']]);
$supportedLangs = $langStmt->fetchAll(PDO::FETCH_ASSOC);

function has_html($text) { return is_string($text) && preg_match('/<[^>]+>/', $text); }
function render_content($v, $p = '<em>Bilgi eklenmedi</em>') {
    if (!$v) return $p;
    $v = trim($v);
    $allowed = '<div><span><p><b><strong><i><em><u><br><a><h1><h2><h3>';
    return has_html($v) ? strip_tags($v, $allowed) : nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
}
function render_link($v, $t = 'phone') {
    if (!$v) return '<em>Bilgi eklenmedi</em>';
    $v = trim($v);
    if (has_html($v)) return strip_tags($v, '<a><b><strong><i><em><u><span><br>');
    if ($t === 'phone') {
        $h = preg_replace('/[^0-9+]/', '', $v);
        return '<a href="tel:' . htmlspecialchars($h) . '">' . htmlspecialchars($v) . '</a>';
    }
    if ($t === 'email') {
        return '<a href="mailto:' . htmlspecialchars($v) . '">' . htmlspecialchars($v) . '</a>';
    }
    return htmlspecialchars($v);
}
function flag_code_from_lang($lc) {
    $lc = strtolower($lc);
    $map = [
        'tr'=>'tr','en'=>'gb','de'=>'de','fr'=>'fr','es'=>'es','it'=>'it','nl'=>'nl','ru'=>'ru',
        'ar'=>'sa','fa'=>'ir','zh'=>'cn','ja'=>'jp','ko'=>'kr','el'=>'gr','he'=>'il','pt'=>'pt','az'=>'az'
    ];
    return $map[$lc] ?? $lc;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= strip_tags($name) ?> | Bilgiler</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  font-family: "Poppins", sans-serif;
  text-align: center;
  margin: 0;
  <?= $theme === 'dark'
    ? 'background-color:#0f0f0f;color:#f5f5f5;'
    : 'background-color:#f9f9f9;color:#222;'
  ?>
}
.hero { position: relative; height: 320px; overflow: hidden; }
.hero img { width: 100%; height: 100%; object-fit: cover; <?= $theme==='dark'?'filter:brightness(0.9);':'filter:brightness(0.95);' ?> }
.hero-overlay {
  position:absolute;top:0;left:0;width:100%;height:100%;
  display:flex;flex-direction:column;justify-content:center;align-items:center;
  background:rgba(0,0,0,0.2);color:#fff;text-shadow:0 2px 6px rgba(0,0,0,0.5);
}
.info-card {
  max-width:700px;margin:20px auto 40px;background:<?= $theme==='dark' ? '#1b1b1b':'#fff' ?>;
  border-radius:15px;padding:25px;box-shadow:0 4px 20px rgba(0,0,0,0.1);
}
.menu-buttons{display:flex;justify-content:center;gap:16px;margin:15px 0 25px;flex-wrap:wrap;}
.menu-buttons a{
  display:inline-flex;align-items:center;gap:10px;padding:10px 26px;font-size:1.05rem;border-radius:30px;font-weight:600;
  text-decoration:none;transition:0.3s;
  <?= $theme==='dark'?'background:#2a2a2a;color:#fff;border:1px solid #555;':'background:#fff;color:#333;border:1px solid #ccc;' ?>
}
.menu-buttons a:hover{
  <?= $theme==='dark'?'background-color:#ff9800;color:#000;border-color:#ff9800;':'background-color:#007bff;color:#fff;border-color:#007bff;' ?>
}
.location-btn{
  display:inline-block;margin-top:15px;padding:10px 28px;font-size:1rem;border-radius:30px;font-weight:600;
  text-decoration:none;transition:0.3s;
  <?= $theme==='dark'?'background-color:#ff9800;color:#000;':'background-color:#007bff;color:#fff;' ?>
}
</style>
</head>
<body>

<div class="hero">
  <img src="<?= htmlspecialchars($main) ?>" alt="Restoran Görseli">
  <div class="hero-overlay">
    <?= !empty($nameHTML)
        ? strip_tags($nameHTML, '<div><span><p><b><strong><i><em><u><br><a><h1><h2><h3>')
        : render_content($name, '<em>Restoran Adı</em>') ?>
    <h6 style="margin-top:8px;font-weight:400;">Masa: <?= htmlspecialchars($table['Name']) ?></h6>
  </div>
</div>

<div class="menu-buttons">
  <?php if (!empty($supportedLangs)): ?>
    <?php foreach ($supportedLangs as $L):
        $lc = strtolower($L['LangCode']);
        $flag = flag_code_from_lang($lc);
    ?>
      <a href="menu.php?hash=<?= htmlspecialchars($hash) ?>&theme=<?= htmlspecialchars($theme) ?>&lang=<?= urlencode($lc) ?>">
        <img src="https://flagcdn.com/w20/<?= htmlspecialchars($flag) ?>.png" alt="<?= htmlspecialchars($L['LangName']) ?>">
        <?= htmlspecialchars($L['LangName']) ?>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
      <a href="menu.php?hash=<?= htmlspecialchars($hash) ?>&theme=<?= htmlspecialchars($theme) ?>&lang=tr">
        <img src="https://flagcdn.com/w20/tr.png" alt="Türkçe">
      </a>
      <a href="menu.php?hash=<?= htmlspecialchars($hash) ?>&theme=<?= htmlspecialchars($theme) ?>&lang=en">
        <img src="https://flagcdn.com/w20/gb.png" alt="English">
      </a>
  <?php endif; ?>
</div>

<div class="info-card">
  <p><strong><?= $t['address'] ?>:</strong> <?= render_content($addr) ?></p>
  <p><strong><?= $t['phone'] ?>:</strong> <?= render_link($phone, 'phone') ?></p>
  <?php if (!empty($email)): ?>
    <p><strong><?= $t['email'] ?>:</strong> <?= render_link($email, 'email') ?></p>
  <?php endif; ?>
  <?php if (!empty($map)): ?>
    <a href="<?= htmlspecialchars($map) ?>" target="_blank" class="location-btn"><?= $t['location'] ?></a>
  <?php endif; ?>
</div>

</body>
</html>
