<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/placement_schema.php';
require_once __DIR__ . '/company_auth.php';

function deny_resume(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

$cid = require_company_json();
$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

if ($jobId < 1 || $studentId < 1) {
    deny_resume(400, 'job_id and student_id are required');
}

$conn = get_db_connection();
ensure_placement_schema($conn);

$jobStmt = $conn->prepare('SELECT id FROM jobs WHERE id = ? AND posted_by = ? LIMIT 1');
$jobStmt->bind_param('ii', $jobId, $cid);
$jobStmt->execute();
if (!$jobStmt->get_result()->fetch_assoc()) {
    deny_resume(404, 'Job not found or not owned by your company');
}

$sql = 'SELECT COALESCE(u.resume_path, sp.resume_path) AS resume_path
        FROM job_applications ja
        INNER JOIN users u ON u.id = ja.student_id
        LEFT JOIN student_profiles sp ON sp.email = u.email
        WHERE ja.job_id = ? AND ja.student_id = ?
        LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $jobId, $studentId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || empty($row['resume_path'])) {
    deny_resume(404, 'Resume not found');
}

$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $row['resume_path']);
$fullPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);
$uploadsRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');

if (!$fullPath || !$uploadsRoot || strpos($fullPath, $uploadsRoot) !== 0 || !is_file($fullPath)) {
    deny_resume(404, 'Resume file is missing');
}

$filename = basename($fullPath);
$mime = function_exists('mime_content_type') ? mime_content_type($fullPath) : 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . (string) filesize($fullPath));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($fullPath);
exit;

