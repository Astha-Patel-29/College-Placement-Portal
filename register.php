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

// Read JSON body or form-encoded
$raw = file_get_contents('php://input');
$parsed = json_decode($raw, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

function respond($statusCode, $payload) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// Basic validation
$required = ['name','email','password','confirmPassword','userType'];
foreach ($required as $field) {
    if (empty($parsed[$field])) {
        respond(400, ['success' => false, 'error' => "Missing field: $field"]);
    }
}

if (!filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
    respond(400, ['success' => false, 'error' => 'Invalid email']);
}

if ($parsed['password'] !== $parsed['confirmPassword']) {
    respond(400, ['success' => false, 'error' => 'Passwords do not match']);
}

$userType = strtolower(trim($parsed['userType']));
if (!in_array($userType, ['student','placement coordinator','admin','company'], true)) {
    respond(400, ['success' => false, 'error' => 'Invalid userType']);
}

$name = trim($parsed['name']);
$email = strtolower(trim($parsed['email']));
$passwordHash = password_hash($parsed['password'], PASSWORD_DEFAULT);

// Optional fields
$rollNumber = isset($parsed['rollNumber']) ? trim($parsed['rollNumber']) : null;
$phone = isset($parsed['phone']) ? trim($parsed['phone']) : null;
$department = isset($parsed['department']) ? trim($parsed['department']) : null;
$companyName = isset($parsed['companyName']) ? trim($parsed['companyName']) : null;
$companyType = isset($parsed['companyType']) ? trim($parsed['companyType']) : null;

try {
    $conn = get_db_connection();

    // Ensure table exists (simple bootstrap)
    $createSql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        user_type VARCHAR(64) NOT NULL,
        roll_number VARCHAR(64) NULL,
        phone VARCHAR(32) NULL,
        department VARCHAR(64) NULL,
        company_name VARCHAR(255) NULL,
        company_type VARCHAR(64) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($createSql)) {
        respond(500, ['success' => false, 'error' => 'Failed to initialize users table']);
    }

    // Insert using prepared statement
    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, user_type, roll_number, phone, department, company_name, company_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        respond(500, ['success' => false, 'error' => 'Failed to prepare statement']);
    }

    $stmt->bind_param(
        'sssssssss',
        $name,
        $email,
        $passwordHash,
        $userType,
        $rollNumber,
        $phone,
        $department,
        $companyName,
        $companyType
    );

    $stmt->execute();

    $newId = (int)$conn->insert_id;
    respond(200, [
        'success' => true,
        'message' => 'Registered successfully',
        'id' => $newId,
        'name' => $name,
        'email' => $email,
        'userType' => $userType,
    ]);
} catch (Throwable $e) {
    if (isset($stmt) && $stmt instanceof mysqli_stmt && $stmt->errno === 1062) {
        respond(409, ['success' => false, 'error' => 'Email already registered']);
    }

    error_log('Registration error: ' . $e->getMessage());
    $detail = isset($conn) && $conn instanceof mysqli && $conn->error
        ? $conn->error
        : $e->getMessage();
    respond(500, ['success' => false, 'error' => 'Server error while processing registration: ' . $detail]);
}

?>
