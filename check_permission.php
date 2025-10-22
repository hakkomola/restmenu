function require_permission($key) {
    if (empty($_SESSION['permissions'][$key])) {
        header('Location: ../error_no_access.php');
        exit;
    }
}
