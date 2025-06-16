<?php
// auth2.php - Enhanced Authentication Script
require_once 'config.php';

// Enable strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_auth_errors.log');

// Additional security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method from IP: " . $_SERVER['REMOTE_ADDR']);
    header("HTTP/1.1 405 Method Not Allowed");
    exit("Invalid request method");
}

// Validate CSRF token if implemented
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed for IP: " . $_SERVER['REMOTE_ADDR']);
    header("Location: index.html?error=Invalid request");
    exit();
}

// Get and sanitize input
$username = trim($conn->real_escape_string($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember']);

// Input validation
if (empty($username) || empty($password)) {
    error_log("Empty credentials from IP: " . $_SERVER['REMOTE_ADDR']);
    header("Location: index.html?error=Username and password are required");
    exit();
}

// Account lockout check
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$attempts = $stmt->get_result()->fetch_assoc();

if ($attempts && $attempts['attempts'] >= 5 && time() - strtotime($attempts['last_attempt']) < 300) {
    error_log("Account lockout triggered for IP: $ip");
    header("Location: index.html?error=Too many failed attempts. Please try again later.");
    exit();
}

// Find user with prepared statement
$stmt = $conn->prepare("SELECT id, username, password, is_admin, is_banned, last_login FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    logFailedAttempt($conn, $ip);
    error_log("User not found: $username from IP: $ip");
    header("Location: index.html?error=Invalid username or password");
    exit();
}

$user = $result->fetch_assoc();

// Check if user is banned
if ($user['is_banned']) {
    error_log("Banned user attempt: $username from IP: $ip");
    header("Location: index.html?error=Your account has been suspended");
    exit();
}

// Verify password with timing-safe comparison
if (!password_verify($password, $user['password'])) {
    logFailedAttempt($conn, $ip);
    error_log("Failed login for user: $username from IP: $ip");
    header("Location: index.html?error=Invalid username or password");
    exit();
}

// Check if password needs rehashing (for when algorithm changes)
if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $conn->query("UPDATE users SET password = '$newHash' WHERE id = {$user['id']}");
}

// Successful login - reset attempts
$conn->query("DELETE FROM login_attempts WHERE ip = '$ip'");

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['is_admin'] = $user['is_admin'];
$_SESSION['last_login'] = $user['last_login'];
$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
$_SESSION['ip'] = $ip;

// Update last login
$conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");

// Remember me functionality
if ($remember_me) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + 60 * 60 * 24 * 30; // 30 days
    
    $stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, token, expires) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $user['id'], $token, date('Y-m-d H:i:s', $expiry));
    $stmt->execute();
    
    setcookie('remember_token', $token, $expiry, '/', '', true, true);
}

// Redirect to chat
header("Location: chat.php");
exit();

function logFailedAttempt($conn, $ip) {
    $conn->query("INSERT INTO login_attempts (ip, attempts, last_attempt) 
                 VALUES ('$ip', 1, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 attempts = attempts + 1, last_attempt = NOW()");
}
