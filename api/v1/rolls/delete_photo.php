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

$sessionManager->refreshSession($sessionKey);

// Step 2: Validate input
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['photo_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing photo_id"]);
    exit;
}

$photoId = (int)$data['photo_id'];

// Step 3: Fetch photo details
$stmt = $mysqli->prepare("
    SELECT id, user_id, photo_url
    FROM roll_photos
    WHERE id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("i", $photoId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Photo not found"]);
    exit;
}

$photo = $result->fetch_assoc();
$stmt->close();

// Step 4: Security check — does this user own this photo?
if ((int)$photo['user_id'] !== (int)$userId) {
    http_response_code(403);
    echo json_encode(["error" => "You do not have permission to delete this photo"]);
    exit;
}

// Step 5: Delete database record
$deleteStmt = $mysqli->prepare("
    DELETE FROM roll_photos WHERE id = ?
");

if (!$deleteStmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$deleteStmt->bind_param("i", $photoId);

if (!$deleteStmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete photo record"]);
    exit;
}

$deleteStmt->close();

// Step 6: Delete the physical file
$uploadsDir = __DIR__ . '/../../../uploads/';
$photoFile = $uploadsDir . basename($photo['photo_url']);

if (file_exists($photoFile)) {
    unlink($photoFile);
}

// Step 7: Output success
echo json_encode([
    "success" => true,
    "message" => "Photo deleted successfully"
]);
?>
