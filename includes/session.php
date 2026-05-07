<?php declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sessionTimeout = (int) ($appConfig['session_timeout'] ?? 3600);
$now = time();

if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > $sessionTimeout) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', $now - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
    session_start();
}

$_SESSION['last_activity'] = $now;
