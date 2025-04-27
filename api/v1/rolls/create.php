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

// Step 2: Get and validate POST data
$data = json_decode(file_get_contents('php://input'), true);

if (
    empty($data['roll_name']) ||
    empty($data['restaurant_name']) ||
    !isset($data['rating'])
) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$rollName = trim($data['roll_name']);
$restaurantName = trim($data['restaurant_name']);
$restaurantPlaceId = isset($data['restaurant_google_place_id']) ? trim($data['restaurant_google_place_id']) : null;
$rating = (float)$data['rating'];
$notes = isset($data['notes']) ? trim($data['notes']) : null;
$now = time();

// Step 3: Insert into rolls table
$stmt = $mysqli->prepare("
    INSERT INTO rolls (user_id, restaurant_name, restaurant_google_place_id, roll_name, notes, rating, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$stmt->bind_param(
    "issssdii",
    $userId,
    $restaurantName,
    $restaurantPlaceId,
    $rollName,
    $notes,
    $rating,
    $now,
    $now
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert roll: " . $stmt->error]);
    exit;
}

$rollId = $stmt->insert_id;
$stmt->close();

// Step 4: (Optional) Link ingredients if provided
if (isset($data['ingredients']) && is_array($data['ingredients'])) {
    foreach ($data['ingredients'] as $ingredientId) {
        $ingredientStmt = $mysqli->prepare("
            INSERT INTO roll_ingredients (roll_id, ingredient_tag_id)
            VALUES (?, ?)
        ");

        if ($ingredientStmt) {
            $ingredientStmt->bind_param("ii", $rollId, $ingredientId);
            $ingredientStmt->execute();
            $ingredientStmt->close();
        }
    }
}

// Step 5: Return success response
echo json_encode([
    "success" => true,
    "roll_id" => $rollId,
    "message" => "Roll logged successfully"
]);
?>
