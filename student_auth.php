<?php

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $opts = ['cookie_httponly' => true];
        if (PHP_VERSION_ID >= 70300) {
            $opts['cookie_samesite'] = 'Lax';
        }
        session_start($opts);
    }
}

/**
 * @return int student user id
 */
function require_student_json(): int
{
    start_app_session();
    header('Content-Type: application/json');

    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $role = strtolower((string) $_SESSION['user_role']);
    if ($role !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Student access only']);
        exit;
    }

    return (int) $_SESSION['user_id'];
}
