<?php
require __DIR__ . '/config.php';

$_SESSION = [];
// session_destroy() alone only invalidates the session server-side — the
// browser keeps sending the now-dead session cookie until it expires
// naturally. Expiring it explicitly is the standard full-logout step.
if (ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
}
session_destroy();

header('Location: login.php');
exit;
