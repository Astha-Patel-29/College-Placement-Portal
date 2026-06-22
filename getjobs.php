<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/placement_schema.php';
require_once __DIR__ . '/student_auth.php';

function respond_jobs(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond_jobs(405, ['success' => false, 'error' => 'Method not allowed']);
}

start_app_session();

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$currentRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$isStudent = $currentRole === 'student';

$conn = get_db_connection();
ensure_placement_schema($conn);

$sql = 'SELECT j.id, j.title, j.company_name, j.description, j.location, j.salary, j.deadline,
               j.created_at, j.opportunity_type, j.job_type,
               COUNT(ja.id) AS applicant_count
        FROM jobs j
        LEFT JOIN job_applications ja ON ja.job_id = j.id
        GROUP BY j.id, j.title, j.company_name, j.description, j.location, j.salary, j.deadline,
                 j.created_at, j.opportunity_type, j.job_type
        ORDER BY j.created_at DESC, j.id DESC';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond_jobs(500, ['success' => false, 'error' => 'Could not load jobs']);
}

$stmt->execute();
$res = $stmt->get_result();

$appliedMap = [];
if ($isStudent && $currentUserId > 0) {
    $appStmt = $conn->prepare('SELECT job_id, status, applied_at FROM job_applications WHERE student_id = ?');
    if ($appStmt) {
        $appStmt->bind_param('i', $currentUserId);
        $appStmt->execute();
        $appRes = $appStmt->get_result();
        while ($row = $appRes->fetch_assoc()) {
            $appliedMap[(int) $row['job_id']] = [
                'status' => $row['status'],
                'applied_at' => $row['applied_at'],
            ];
        }
    }
}

$jobs = [];
while ($row = $res->fetch_assoc()) {
    $jobId = (int) $row['id'];
    $applied = $appliedMap[$jobId] ?? null;
    $jobs[] = [
        'id' => $jobId,
        'title' => $row['title'],
        'company_name' => $row['company_name'],
        'description' => $row['description'],
        'location' => $row['location'],
        'salary' => $row['salary'],
        'package' => $row['salary'],
        'deadline' => $row['deadline'],
        'created_at' => $row['created_at'],
        'opportunity_type' => $row['opportunity_type'],
        'job_type' => $row['job_type'],
        'applicant_count' => (int) $row['applicant_count'],
        'has_applied' => $applied !== null,
        'application_status' => $applied['status'] ?? null,
        'applied_at' => $applied['applied_at'] ?? null,
    ];
}

respond_jobs(200, [
    'success' => true,
    'role' => $currentRole,
    'is_student' => $isStudent,
    'jobs' => $jobs,
]);

