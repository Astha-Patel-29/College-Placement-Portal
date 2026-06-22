<?php
// Database configuration and connection helper

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$DB_NAME = getenv('DB_NAME') ?: 'college_placement_portal';
// XAMPP in this setup uses port 3307; we still keep fallbacks.
$DB_PORT = (int)(getenv('DB_PORT') ?: 3307);

function get_db_connection(): mysqli {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    mysqli_report(MYSQLI_REPORT_OFF);

    $hosts = array_values(array_unique([$DB_HOST, '127.0.0.1', 'localhost']));
    $ports = array_values(array_unique([(int)$DB_PORT, 3307, 3306]));
    $lastErr = '';

    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            $conn = @new mysqli($host, $DB_USER, $DB_PASS, $DB_NAME, $port);
            if (!$conn->connect_error) {
                $conn->set_charset('utf8mb4');
                return $conn;
            }
            $lastErr = "host={$host} port={$port}: " . $conn->connect_error;
        }
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Start MySQL in XAMPP and ensure database "college_placement_portal" exists. (' . $lastErr . ')',
    ]);
    exit;
}

?>