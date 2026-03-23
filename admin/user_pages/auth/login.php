<?php
session_start();
require_once("../../config/connection.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request");
}

$email    = trim($_POST['email']);
$password = $_POST['password'];
$redirect = $_POST['redirect'] ?? '';

$stmt = $conn->prepare("
    SELECT user_id, password_hash, role, is_active
    FROM users 
    WHERE email = :email
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    header("Location: http://localhost/ScholarSwap/login.html?s=invalid");
    exit;
}

if ((int)$user['is_active'] === 0) {
    header("Location: http://localhost/ScholarSwap/login.html?s=banned");
    exit;
}

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role']    = $user['role'];

$token = bin2hex(random_bytes(32)); 
$expires = time() + (30 * 24 * 60 * 60);

$stmt2 = $conn->prepare("
    INSERT INTO remember_tokens (user_id, token_hash, expires_at)
    VALUES (:user_id, :token_hash, :expires_at)
");
$stmt2->execute([
    ':user_id'    => $user['user_id'],
    ':token_hash' => hash('sha256', $token),
    ':expires_at' => date('Y-m-d H:i:s', $expires),
]);

setcookie('remember_me', $token, [
    'expires'  => $expires,
    'path'     => '/',
    'httponly' => true,   
    'samesite' => 'Lax',
    // 'secure' => true,  // ← uncomment when using HTTPS
]);

if ($redirect) {
    header("Location: http://localhost/ScholarSwap/login.html?s=success&r=" . urlencode($redirect));
} else {
    header("Location: http://localhost/ScholarSwap/login.html?s=success");
}
exit;