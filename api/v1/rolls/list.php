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

// Step 2: Group rolls by roll_name and restaurant_name
$rolls = [];

$stmt = $mysqli->prepare("
    SELECT 
        roll_name,
        restaurant_name,
        AVG(rating) AS avg_rating,
        COUNT(*) AS ratings_count,
        MAX(updated_at) AS last_updated_at
    FROM rolls
    GROUP BY roll_name, restaurant_name
    ORDER BY last_updated_at DESC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $key = strtolower(trim($row['roll_name'])) . '|' . strtolower(trim($row['restaurant_name']));

    $rolls[$key] = [
        "roll_name" => $row['roll_name'],
        "restaurant_name" => $row['restaurant_name'],
        "avg_rating" => round((float)$row['avg_rating'], 2),
        "ratings_count" => (int)$row['ratings_count'],
        "last_updated_at" => (int)$row['last_updated_at'],
        "thumb_url" => null,
        "tags" => []
    ];
}

$stmt->close();

// Step 3: Fetch all roll IDs to find thumbnails and tags
$rollIdMapping = [];

$rollIdStmt = $mysqli->prepare("
    SELECT id, roll_name, restaurant_name
    FROM rolls
");

if ($rollIdStmt) {
    $rollIdStmt->execute();
    $rollIdResult = $rollIdStmt->get_result();

    while ($row = $rollIdResult->fetch_assoc()) {
        $key = strtolower(trim($row['roll_name'])) . '|' . strtolower(trim($row['restaurant_name']));
        if (!isset($rollIdMapping[$key])) {
            $rollIdMapping[$key] = [];
        }
        $rollIdMapping[$key][] = (int)$row['id'];
    }

    $rollIdStmt->close();
}

// Step 4: Attach thumbnail photos
$photoStmt = $mysqli->prepare("
    SELECT roll_id, photo_url
    FROM roll_photos
");

if ($photoStmt) {
    $photoStmt->execute();
    $photoResult = $photoStmt->get_result();

    while ($photo = $photoResult->fetch_assoc()) {
        $rollId = (int)$photo['roll_id'];

        foreach ($rollIdMapping as $key => $ids) {
            if (in_array($rollId, $ids)) {
                if (!$rolls[$key]['thumb_url']) {
                    $rolls[$key]['thumb_url'] = $photo['photo_url']; // First found photo
                }
            }
        }
    }

    $photoStmt->close();
}

// Step 5: Attach ingredient tags
$tagStmt = $mysqli->prepare("
    SELECT 
        ri.roll_id,
        it.name
    FROM roll_ingredients ri
    INNER JOIN ingredient_tags it ON ri.ingredient_tag_id = it.id
    WHERE it.status = 1
");

if ($tagStmt) {
    $tagStmt->execute();
    $tagResult = $tagStmt->get_result();

    while ($tag = $tagResult->fetch_assoc()) {
        $rollId = (int)$tag['roll_id'];
        $ingredientName = $tag['name'];

        foreach ($rollIdMapping as $key => $ids) {
            if (in_array($rollId, $ids)) {
                if (!in_array($ingredientName, $rolls[$key]['tags'])) {
                    $rolls[$key]['tags'][] = $ingredientName;
                }
            }
        }
    }

    $tagStmt->close();
}

// Step 6: Output rolls cleanly
usort($rolls, function ($a, $b) {
    return $b['last_updated_at'] - $a['last_updated_at'];
});

echo json_encode([
    "success" => true,
    "rolls" => array_values($rolls)
]);
?>
