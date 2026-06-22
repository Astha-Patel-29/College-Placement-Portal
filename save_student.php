<?php
/**
 * Save student profile (form POST + file upload).
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/db_config.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function exit_with_message(string $msg): void {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:sans-serif;padding:2rem;">';
    echo '<p>' . h($msg) . '</p><p><a href="student_details.html">Back to form</a></p></body></html>';
    exit;
}

function student_profiles_bootstrap(mysqli $conn): void {
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
    $conn->query($create);
    ensure_student_profile_columns($conn);
}

function ensure_student_profile_columns(mysqli $conn): void {
    $dbRes = $conn->query('SELECT DATABASE()');
    if (!$dbRes) {
        return;
    }
    $dbRow = $dbRes->fetch_row();
    $dbName = isset($dbRow[0]) ? (string)$dbRow[0] : '';
    if ($dbName === '') {
        return;
    }
    $esc = $conn->real_escape_string($dbName);
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$esc}' AND TABLE_NAME = 'student_profiles'");
    if (!$res) {
        return;
    }
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[$row['COLUMN_NAME']] = true;
    }
    if ($cols === []) {
        return;
    }
    $run = static function (mysqli $c, string $sql): void {
        @$c->query($sql);
    };
    if (!isset($cols['name'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN name VARCHAR(255) NULL');
    }
    if (!isset($cols['email'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN email VARCHAR(255) NULL');
    }
    if (!isset($cols['phone'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN phone VARCHAR(64) NULL');
    }
    if (!isset($cols['college_name'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN college_name VARCHAR(255) NULL');
    }
    if (!isset($cols['university'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN university VARCHAR(255) NULL');
    }
    if (!isset($cols['course'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN course VARCHAR(255) NULL');
    }
    if (!isset($cols['skills'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN skills TEXT NULL');
    }
    if (!isset($cols['resume_path']) && isset($cols['resume'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN resume_path VARCHAR(512) NULL');
        $conn->query("UPDATE student_profiles SET resume_path = CONCAT('uploads/', resume) WHERE resume IS NOT NULL AND TRIM(resume) <> ''");
    } elseif (!isset($cols['resume_path'])) {
        $run($conn, 'ALTER TABLE student_profiles ADD COLUMN resume_path VARCHAR(512) NULL');
    }
    $idx = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = '{$esc}' AND TABLE_NAME = 'student_profiles' AND INDEX_NAME = 'uq_student_profiles_email' LIMIT 1");
    if ($idx && $idx->num_rows === 0) {
        @$conn->query('ALTER TABLE student_profiles ADD UNIQUE INDEX uq_student_profiles_email (email)');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<!DOCTYPE html><html><body><p>Method not allowed.</p><a href="student_details.html">Back</a></body></html>';
    exit;
}

$name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$email = isset($_POST['email']) ? strtolower(trim((string)$_POST['email'])) : '';
$phone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
$collegeName = isset($_POST['college_name']) ? trim((string)$_POST['college_name']) : '';
$university = isset($_POST['university']) ? trim((string)$_POST['university']) : '';
$course = isset($_POST['course']) ? trim((string)$_POST['course']) : '';
$skills = isset($_POST['skills']) ? trim((string)$_POST['skills']) : '';

if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit_with_message('Please enter a valid name and email.');
}
if ($collegeName === '' || $university === '' || $course === '' || $skills === '') {
    exit_with_message('Please fill college name, university, course, and skills.');
}

$conn = get_db_connection();
student_profiles_bootstrap($conn);

$resumePath = null;
$uploadDir = __DIR__ . '/uploads/resumes';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        exit_with_message('Could not create upload folder.');
    }
}

if (!empty($_FILES['resume']['name']) && isset($_FILES['resume']['tmp_name']) && is_uploaded_file($_FILES['resume']['tmp_name'])) {
    if ((int)$_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        exit_with_message('Resume upload failed. Please try again.');
    }
    if ($_FILES['resume']['size'] > 5 * 1024 * 1024) {
        exit_with_message('Resume must be 5 MB or smaller.');
    }
    $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowed, true)) {
        exit_with_message('Resume must be PDF, DOC, or DOCX.');
    }
    $safe = 'resume_' . preg_replace('/[^a-z0-9._-]/i', '_', strstr($email, '@', true) ?: 'user') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (!move_uploaded_file($_FILES['resume']['tmp_name'], $dest)) {
        exit_with_message('Could not save the resume file.');
    }
    $resumePath = 'uploads/resumes/' . $safe;
} else {
    exit_with_message('Please upload your resume.');
}

$sql = 'INSERT INTO student_profiles (
            name, email, phone, college_name, university, course, skills, resume_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            phone = VALUES(phone),
            college_name = VALUES(college_name),
            university = VALUES(university),
            course = VALUES(course),
            skills = VALUES(skills),
            resume_path = VALUES(resume_path)';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $detail = $conn->error ? (' Details: ' . $conn->error) : '';
    exit_with_message('Database error — could not save profile.' . $detail . ' If this persists, open phpMyAdmin and check the student_profiles table columns.');
}

$stmt->bind_param(
    'ssssssss',
    $name,
    $email,
    $phone,
    $collegeName,
    $university,
    $course,
    $skills,
    $resumePath
);

try {
    $stmt->execute();
} catch (Throwable $e) {
    exit_with_message('Could not save your profile. ' . ($conn->error ?: $e->getMessage()));
}
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile saved</title>
  <link rel="stylesheet" href="navbar.css">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f8f9ff; margin: 0; padding-top: 80px; text-align: center; padding-left: 16px; padding-right: 16px; }
    .box { background: #fff; max-width: 480px; margin: 0 auto; padding: 32px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    h1 { color: #233fbb; font-size: 1.35rem; }
    p { color: #555; line-height: 1.5; }
    a { color: #233fbb; font-weight: 600; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Profile saved</h1>
    <p>Your details, institute information, and resume were stored successfully.</p>
    <p><a href="student-dashboard.html">Open student dashboard</a> · <a href="student_details.html">Edit profile again</a></p>
  </div>
  <script>
    (function () {
      var profile = {
        name: <?php echo json_encode($name); ?>,
        email: <?php echo json_encode($email); ?>,
        phone: <?php echo json_encode($phone); ?>,
        college_name: <?php echo json_encode($collegeName); ?>,
        university: <?php echo json_encode($university); ?>,
        course: <?php echo json_encode($course); ?>,
        skills: <?php echo json_encode($skills); ?>,
        resume_path: <?php echo json_encode($resumePath); ?>
      };

      localStorage.setItem('studentProfile', JSON.stringify(profile));

      var rawUser = localStorage.getItem('currentUser');
      if (!rawUser) return;

      try {
        var user = JSON.parse(rawUser);
        user.name = profile.name || user.name;
        user.email = profile.email || user.email;
        localStorage.setItem('currentUser', JSON.stringify(user));
      } catch (e) {}
    })();
  </script>
</body>
</html>
