<?php
require_once __DIR__ . '/../../../lib/db.php';
require_once __DIR__ . '/../../../lib/session.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['provider_sub']) || !isset($data['auth_provider'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

$providerSub = $data['provider_sub'];
$authProvider = strtolower(trim($data['auth_provider']));

$stmt = $mysqli->prepare("
    SELECT id, status
    FROM users
    WHERE provider_sub = ? AND auth = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit();
}

$stmt->bind_param("ss", $providerSub, $authProvider);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if ($user['status'] != 1) {
        http_response_code(403);
        echo json_encode(["error" => "User is not active"]);
        exit;
    }

    $userId = $user['id'];

    $now = time();
    $updateStmt = $mysqli->prepare("
        UPDATE users SET last_login = ? WHERE id = ?
    ");
    $updateStmt->bind_param("ii", $now, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    $sessionManager = new SessionManager($mysqli);
    $sessionKey = $sessionManager->createSession($userId);

    echo json_encode([
        "success" => true,
        "session_key" => $sessionKey
    ]);
    
} else {
    http_response_code(401);
    echo json_encode(["error" => "Invalid login credentials"]);
}

$stmt->close();
?>
