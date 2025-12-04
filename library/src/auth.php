<?php
require_once __DIR__ . '/config.php';

// Login using username and password
function login_user(PDO $pdo, $username, $password) {
  // Prepare and execute statement to fetch user data
  $stmt = $pdo->prepare("
    SELECT uid, uname, pwd, role 
    FROM users 
    WHERE uname = :uname LIMIT 1"
  );
  
  $stmt->execute([':uname' => $username]);
  $user = $stmt->fetch();
  
  // Verify password
  if ($user && password_verify($password, $user['pwd'])) {
    // regenerate session id
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['uid'];
    $_SESSION['uname'] = $user['uname'];
    $_SESSION['role'] = $user['role'];
    return true;
  } else{
    return false;
  }
}

// Send user to login if not logged in
function require_login() {
  if (empty($_SESSION['uid'])) {
    header('Location: /index.php');
    exit;
  }
}

// Check user's role
function require_role($role) {
  if (empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

// Return user's info
function current_user() {
  if (empty($_SESSION['uid'])) {
    return null;
  }
  return [
    'uid' => $_SESSION['uid'],
    'uname' => $_SESSION['uname'],
    'role' => $_SESSION['role']
  ];
}

// Logout user
function logout_user() {
  // Make sure a session exists then clear data
  session_start();
  $_SESSION = [];
  
  // Delete session cookie
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
  }
  session_destroy();
}

// Create csrf token
function csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

// Check csrf token
function csrf_check($token) {
  return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}
