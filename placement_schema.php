<?php
/**
 * Ensures jobs, applications, and student profile columns exist.
 */
function ensure_placement_schema(mysqli $conn): void
{
    $col = function (string $table, string $column) use ($conn): bool {
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($column);
        $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $r && $r->num_rows > 0;
    };

    if (!$col('users', 'year_of_study')) {
        $conn->query("ALTER TABLE users ADD COLUMN year_of_study VARCHAR(32) NULL AFTER department");
    }
    if (!$col('users', 'resume_path')) {
        $conn->query("ALTER TABLE users ADD COLUMN resume_path VARCHAR(512) NULL AFTER year_of_study");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            location VARCHAR(255) NULL,
            salary VARCHAR(128) NULL,
            deadline DATE NULL,
            posted_by INT NULL,
            opportunity_type VARCHAR(32) NULL,
            job_type VARCHAR(32) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_posted_by (posted_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (!$col('jobs', 'posted_by')) {
        $conn->query('ALTER TABLE jobs ADD COLUMN posted_by INT NULL AFTER deadline');
        $conn->query('ALTER TABLE jobs ADD KEY idx_posted_by (posted_by)');
    }
    if (!$col('jobs', 'opportunity_type')) {
        $conn->query('ALTER TABLE jobs ADD COLUMN opportunity_type VARCHAR(32) NULL AFTER posted_by');
    }
    if (!$col('jobs', 'job_type')) {
        $conn->query('ALTER TABLE jobs ADD COLUMN job_type VARCHAR(32) NULL AFTER opportunity_type');
    }
    $conn->query(
        "CREATE TABLE IF NOT EXISTS job_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            student_id INT NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(32) DEFAULT 'submitted',
            UNIQUE KEY uniq_job_student (job_id, student_id),
            KEY idx_student (student_id),
            KEY idx_job (job_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $count = $conn->query('SELECT COUNT(*) AS c FROM jobs');
    $n = $count ? (int) $count->fetch_assoc()['c'] : 0;
    if ($n === 0) {
        $samples = [
            ['Graduate Trainee', 'TCS', 'Campus hiring for CS/IT branches. Aptitude + technical rounds.', 'Pan India', '3.5 LPA', date('Y-m-d', strtotime('+30 days'))],
            ['Systems Engineer', 'Infosys', 'Full-time role; training in Mysore.', 'Multiple', '3.6 LPA', date('Y-m-d', strtotime('+21 days'))],
            ['Software Developer', 'Tech Mahindra', 'Java/React stack. CGPA 7+ preferred.', 'Hybrid', '4.0 LPA', date('Y-m-d', strtotime('+14 days'))],
        ];
        $st = $conn->prepare(
            'INSERT INTO jobs (title, company_name, description, location, salary, deadline) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($samples as $row) {
            $st->bind_param('ssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
            $st->execute();
        }
    }
}
