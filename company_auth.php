<?php

require_once __DIR__ . '/student_auth.php';

/**
 * @return int company user id
 */
function require_company_json(): int
{
    start_app_session();
    header('Content-Type: application/json');

    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $role = strtolower(trim((string) $_SESSION['user_role']));
    if ($role !== 'company') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Company access only']);
        exit;
    }

    return (int) $_SESSION['user_id'];
}
