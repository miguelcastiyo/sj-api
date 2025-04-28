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

// Step 2: Fetch ingredients
$ingredients = [];

$stmt = $mysqli->prepare("
    SELECT id, name
    FROM ingredient_tags
    WHERE status = 1
    ORDER BY name ASC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $ingredients[] = [
        "id" => (int)$row['id'],
        "name" => $row['name']
    ];
}

$stmt->close();

// Step 3: Output
echo json_encode([
    "success" => true,
    "ingredients" => $ingredients
]);
?>
