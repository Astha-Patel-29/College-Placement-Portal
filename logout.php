<?php
require_once __DIR__ . '/student_auth.php';

start_app_session();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header('Location: login.html');
exit;
