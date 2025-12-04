<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
$attemptsFile = __DIR__ . '/../src/attempts.json';
$ip = $_SERVER['REMOTE_ADDR'];
$limit = 5;
$window = 600;

// Load attempt data
$attempts = [];
if (file_exists($attemptsFile)) {
    $data = file_get_contents($attemptsFile);
    $attempts = json_decode($data, true) ?: [];
}

if (!isset($attempts[$ip])) {
    $attempts[$ip] = [];
}

// Remove old attempts
$attempts[$ip] = array_filter($attempts[$ip], fn($t) => $t > time() - $window);

// Block login if too many attempts
if (count($attempts[$ip]) >= $limit) {
    die("Too many login attempts. Try again in 10 minutes.");
}

// Login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check the csrf token
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('Invalid CSRF token.');
  }
  $uname = trim($_POST['uname'] ?? '');
  $pwd = $_POST['pwd'] ?? '';

  // Attempt to login
  if (login_user($pdo, $uname, $pwd)) {
    // Clear login attempts on success
    $attempts[$ip] = [];
    file_put_contents($attemptsFile, json_encode($attempts));
    header('Location: search.php');
    exit;
  } else {
    // Record failed attempts
    $attempts[$ip][] = time();
    file_put_contents($attemptsFile, json_encode($attempts));
    $error = "Invalid username/password.";
  }
}

// Give the user a token
$csrf = csrf_token(); 
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8"><title>Library — Login</title>
<title>Login</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background: #f2f4f8;
    display: flex;
    justify-content: center;
    margin-top: 60px;
  }

  .box {
    width: 400px;
    background: white;
    padding: 100px;
    border-radius: 8px;
    box-shadow: 0 0 8px rgba(0,0,0,0.15);
  }

  h1 { text-align: center; margin-bottom: 15px; }
  label {
    display: block;
    width: 100px;
    font-weight: bold;
    margin-top: 12px;
  }
  input[type="text"]{
    width: 200px;
  }
  input[type="password"] {
    width: 175px;
  }

  .btn {
    margin-top: 15px;
    width: 100%;
    padding: 10px;
    background: #2979ff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
  }

  .btn:hover { background: #0d47a1; }
  .error {
    background: #ffe0e0;
    border-left: 4px solid #cc0000;
    padding: 10px;
    margin-bottom: 15px;
  }
</style>
</head>
<body>
  <div class="box">
    <h1>Library Login</h1>
    <?php if (!empty($error)): ?>
      <div style="color:red"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">

      <label for="uname">Username:</label>
      <input id="uname" name="uname" required>

      <label for="pwd">Password:</label>
      <input id="pwd" type="password" name="pwd" required>

      <button type="submit" class="btn">Login</button>
    </form>
    <p><a href="register.php">Register</a></p>
  </div>
</body>
</html>
