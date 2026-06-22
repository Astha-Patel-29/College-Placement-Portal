<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';

function respond_profile(int $statusCode, array $payload): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function ensure_student_profiles_table(mysqli $conn): void {
    $create = "CREATE TABLE IF NOT EXISTS student_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(64) NULL,
        college_name VARCHAR(255) NULL,
        university VARCHAR(255) NULL,
        course VARCHAR(255) NULL,
        skills TEXT NULL,
        resume_path VARCHAR(512) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_profiles_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    @$conn->query($create);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_profile(405, ['success' => false, 'error' => 'Method not allowed']);
}

$email = isset($_GET['email']) ? strtolower(trim((string)$_GET['email'])) : '';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond_profile(400, ['success' => false, 'error' => 'Valid email is required']);
}

$conn = get_db_connection();
ensure_student_profiles_table($conn);

$stmt = $conn->prepare('SELECT name, email, phone, college_name, university, course, skills, resume_path, updated_at FROM student_profiles WHERE email = ? LIMIT 1');
if (!$stmt) {
    respond_profile(500, ['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error]);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result ? $result->fetch_assoc() : null;

respond_profile(200, [
    'success' => true,
    'profile' => $profile ?: null
]);

?>
