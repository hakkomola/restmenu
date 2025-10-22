<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['restaurant_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /restaurants/login.php');
        exit;
    }
}

function current_restaurant_id(): ?int {
    return $_SESSION['restaurant_id'] ?? null;
}

function current_branch_id(): ?int {
    return $_SESSION['current_branch'] ?? null;
}

// Permissions JSON: $_SESSION['permissions'] = ['menu'=>true, 'orders'=>true, ...]
function can(string $permKey): bool {
    return !empty($_SESSION['permissions'][$permKey]);
}

function require_permission(string $permKey): void {
    if (!can($permKey)) {
        http_response_code(403);
        die('Bu sayfayı görüntülemek için yetkiniz yok.');
    }
}

// Kullanıcının yetkili olduğu şubeler
function user_branches(): array {
    return $_SESSION['branches'] ?? [];
}

function user_has_branch(int $branchId): bool {
    if (empty($_SESSION['branches'])) return false;
    foreach ($_SESSION['branches'] as $b) {
        if ((int)$b['BranchID'] === (int)$branchId) return true;
    }
    return false;
}

// Şube değiştir (Admin ise tüm şubeleri; değilse sadece yetkili olduğu şubeleri seçebilir)
function set_current_branch(int $branchId): bool {
    if (empty($_SESSION['is_admin'])) {
        if (!user_has_branch($branchId)) return false;
    }
    $_SESSION['current_branch'] = $branchId;
    return true;
}

// Login işleminden sonra çağrılacak: kullanıcı, rol ve şubeleri session'a yükle
function hydrate_session_from_user(array $userRow): void {
    // $userRow: u.*, RoleName, Permissions (JOIN ile geliyor varsayım)
    $_SESSION['user_id'] = (int)$userRow['UserID'];
    $_SESSION['restaurant_id'] = (int)$userRow['RestaurantID'];
    $_SESSION['role_name'] = $userRow['RoleName'];
    $perms = $userRow['Permissions'];
    if (is_string($perms)) {
        $perms = json_decode($perms, true);
    }
    $_SESSION['permissions'] = is_array($perms) ? $perms : [];
    $_SESSION['is_admin'] = !empty($_SESSION['permissions']['branches']) && !empty($_SESSION['permissions']['users']) && !empty($_SESSION['permissions']['menu']) && !empty($_SESSION['permissions']['orders']) && !empty($_SESSION['permissions']['tables']);
}

// Yardımcı: her SELECT/INSERT’te kullanılacak güvenli filtre parçaları
function sql_where_restaurant_branch(string $alias = ''): string {
    $a = $alias ? ($alias . '.') : '';
    $rid = (int)current_restaurant_id();
    $bid = (int)current_branch_id();
    // Admin tüm şubeleri görebilir → Branch filtresi opsiyonel olabilir
    if (!empty($_SESSION['is_admin'])) {
        return sprintf("%sRestaurantID = %d", $a, $rid);
    }
    return sprintf("%sRestaurantID = %d AND %sBranchID = %d", $a, $rid, $a, $bid);
}
