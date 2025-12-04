<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();
require_role('librarian');

$errors = [];
$success = '';

// Handle book creation form
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Only accept POST requests
  // Check csrf token
  if (!csrf_check($_POST['csrf'] ?? '')) {
    $errors[] = 'Invalid CSRF token.';
  } else {
    // Sanitize the inputs
    $bname = trim($_POST['bname'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $available = isset($_POST['available']) ? 1 : 0;

    // Make sure the inputs aren't null
    if ($bname === '') $errors[] = 'Title is required.';
    if (strlen($bname) > 128) $errors[] = 'Title too long (max 128).';
    if (strlen($author) > 64) $errors[] = 'Author name too long (max 64).';
    if ($isbn !== '' && strlen($isbn) > 20) $errors[] = 'ISBN too long (max 20).';

    // Insert the book if there were no errors
    if (empty($errors)) {
      try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
          INSERT INTO books (isbn, bname, author, available) 
          VALUES (:isbn, :bname, :author, :available)
        ");

        $stmt->execute([
          ':isbn' => $isbn ?: null,
          ':bname' => $bname,
          ':author' => $author ?: null,
          ':available' => $available,
        ]);

        $pdo->commit();

        // Redirect to the book list with a success message
        header('Location: books.php?msg=' . urlencode('Book added.'));
        exit;
      } catch (PDOException $e) {
        // Rollback any transactions if there was an error
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Used for debug
        // $msg = "Database error: " . $e->getMessage();

        header('Location: books.php?msg=' . urlencode('Book addition failed due to a database error.'));
        exit;
      }

      
    }
  }
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Add Book</title></head>
<body>
  <h1>Add Book</h1>
  <p><a href="books.php">← Back to books</a></p>

  <?php if ($errors): ?>
    <div style="color:red"><ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
    <label>Title (required)<br>
      <input name="bname" value="<?=htmlspecialchars($_POST['bname'] ?? '')?>" maxlength="128" required style="width:400px">
    </label><br><br>

    <label>Author<br>
      <input name="author" value="<?=htmlspecialchars($_POST['author'] ?? '')?>" maxlength="64" style="width:300px">
    </label><br><br>

    <label>ISBN<br>
      <input name="isbn" value="<?=htmlspecialchars($_POST['isbn'] ?? '')?>" maxlength="20" style="width:200px">
    </label><br><br>

    <label><input type="checkbox" name="available" <?=isset($_POST['available']) ? 'checked' : 'checked'?>> Available</label><br><br>

    <button type="submit">Add Book</button>
  </form>
</body>
</html>
