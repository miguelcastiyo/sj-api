<?php
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/session.php';

header('Content-Type: application/json');

$headers = getallheaders();
$sessionKey = isset($headers['Authorization']) ? trim($headers['Authorization']) : null;

if (!$sessionKey) {
    http_response_code(401);
    echo json_encode(["error" => "Missing session key"]);
    exit();
}

$sessionManager = new SessionManager($mysqli);

$userId = $sessionManager->validateSession($sessionKey);

if (!$userId) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired session"]);
    exit();
}

$sessionManager->refreshSession($sessionKey);

if (empty($_POST['roll_id']) || !isset($_FILES['photo_file'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing roll_id or photo file"]);
    exit();
}

$rollId = (int)$_POST['roll_id'];
$photoFile = $_FILES['photo_file'];

if ($photoFile['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "Error uploading file"]);
    exit();
}

// Save file locally (MVP)
$uploadsDir = __DIR__ . '/../../../uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

$extension = strtolower(pathinfo($photoFile['name'], PATHINFO_EXTENSION));

// Only allow certain file types (basic security)
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(["error" => "Unsupported file type"]);
    exit;
}

// Safe random filename
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$destination = $uploadsDir . $filename;

if (!move_uploaded_file($photoFile['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to save file"]);
    exit;
}

// Step 4: Insert into roll_photos table
$photoUrl = '/uploads/' . $filename;
$now = time();

$stmt = $mysqli->prepare("
    INSERT INTO roll_photos (roll_id, user_id, photo_url, created_at)
    VALUES (?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit();
}

$stmt->bind_param("iisi", $rollId, $userId, $photoUrl, $now);
$stmt->execute();
$stmt->close();

// Success
echo json_encode([
    "success" => true,
    "photo_url" => $photoUrl,
    "message" => "Photo uploaded successfully"
]);
?>
