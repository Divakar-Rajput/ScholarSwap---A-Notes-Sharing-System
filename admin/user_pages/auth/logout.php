<?php
session_start();
require_once("../../config/connection.php");
if (!empty($_COOKIE['remember_me'])) {
    $token_hash = hash('sha256', $_COOKIE['remember_me']);

    $stmt = $conn->prepare("
        DELETE FROM remember_tokens WHERE token_hash = :token_hash
    ");
    $stmt->execute([':token_hash' => $token_hash]);

    setcookie('remember_me', '', time() - 3600, '/');
}

$_SESSION = [];
session_destroy();

header("Location: http://localhost/ScholarSwap/login.html?s=logout");
exit;