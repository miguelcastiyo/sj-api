<?php
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/session.php';

header('Content-Type: application/json');

// Step 1: Validate session
$headers = getallheaders();
$sessionKey = isset($headers['Authorization']) ? trim($headers['Authorization']) : null;

if (!$sessionKey) {
    http_response_code(401);
    echo json_encode(["error" => "Missing session key"]);
    exit;
}

$sessionManager = new SessionManager($mysqli);

$userId = $sessionManager->validateSession($sessionKey);

if (!$userId) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired session"]);
    exit;
}

// Refresh session expiration
$sessionManager->refreshSession($sessionKey);

// Step 2: Validate POST body
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['display_name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing new display name"]);
    exit;
}

$displayName = trim($data['display_name']);

// Simple validation
if (strlen($displayName) < 2 || strlen($displayName) > 50) {
    http_response_code(400);
    echo json_encode(["error" => "Display name must be between 2 and 50 characters"]);
    exit;
}

// Step 3: Update in database
$stmt = $mysqli->prepare("
    UPDATE users
    SET display_name = ?, mod_at = ?
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$now = time();
$stmt->bind_param("sii", $displayName, $now, $userId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update display name: " . $stmt->error]);
    exit;
}

$stmt->close();

// Success
echo json_encode([
    "success" => true,
    "message" => "Display name updated successfully",
    "new_display_name" => $displayName
]);
?>
