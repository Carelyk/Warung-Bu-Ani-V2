<?php
/**
 * Sign the current account out.
 *
 * Logging out is a state change, so the request must be a POST carrying a
 * valid CSRF token. A GET link can be triggered by any third-party page (an
 * <img> tag is enough), which forces the user out without their consent.
 *
 * A `role` field may be supplied to end just one of the two sessions
 * (admin or user); without it, every session is cleared.
 */

require_once __DIR__.'/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Logout harus melalui POST.');
}

requireCsrf();

$role = $_POST['role'] ?? null;

if ($role === 'admin' && isset($_SESSION['admin'])) {
    unset($_SESSION['admin']);
} elseif ($role === 'user' && isset($_SESSION['user'])) {
    unset($_SESSION['user']);
} else {
    // No specific role requested: end everything.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Back to the dashboard if another session is still live, otherwise to login.
if (isLoggedIn()) {
    redirect('modules/dashboard/');
}
redirect('modules/auth/login.php');
