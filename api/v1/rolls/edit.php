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

if (
    empty($data['roll_name']) ||
    empty($data['restaurant_name']) ||
    !isset($data['rating']) ||
    !is_array($data['ingredients'])
) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$rollName = trim($data['roll_name']);
$restaurantName = trim($data['restaurant_name']);
$rating = (float)$data['rating'];
$notes = isset($data['notes']) ? trim($data['notes']) : '';
$ingredients = $data['ingredients'];

$createdAt = time();
$updatedAt = time();

// Step 3: Insert new roll entry
$stmt = $mysqli->prepare("
    INSERT INTO rolls (user_id, roll_name, restaurant_name, rating, notes, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("issdsii", $userId, $rollName, $restaurantName, $rating, $notes, $createdAt, $updatedAt);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert roll: " . $stmt->error]);
    exit;
}

$newRollId = $stmt->insert_id;
$stmt->close();

// Step 4: Handle ingredients
foreach ($ingredients as $ingredientName) {
    $ingredientName = trim(strtolower($ingredientName)); // normalize

    if (empty($ingredientName)) {
        continue;
    }

    // Check if ingredient tag already exists
    $ingredientStmt = $mysqli->prepare("
        SELECT id FROM ingredient_tags WHERE name = ?
    ");
    $ingredientStmt->bind_param("s", $ingredientName);
    $ingredientStmt->execute();
    $ingredientResult = $ingredientStmt->get_result();

    if ($ingredientResult->num_rows > 0) {
        $ingredientRow = $ingredientResult->fetch_assoc();
        $ingredientTagId = (int)$ingredientRow['id'];
    } else {
        // Create new ingredient tag
        $insertTagStmt = $mysqli->prepare("
            INSERT INTO ingredient_tags (name, created_by_user_id, created_at)
            VALUES (?, ?, ?)
        ");
        $insertTagStmt->bind_param("sii", $ingredientName, $userId, $createdAt);
        if ($insertTagStmt->execute()) {
            $ingredientTagId = $insertTagStmt->insert_id;
        } else {
            $ingredientTagId = null;
        }
        $insertTagStmt->close();
    }

    $ingredientStmt->close();

    if ($ingredientTagId) {
        // Insert roll_ingredient link
        $linkStmt = $mysqli->prepare("
            INSERT INTO roll_ingredients (roll_id, ingredient_tag_id)
            VALUES (?, ?)
        ");
        $linkStmt->bind_param("ii", $newRollId, $ingredientTagId);
        $linkStmt->execute();
        $linkStmt->close();
    }
}

// Step 5: Final output
echo json_encode([
    "success" => true,
    "message" => "Roll re-logged successfully",
    "new_roll_id" => $newRollId
]);
?>
