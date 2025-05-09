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

try {
    $userId = $sessionManager->validateSession($sessionKey);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired session"]);
        exit;
    }

    //Session valid — refresh expiration
    $sessionManager->refreshSession($sessionKey);

    echo json_encode([
        "success" => true,
        "message" => "Session refreshed successfully"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error during refresh: " . $e->getMessage()]);
}
?>
