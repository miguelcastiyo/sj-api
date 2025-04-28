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

// Step 2: Validate GET params
if (empty($_GET['roll_name']) || empty($_GET['restaurant_name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing roll_name or restaurant_name"]);
    exit;
}

$rollName = trim($_GET['roll_name']);
$restaurantName = trim($_GET['restaurant_name']);

// Step 3: Fetch all matching roll entries
$stmt = $mysqli->prepare("
    SELECT 
        r.id AS roll_id,
        r.roll_name,
        r.restaurant_name,
        r.notes,
        r.rating,
        r.created_at,
        u.display_name AS created_by
    FROM rolls r
    INNER JOIN users u ON r.user_id = u.id
    WHERE r.roll_name = ? AND r.restaurant_name = ?
    ORDER BY r.created_at DESC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("ss", $rollName, $restaurantName);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "No matching roll logs found"]);
    exit;
}

$rollLogs = [];

while ($row = $result->fetch_assoc()) {
    $rollLogs[$row['roll_id']] = [
        "roll_id" => (int)$row['roll_id'],
        "notes" => $row['notes'],
        "rating" => (float)$row['rating'],
        "created_by" => $row['created_by'],
        "created_at" => (int)$row['created_at'],
        "photos" => [],
        "ingredients" => []
    ];
}

$stmt->close();

// Step 4: Attach photos to each roll log
$photoStmt = $mysqli->prepare("
    SELECT roll_id, photo_url
    FROM roll_photos
    WHERE roll_id IN (" . implode(',', array_keys($rollLogs)) . ")
");

if ($photoStmt) {
    $photoStmt->execute();
    $photoResult = $photoStmt->get_result();

    while ($photo = $photoResult->fetch_assoc()) {
        $rollId = (int)$photo['roll_id'];

        if (isset($rollLogs[$rollId])) {
            $rollLogs[$rollId]['photos'][] = $photo['photo_url'];
        }
    }

    $photoStmt->close();
}

// Step 5: Attach ingredients to each roll log
$ingredientStmt = $mysqli->prepare("
    SELECT 
        ri.roll_id,
        it.name
    FROM roll_ingredients ri
    INNER JOIN ingredient_tags it ON ri.ingredient_tag_id = it.id
    WHERE ri.roll_id IN (" . implode(',', array_keys($rollLogs)) . ") AND it.status = 1
");

if ($ingredientStmt) {
    $ingredientStmt->execute();
    $ingredientResult = $ingredientStmt->get_result();

    while ($tag = $ingredientResult->fetch_assoc()) {
        $rollId = (int)$tag['roll_id'];

        if (isset($rollLogs[$rollId])) {
            $rollLogs[$rollId]['ingredients'][] = $tag['name'];
        }
    }

    $ingredientStmt->close();
}

// Step 6: Final output
echo json_encode([
    "success" => true,
    "roll_name" => $rollName,
    "restaurant_name" => $restaurantName,
    "logs" => array_values($rollLogs)
]);
?>
