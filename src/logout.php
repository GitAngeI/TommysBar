<?php
// =============================================
//  logout.php — Tommy's Bar
//  Destroys session completely and redirects
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Unset all session variables
$_SESSION = [];

// 2. Destroy the session cookie on the client
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy session data on the server
session_destroy();

// 4. Redirect to login with a confirmation flag
header('Location: login.php?logged_out=1');
exit;
