<?php
/**
 * Placement coordinator / admin: list & update students, companies; list placements.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_config.php';

function json_out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function table_exists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function ensure_placements_table(mysqli $conn): string {
    if (table_exists($conn, 'placement')) {
        return 'placement';
    }

    $sql = "CREATE TABLE IF NOT EXISTS placements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        company_id INT NULL,
        company_name VARCHAR(255) NULL,
        package_lpa DECIMAL(10,2) NULL,
        role_title VARCHAR(255) NULL,
        placed_on DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        json_out(500, ['success' => false, 'error' => 'Failed to ensure placements table']);
    }

    return 'placements';
}

$conn = get_db_connection();
$placementsTable = ensure_placements_table($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $resource = isset($_GET['resource']) ? strtolower(trim((string)$_GET['resource'])) : '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    // Browser / direct open: ?resource missing — default stats (avoids "Unknown resource")
    if ($resource === '') {
        $resource = 'stats';
    }

    switch ($resource) {
        case 'stats': {
            $students = 0;
            $companies = 0;
            $placed = 0;
            if ($r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE LOWER(TRIM(user_type)) = 'student'")) {
                $row = $r->fetch_assoc();
                $students = (int)($row['c'] ?? 0);
            }
            if ($r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE LOWER(TRIM(user_type)) = 'company'")) {
                $row = $r->fetch_assoc();
                $companies = (int)($row['c'] ?? 0);
            }
            if ($r = $conn->query("SELECT COUNT(*) AS c FROM `{$placementsTable}`")) {
                $row = $r->fetch_assoc();
                $placed = (int)($row['c'] ?? 0);
            }
            $rate = $students > 0 ? round(($placed / $students) * 100, 1) : 0;
            json_out(200, [
                'success' => true,
                'stats' => [
                    'students_registered' => $students,
                    'companies' => $companies,
                    'placed_students' => $placed,
                    'placement_rate_percent' => $rate,
                ],
            ]);
        }

        case 'students': {
            if ($id > 0) {
                $stmt = $conn->prepare(
                    'SELECT id, name, email, user_type, roll_number, phone, department, created_at
                     FROM users WHERE id = ? AND LOWER(TRIM(user_type)) = ? LIMIT 1'
                );
                $t = 'student';
                $stmt->bind_param('is', $id, $t);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if (!$row) {
                    json_out(404, ['success' => false, 'error' => 'Student not found']);
                }
                json_out(200, ['success' => true, 'student' => $row]);
            }
            $r = $conn->query(
                "SELECT id, name, email, roll_number, phone, department, created_at
                 FROM users WHERE LOWER(TRIM(user_type)) = 'student' ORDER BY id DESC"
            );
            $list = [];
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $list[] = $row;
                }
            }
            json_out(200, ['success' => true, 'students' => $list]);
        }

        case 'companies': {
            if ($id > 0) {
                $stmt = $conn->prepare(
                    'SELECT id, name, email, user_type, phone, company_name, company_type, created_at
                     FROM users WHERE id = ? AND LOWER(TRIM(user_type)) = ? LIMIT 1'
                );
                $t = 'company';
                $stmt->bind_param('is', $id, $t);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if (!$row) {
                    json_out(404, ['success' => false, 'error' => 'Company not found']);
                }
                json_out(200, ['success' => true, 'company' => $row]);
            }
            $r = $conn->query(
                "SELECT id, name, email, phone, company_name, company_type, created_at
                 FROM users WHERE LOWER(TRIM(user_type)) = 'company' ORDER BY id DESC"
            );
            $list = [];
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $list[] = $row;
                }
            }
            json_out(200, ['success' => true, 'companies' => $list]);
        }

        case 'placements': {
            if ($id > 0) {
                $sql = "SELECT p.id, p.student_id, p.company_id, p.company_name, p.package_lpa, p.role_title,
                        p.placed_on, p.notes, p.created_at,
                        s.name AS student_name, s.email AS student_email, s.roll_number AS student_roll,
                        s.phone AS student_phone, s.department AS student_department,
                        cu.name AS company_contact_name, cu.email AS company_email,
                        cu.company_name AS registered_company_name, cu.company_type AS company_type
                        FROM `{$placementsTable}` p
                        INNER JOIN users s ON s.id = p.student_id AND LOWER(TRIM(s.user_type)) = 'student'
                        LEFT JOIN users cu ON cu.id = p.company_id AND LOWER(TRIM(cu.user_type)) = 'company'
                        WHERE p.id = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    json_out(500, ['success' => false, 'error' => 'Failed to prepare placement detail query: ' . $conn->error]);
                }
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if (!$row) {
                    json_out(404, ['success' => false, 'error' => 'Placement not found']);
                }
                json_out(200, ['success' => true, 'placement' => $row]);
            }
            $sql = "SELECT p.id, p.student_id, p.company_id, p.company_name, p.package_lpa, p.role_title,
                    p.placed_on, p.notes,
                    s.name AS student_name, s.email AS student_email, s.roll_number AS student_roll,
                    s.department AS student_department,
                    COALESCE(cu.company_name, p.company_name) AS display_company
                    FROM `{$placementsTable}` p
                    INNER JOIN users s ON s.id = p.student_id
                    LEFT JOIN users cu ON cu.id = p.company_id
                    ORDER BY p.placed_on DESC, p.id DESC";
            $r = $conn->query($sql);
            if (!$r) {
                json_out(500, ['success' => false, 'error' => 'Failed to load placements: ' . $conn->error]);
            }
            $list = [];
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $list[] = $row;
                }
            }
            json_out(200, ['success' => true, 'placements' => $list]);
        }

        default:
            json_out(400, [
                'success' => false,
                'error' => 'Unknown resource',
                'valid_resources' => ['stats', 'students', 'companies', 'placements'],
                'example' => 'placement_admin_api.php?resource=students',
            ]);
    }
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $action = isset($body['action']) ? trim((string)$body['action']) : '';

    if ($action === 'update_student') {
        $sid = isset($body['id']) ? (int)$body['id'] : 0;
        if ($sid <= 0) {
            json_out(400, ['success' => false, 'error' => 'Invalid student id']);
        }
        $chk = $conn->prepare('SELECT id FROM users WHERE id = ? AND LOWER(TRIM(user_type)) = ? LIMIT 1');
        $st = 'student';
        $chk->bind_param('is', $sid, $st);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            json_out(404, ['success' => false, 'error' => 'Student not found']);
        }

        $name = trim((string)($body['name'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $roll = trim((string)($body['roll_number'] ?? ''));
        $phone = trim((string)($body['phone'] ?? ''));
        $dept = trim((string)($body['department'] ?? ''));

        if ($name === '' || $email === '') {
            json_out(400, ['success' => false, 'error' => 'Name and email are required']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(400, ['success' => false, 'error' => 'Invalid email']);
        }

        $dup = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $dup->bind_param('si', $email, $sid);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            json_out(409, ['success' => false, 'error' => 'Email already in use']);
        }

        $stmt = $conn->prepare(
            'UPDATE users SET name = ?, email = ?, roll_number = ?, phone = ?, department = ? WHERE id = ?'
        );
        $stmt->bind_param('sssssi', $name, $email, $roll, $phone, $dept, $sid);
        if (!$stmt->execute()) {
            json_out(500, ['success' => false, 'error' => 'Update failed']);
        }
        json_out(200, ['success' => true, 'message' => 'Student updated']);
    }

    if ($action === 'update_company') {
        $cid = isset($body['id']) ? (int)$body['id'] : 0;
        if ($cid <= 0) {
            json_out(400, ['success' => false, 'error' => 'Invalid company id']);
        }
        $chk = $conn->prepare('SELECT id FROM users WHERE id = ? AND LOWER(TRIM(user_type)) = ? LIMIT 1');
        $ct = 'company';
        $chk->bind_param('is', $cid, $ct);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            json_out(404, ['success' => false, 'error' => 'Company not found']);
        }

        $name = trim((string)($body['name'] ?? ''));
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $phone = trim((string)($body['phone'] ?? ''));
        $cname = trim((string)($body['company_name'] ?? ''));
        $ctype = trim((string)($body['company_type'] ?? ''));

        if ($name === '' || $email === '') {
            json_out(400, ['success' => false, 'error' => 'Name and email are required']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(400, ['success' => false, 'error' => 'Invalid email']);
        }

        $dup = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $dup->bind_param('si', $email, $cid);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            json_out(409, ['success' => false, 'error' => 'Email already in use']);
        }

        $stmt = $conn->prepare(
            'UPDATE users SET name = ?, email = ?, phone = ?, company_name = ?, company_type = ? WHERE id = ?'
        );
        $stmt->bind_param('sssssi', $name, $email, $phone, $cname, $ctype, $cid);
        if (!$stmt->execute()) {
            json_out(500, ['success' => false, 'error' => 'Update failed']);
        }
        json_out(200, ['success' => true, 'message' => 'Company updated']);
    }

    if ($action === 'add_placement') {
        $studentId = isset($body['student_id']) ? (int)$body['student_id'] : 0;
        $companyId = isset($body['company_id']) && $body['company_id'] !== '' && $body['company_id'] !== null
            ? (int)$body['company_id'] : null;
        $companyName = trim((string)($body['company_name'] ?? ''));
        $package = isset($body['package_lpa']) ? (float)$body['package_lpa'] : null;
        $role = trim((string)($body['role_title'] ?? ''));
        $placedOn = trim((string)($body['placed_on'] ?? ''));
        $notes = trim((string)($body['notes'] ?? ''));

        if ($studentId <= 0) {
            json_out(400, ['success' => false, 'error' => 'student_id required']);
        }
        $chk = $conn->prepare('SELECT id FROM users WHERE id = ? AND LOWER(TRIM(user_type)) = ? LIMIT 1');
        $st = 'student';
        $chk->bind_param('is', $studentId, $st);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            json_out(400, ['success' => false, 'error' => 'Invalid student']);
        }
        if ($companyId !== null && $companyId > 0) {
            $chk2 = $conn->prepare('SELECT id FROM users WHERE id = ? AND LOWER(TRIM(user_type)) = ? LIMIT 1');
            $ct = 'company';
            $chk2->bind_param('is', $companyId, $ct);
            $chk2->execute();
            if (!$chk2->get_result()->fetch_assoc()) {
                json_out(400, ['success' => false, 'error' => 'Invalid company id']);
            }
        } else {
            $companyId = null;
        }

        $placedDate = $placedOn === '' ? null : $placedOn;
        $pkgBind = ($package === null || $package === '') ? null : (float)$package;

        $stmt = $conn->prepare(
            "INSERT INTO `{$placementsTable}` (student_id, company_id, company_name, package_lpa, role_title, placed_on, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            json_out(500, ['success' => false, 'error' => 'Could not prepare placement insert: ' . $conn->error]);
        }
        $stmt->bind_param(
            'iisdsss',
            $studentId,
            $companyId,
            $companyName,
            $pkgBind,
            $role,
            $placedDate,
            $notes
        );
        if (!$stmt->execute()) {
            json_out(500, ['success' => false, 'error' => 'Could not add placement: ' . $stmt->error]);
        }
        json_out(200, ['success' => true, 'message' => 'Placement recorded', 'id' => $conn->insert_id]);
    }

    json_out(400, ['success' => false, 'error' => 'Unknown action']);
}

json_out(405, ['success' => false, 'error' => 'Method not allowed']);
