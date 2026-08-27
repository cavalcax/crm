<?php
require_once 'config/db.php';
require_once 'helpers/functions.php';

// Invalidate persistent token in database if user is logged in
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Ignore DB error
    }
}

// Clear remember_token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['remember_token']);
}

// Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit;
?>