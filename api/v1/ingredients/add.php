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

if (empty($data['name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ingredient name"]);
    exit;
}

$ingredientName = trim(strtolower($data['name']));

if (strlen($ingredientName) > 100) {
    http_response_code(400);
    echo json_encode(["error" => "Ingredient name too long"]);
    exit;
}

// Step 3: Check if ingredient already exists
$checkStmt = $mysqli->prepare("
    SELECT id FROM ingredient_tags WHERE name = ?
");
$checkStmt->bind_param("s", $ingredientName);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Ingredient already exists"]);
    exit;
}
$checkStmt->close();

// Step 4: Insert new ingredient
$createdAt = time();

$insertStmt = $mysqli->prepare("
    INSERT INTO ingredient_tags (name, created_by_user_id, created_at)
    VALUES (?, ?, ?)
");

if (!$insertStmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$insertStmt->bind_param("sii", $ingredientName, $userId, $createdAt);

if ($insertStmt->execute()) {
    echo json_encode([
        "success" => true,
        "new_ingredient_id" => $insertStmt->insert_id,
        "name" => $ingredientName
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add ingredient"]);
}

$insertStmt->close();
?>
