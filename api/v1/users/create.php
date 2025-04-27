<?php
require_once __DIR__ . '/../../../lib/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['provider_sub'], $data['email'], $data['display_name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

$stmt = $mysqli->prepare(
    "INSERT INTO users (provider_sub, status, email, display_name, role, joined_at, auth)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit();
}

$status = 1; // active
$role = 'member';
$joined_at = time(); // Unix timestamp
$auth = 'google';

// Bind parameters
$stmt->bind_param(
    "sisssis",
    $data['provider_sub'],
    $status,
    $data['email'],
    $data['display_name'],
    $role,
    $joined_at,
    $auth
);

// Execute
if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "user_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Error creating user: " . $stmt->error]);
}

$stmt->close();
?>
