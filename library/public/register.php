<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

$csrf = csrf_token(); 

// Registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Only accept POST requests
  // Check the csrf token
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('Invalid CSRF token.');
  }

  // Sanitize the inputs
  // Make sure the inputs aren't null
  $fname = trim($_POST['fname'] ?? '');
  $lname = trim($_POST['lname'] ?? '');
  $uname = trim($_POST['uname'] ?? '');
  $pwd = $_POST['pwd'] ?? '';
  $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

  // Make sure the lengths are valid
  if (strlen($fname) < 1 || strlen($lname) < 1) {
      $error = "First and last name are required.";
  } elseif (strlen($uname) < 8 || strlen($pwd) < 8 || !$email) {
      $error = "Username, valid email, and password (>=8 chars) required.";
  } else {
    // Make sure the username and email are unique
    $stmt = $pdo->prepare("
      SELECT COUNT(*) 
      FROM users 
      WHERE uname = :u 
      OR email = :e"
    );

    $stmt->execute([':u'=>$uname, ':e'=>$email]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Username or email already exists.";
    } else {
      try {
        $pdo->beginTransaction();

        // Hash the password
        $hash = password_hash($pwd, PASSWORD_DEFAULT);

        // Insert user into the database
        $ins = $pdo->prepare("
          INSERT INTO users (fname, lname, uname, pwd, email) 
          VALUES (:fname, :lname, :uname, :pwd, :email)"
        );
        $ins->execute([
            ':fname' => $fname,
            ':lname' => $lname,
            ':uname' => $uname,
            ':pwd' => $hash,
            ':email' => $email
        ]);

        $pdo->commit();

        // Send user back to login page
        header('Location: index.php?registered=1');
        exit;
      } catch (PDOException $e) {
        // Rollback any transactions if there was an error
        if ($pdo->inTransaction()) $pdo->rollBack();

        // Used for debug
        // $msg = "Database error: " . $e->getMessage();

        $error = "Registration failed due to a database error.";
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register</title>
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

  h2 { text-align: center; margin-bottom: 15px; }
  label { display: block; margin-top: 12px; margin-bottom: 4px; font-weight: bold; }
  input[type="text"], input[type="email"], input[type="password"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #aaa;
    border-radius: 4px;
    margin-bottom: 5px;
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
  .login-link { text-align: center; margin-top: 15px; }
</style>
</head>
<body>
<div class="box">
  <h2>Create Account</h2>

  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <label for="fname">First Name</label>
    <input type="text" id="fname" name="fname" required>

    <label for="lname">Last Name</label>
    <input type="text" id="lname" name="lname" required>

    <label for="uname">Username</label>
    <input type="text" id="uname" name="uname" required minlength="8">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" required>

    <label for="pwd">Password</label>
    <input type="password" id="pwd" name="pwd" required minlength="8">

    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <button type="submit" class="btn">Register</button>
  </form>
  <div class="login-link">
    Already have an account? <a href="index.php">Log in</a>
  </div>
</div>
</body>
</html>

