<?php
// =============================================
//  auth_check.php — Tommy's Bar
//  Session middleware. Include at the TOP of
//  every protected file (dashboard.php, ticket.php, etc.)
//
//  Usage (first line after <?php):
//      require_once __DIR__ . '/auth_check.php';
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not authenticated → redirect to login
if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_user'])) {
    // Preserve the originally requested URL so we can redirect back after login
    $requested = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: login.php?redirect=' . $requested);
    exit;
}

// ── Optional: regenerate session ID periodically ──
// Helps mitigate session fixation on long-lived sessions
if (empty($_SESSION['_last_regenerated'])) {
    $_SESSION['_last_regenerated'] = time();
} elseif (time() - $_SESSION['_last_regenerated'] > 900) { // every 15 min
    session_regenerate_id(true);
    $_SESSION['_last_regenerated'] = time();
}
""