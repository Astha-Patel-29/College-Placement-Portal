<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/placement_schema.php';
require_once __DIR__ . '/company_auth.php';

function respond_ja(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_ja(405, ['success' => false, 'error' => 'Method not allowed']);
}

$cid = require_company_json();

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
if ($jobId < 1) {
    respond_ja(400, ['success' => false, 'error' => 'job_id is required']);
}

$conn = get_db_connection();
ensure_placement_schema($conn);

$chk = $conn->prepare('SELECT id, title FROM jobs WHERE id = ? AND posted_by = ? LIMIT 1');
$chk->bind_param('ii', $jobId, $cid);
$chk->execute();
$job = $chk->get_result()->fetch_assoc();
if (!$job) {
    respond_ja(404, ['success' => false, 'error' => 'Job not found or not owned by your company']);
}

$sql = 'SELECT u.id, u.name, u.email, u.roll_number,
               COALESCE(sp.phone, u.phone) AS phone,
               COALESCE(u.department, sp.course) AS department,
               u.year_of_study,
               COALESCE(u.resume_path, sp.resume_path) AS resume_path,
               sp.course,
               ja.applied_at, ja.status
        FROM job_applications ja
        INNER JOIN users u ON u.id = ja.student_id
        LEFT JOIN student_profiles sp ON sp.email = u.email
        WHERE ja.job_id = ?
        ORDER BY ja.applied_at DESC';

$st = $conn->prepare($sql);
$st->bind_param('i', $jobId);
$st->execute();
$res = $st->get_result();

$applicants = [];
while ($row = $res->fetch_assoc()) {
    $sid = (int) $row['id'];
    $hasResume = !empty($row['resume_path']);
    $resumeFilename = $hasResume ? basename($row['resume_path']) : null;
    $resumeUrl = $hasResume
        ? 'company_download_resume.php?job_id=' . $jobId . '&student_id=' . $sid
        : null;

    $applicants[] = [
        'student_id' => $sid,
        'name' => $row['name'],
        'email' => $row['email'],
        'roll_number' => $row['roll_number'],
        'phone' => $row['phone'],
        'department' => $row['department'],
        'year_of_study' => $row['year_of_study'],
        'course' => $row['course'],
        'applied_at' => $row['applied_at'],
        'status' => $row['status'],
        'has_resume' => $hasResume,
        'resume_filename' => $resumeFilename,
        'resume_download_url' => $resumeUrl,
    ];
}

respond_ja(200, [
    'success' => true,
    'job' => [
        'id' => (int) $job['id'],
        'title' => $job['title'],
    ],
    'applicant_count' => count($applicants),
    'applicants' => $applicants,
]);
