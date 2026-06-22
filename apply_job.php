<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/placement_schema.php';
require_once __DIR__ . '/student_auth.php';

function respond_apply(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_apply(405, ['success' => false, 'error' => 'Method not allowed']);
}

$uid = require_student_json();

$raw = file_get_contents('php://input');
$parsed = json_decode($raw, true);
if (!is_array($parsed) || empty($parsed['job_id'])) {
    respond_apply(400, ['success' => false, 'error' => 'job_id is required']);
}

$jobId = (int) $parsed['job_id'];
if ($jobId < 1) {
    respond_apply(400, ['success' => false, 'error' => 'Invalid job_id']);
}

$conn = get_db_connection();
ensure_placement_schema($conn);

$chk = $conn->prepare('SELECT id FROM jobs WHERE id = ? LIMIT 1');
$chk->bind_param('i', $jobId);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    respond_apply(404, ['success' => false, 'error' => 'Job not found']);
}

$ins = $conn->prepare('INSERT INTO job_applications (job_id, student_id, status) VALUES (?, ?, ?)');
$status = 'submitted';
$ins->bind_param('iis', $jobId, $uid, $status);

if (!$ins->execute()) {
    if ($conn->errno === 1062) {
        respond_apply(409, ['success' => false, 'error' => 'You have already applied for this job']);
    }
    respond_apply(500, ['success' => false, 'error' => 'Could not submit application']);
}

respond_apply(200, ['success' => true, 'message' => 'Application submitted']);
