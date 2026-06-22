<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/student_auth.php';

$raw = file_get_contents('php://input');
$parsed = json_decode($raw, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

function respond_login($statusCode, $payload) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (empty($parsed['email']) || empty($parsed['password'])) {
    respond_login(400, ['success' => false, 'error' => 'Email and password are required']);
}

$email = strtolower(trim($parsed['email']));
$password = (string)$parsed['password'];

$conn = get_db_connection();

$stmt = $conn->prepare('SELECT id, name, email, password_hash, user_type FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    respond_login(500, ['success' => false, 'error' => 'Failed to prepare statement']);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    respond_login(401, ['success' => false, 'error' => 'Invalid credentials']);
}

start_app_session();
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = strtolower(trim((string)$user['user_type']));

respond_login(200, [
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => strtolower($user['user_type'])
    ]
]);


