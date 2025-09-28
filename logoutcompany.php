<?php
// Start session
session_start();

// Log the logout attempt for security
if (isset($_SESSION['id']) && isset($_SESSION['company_name'])) {
    error_log("Company logout: ID=" . $_SESSION['id'] . ", Name=" . $_SESSION['company_name']);
}

// Destroy all session data
$_SESSION = array();

// Delete session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page
header("Location: logincompany.html");
exit;
?>