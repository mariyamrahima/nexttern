<?php
session_start();

// Complete session cleanup
session_unset();
session_destroy();

// Delete session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear any persistent cookies (if you have any)
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/');
}

// Clear any other admin-specific cookies
if (isset($_COOKIE['admin_session'])) {
    setcookie('admin_session', '', time() - 3600, '/');
}

// Regenerate session ID for security
session_start();
session_regenerate_id(true);

// Redirect to admin login page with success message
header("Location: login.html?message=" . urlencode("Logged out successfully"));
exit;
?>