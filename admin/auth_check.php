<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once("config/connection.php");
if (!empty($_SESSION['user_id'])) {
    return; 
}
if (!empty($_COOKIE['remember_me'])) {
    $token_hash = hash('sha256', $_COOKIE['remember_me']);

    $stmt = $conn->prepare("
        SELECT u.user_id, u.role, u.is_active
        FROM remember_tokens rt
        JOIN users u ON u.user_id = rt.user_id
        WHERE rt.token_hash = :token_hash
          AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token_hash' => $token_hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && (int)$user['is_active'] === 1) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role']    = $user['role'];
        return;
    }
    setcookie('remember_me', '', time() - 3600, '/');
}

$current = urlencode($_SERVER['REQUEST_URI']);
header("Location: http://localhost/ScholarSwap/login.html?redirect=$current");
exit;