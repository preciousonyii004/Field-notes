<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
start_auth_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !auth_validate_csrf()) {
    http_response_code(400);
    exit('Bad request');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();
header('Location: index.html', true, 303);