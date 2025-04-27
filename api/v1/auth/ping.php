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
    // Validate the session key
    $userId = $sessionManager->validateSession($sessionKey);

    if ($userId) {
        echo json_encode([
            "success" => true,
            "key" => $sessionKey,
            "time_remaining" => $sessionManager->getSessionTimeRemaining($sessionKey),
            "expires_at" => $sessionManager->getSessionExpiry($sessionKey),
            "created_at" => $sessionManager->getSessionCreatedAt($sessionKey),
            "status" => $sessionManager->getSessionStatus($sessionKey),
            "user" => $sessionManager->getUserDetails($userId)
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired session"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error checking session: " . $e->getMessage()]);
}
?>
