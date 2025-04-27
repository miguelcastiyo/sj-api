<?php
require_once __DIR__ . '/db.php';

class SessionManager
{
    private $db;
    private $session_lifetime_seconds = 604800; // 7 days

    public function __construct($mysqli)
    {
        $this->db = $mysqli;
    }

    // Create a new session
    public function createSession($userId)
    {
        $sessionKey = bin2hex(random_bytes(32)); // 64 characters
        $now = time();
        $expiresAt = $now + $this->session_lifetime_seconds;

        $stmt = $this->db->prepare("
            INSERT INTO sessions (session_key, user_id, created_at, expires_at, status)
            VALUES (?, ?, ?, ?, 1)
        ");

        if (!$stmt) {
            throw new Exception("Database error: " . $this->db->error);
        }

        $stmt->bind_param("siii", $sessionKey, $userId, $now, $expiresAt);

        if (!$stmt->execute()) {
            throw new Exception("Failed to create session: " . $stmt->error);
        }

        $stmt->close();

        return $sessionKey;
    }

    // Validate a session
    public function validateSession($sessionKey)
    {
        $now = time();

        $stmt = $this->db->prepare("
            SELECT user_id, status, expires_at
            FROM sessions
            WHERE session_key = ?
        ");

        if (!$stmt) {
            throw new Exception("Database error: " . $this->db->error);
        }

        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$row = $result->fetch_assoc()) {
            return false; // Session key not found
        }

        if ((int)$row['status'] !== 1) {
            return false; // Session is inactive
        }

        if ($row['expires_at'] < $now) {
            return false; // Session expired
        }

        return $row['user_id'];
    }

    // Destroy (expire) a session
    public function destroySession($sessionKey)
    {
        $stmt = $this->db->prepare("
            SELECT status
            FROM sessions
            WHERE session_key = ?
        ");

        if (!$stmt) {
            throw new Exception("Database error: " . $this->db->error);
        }

        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$row = $result->fetch_assoc()) {
            throw new Exception("Session not found");
        }

        if ((int)$row['status'] !== 1) {
            throw new Exception("Session already inactive");
        }

        $stmt->close();

        // Invalidate the session
        $updateStmt = $this->db->prepare("
            UPDATE sessions
            SET status = 0
            WHERE session_key = ?
        ");

        if (!$updateStmt) {
            throw new Exception("Database error: " . $this->db->error);
        }

        $updateStmt->bind_param("s", $sessionKey);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Get how many seconds left before session expires
    public function getSessionTimeRemaining($sessionKey)
    {
        $stmt = $this->db->prepare("
            SELECT expires_at
            FROM sessions
            WHERE session_key = ?
        ");
        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return max(0, $row['expires_at'] - time());
        }
        return null;
    }

    // Get exact expires_at timestamp
    public function getSessionExpiry($sessionKey)
    {
        $stmt = $this->db->prepare("
            SELECT expires_at
            FROM sessions
            WHERE session_key = ?
        ");
        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return (int)$row['expires_at'];
        }
        return null;
    }

    // Get exact created_at timestamp
    public function getSessionCreatedAt($sessionKey)
    {
        $stmt = $this->db->prepare("
            SELECT created_at
            FROM sessions
            WHERE session_key = ?
        ");
        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return (int)$row['created_at'];
        }
        return null;
    }

    // Get status of session (active, inactive)
    public function getSessionStatus($sessionKey)
    {
        $stmt = $this->db->prepare("
            SELECT status
            FROM sessions
            WHERE session_key = ?
        ");
        $stmt->bind_param("s", $sessionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return (int)$row['status'];
        }
        return null;
    }

    // Get full user details by user ID
    public function getUserDetails($userId)
    {
        $stmt = $this->db->prepare("
            SELECT id, email, display_name, role, joined_at
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        return null;
    }
    public function refreshSession($sessionKey)
    {
        $now = time();
        $newExpiresAt = $now + $this->session_lifetime_seconds;

        $stmt = $this->db->prepare("
            UPDATE sessions
            SET expires_at = ?
            WHERE session_key = ? AND status = 1
        ");

        if (!$stmt) {
            throw new Exception("Database error: " . $this->db->error);
        }

        $stmt->bind_param("is", $newExpiresAt, $sessionKey);
        $stmt->execute();
        $stmt->close();
    }
}
?>
