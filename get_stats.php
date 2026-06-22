<?php
header('Content-Type: application/json');

// You can later fetch this from database (MySQL)
$data = [
    "years" => ["2025-26", "2024-25", "2023-24"],
    "highest" => [6.11, 8, 10],
    "average" => [2.30, 6, 13.04]
];

echo json_encode($data);
?>