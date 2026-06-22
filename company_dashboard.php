<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/placement_schema.php';
require_once __DIR__ . '/company_auth.php';

function respond_company(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

$ALLOWED_OPPORTUNITY = ['full_time', 'part_time'];
$ALLOWED_JOB_TYPE = ['remote', 'on_site', 'freelance', 'full_time', 'part_time', 'contract', 'internship'];

$cid = require_company_json();
$conn = get_db_connection();
ensure_placement_schema($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare(
        'SELECT id, name, email, phone, company_name, company_type, user_type, created_at FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        respond_company(404, ['success' => false, 'error' => 'Account not found']);
    }

    $jq = $conn->prepare(
        'SELECT j.id, j.title, j.company_name, j.description, j.location, j.salary, j.deadline, j.created_at,
                j.opportunity_type, j.job_type,
                (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) AS application_count
         FROM jobs j
         WHERE j.posted_by = ?
         ORDER BY j.created_at DESC'
    );
    $jq->bind_param('i', $cid);
    $jq->execute();
    $res = $jq->get_result();

    $jobs = [];
    $totalApps = 0;
    while ($row = $res->fetch_assoc()) {
        $ac = (int) $row['application_count'];
        $totalApps += $ac;
        $jobs[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'company_name' => $row['company_name'],
            'description' => $row['description'],
            'location' => $row['location'],
            'package' => $row['salary'],
            'salary' => $row['salary'],
            'deadline' => $row['deadline'],
            'created_at' => $row['created_at'],
            'opportunity_type' => $row['opportunity_type'],
            'job_type' => $row['job_type'],
            'application_count' => $ac,
        ];
    }

    respond_company(200, [
        'success' => true,
        'company' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'company_name' => $user['company_name'],
            'company_type' => $user['company_type'],
            'created_at' => $user['created_at'],
        ],
        'stats' => [
            'jobs_posted' => count($jobs),
            'applications_received' => $totalApps,
        ],
        'jobs' => $jobs,
    ]);
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        respond_company(400, ['success' => false, 'error' => 'Invalid JSON']);
    }

    $title = isset($parsed['title']) ? trim((string) $parsed['title']) : '';
    $description = isset($parsed['description']) ? trim((string) $parsed['description']) : '';
    $package = isset($parsed['package']) ? trim((string) $parsed['package']) : '';
    if ($package === '' && isset($parsed['salary'])) {
        $package = trim((string) $parsed['salary']);
    }
    $deadline = isset($parsed['deadline']) ? trim((string) $parsed['deadline']) : '';
    $opportunity = isset($parsed['opportunity_type']) ? trim((string) $parsed['opportunity_type']) : '';
    $jobType = isset($parsed['job_type']) ? trim((string) $parsed['job_type']) : '';
    $location = isset($parsed['location']) ? trim((string) $parsed['location']) : '';

    if ($title === '' || $description === '' || $package === '' || $deadline === '') {
        respond_company(400, ['success' => false, 'error' => 'title, description, package, and deadline are required']);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
        respond_company(400, ['success' => false, 'error' => 'deadline must be YYYY-MM-DD']);
    }

    if (!in_array($opportunity, $ALLOWED_OPPORTUNITY, true)) {
        respond_company(400, ['success' => false, 'error' => 'Invalid opportunity_type']);
    }
    if (!in_array($jobType, $ALLOWED_JOB_TYPE, true)) {
        respond_company(400, ['success' => false, 'error' => 'Invalid job_type']);
    }

    $coStmt = $conn->prepare('SELECT company_name FROM users WHERE id = ? LIMIT 1');
    $coStmt->bind_param('i', $cid);
    $coStmt->execute();
    $coRow = $coStmt->get_result()->fetch_assoc();
    $companyName = $coRow && !empty($coRow['company_name']) ? $coRow['company_name'] : ($_SESSION['user_name'] ?? 'Company');

    $ins = $conn->prepare(
        'INSERT INTO jobs (title, company_name, description, location, salary, deadline, posted_by, opportunity_type, job_type)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->bind_param(
        'ssssssiss',
        $title,
        $companyName,
        $description,
        $location,
        $package,
        $deadline,
        $cid,
        $opportunity,
        $jobType
    );

    if (!$ins->execute()) {
        respond_company(500, ['success' => false, 'error' => 'Could not post job']);
    }

    $newId = (int) $conn->insert_id;
    respond_company(200, [
        'success' => true,
        'message' => 'Job posted',
        'job_id' => $newId,
    ]);
}

respond_company(405, ['success' => false, 'error' => 'Method not allowed']);
