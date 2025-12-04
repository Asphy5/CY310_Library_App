<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();
require_role('librarian');

$errors = [];

// Handle the edit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Make sure the given bid is valid (>= 0)
  $bid = isset($_POST['bid']) ? intval($_POST['bid']) : 0;
  if ($bid <= 0) {
    header('Location: books.php?msg=' . urlencode('Invalid book id.'));
    exit;
  }

  // Check csrf token
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('Invalid CSRF token.');
  }

  // Sanitize the inputs
  // Make sure the inputs aren't null
  $bname = trim($_POST['bname'] ?? '');
  $author = trim($_POST['author'] ?? '');
  $isbn = trim($_POST['isbn'] ?? '');
  $available = isset($_POST['available']) ? 1 : 0;

  if ($bname === '') $errors[] = 'Title is required.';
  if (strlen($bname) > 128) $errors[] = 'Title too long (max 128).';
  
  // Try to commit the changes
  if (empty($errors)) {
    try {
      $pdo->beginTransaction();

      // Prepare the query
      $stmt = $pdo->prepare("
        UPDATE books 
        SET isbn = :isbn, bname = :bname, author = :author, available = :available 
        WHERE bid = :bid
      ");

      $stmt->execute([
        ':isbn' => $isbn ?: null,
        ':bname' => $bname,
        ':author' => $author ?: null,
        ':available' => $available,
        ':bid' => $bid,
      ]);

      $pdo->commit();

      header('Location: books.php?msg=' . urlencode('Book updated.'));
      exit;
    } catch (PDOException $e) {
      // Rollback any transactions if there was an error
      if ($pdo->inTransaction()) $pdo->rollBack();
      
      // Used for debug
      // $msg = "Database error: " . $e->getMessage();

      header('Location: books.php?msg=' . urlencode('Book edit failed due to a database error.'));
      exit;
    }
  }
}
?>

<!doctype html>
<html>
<head><meta charset="utf-8"><title>Edit Book</title></head>
<body>
  <h1>Edit Book</h1>
  <p><a href="books.php">← Back to books</a></p>

  <?php if ($errors): ?>
    <div style="color:red"><ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($_POST['csrf'])?>">
    <input type="hidden" name="bid" value="<?=htmlspecialchars($_POST['bid'])?>">

    <label>Title (required)<br>
      <input name="bname" value="<?=htmlspecialchars($_POST['bname'] ?? $book['bname'])?>" maxlength="128" required style="width:400px">
    </label><br><br>

    <label>Author<br>
      <input name="author" value="<?=htmlspecialchars($_POST['author'] ?? $book['author'])?>" maxlength="64" style="width:300px">
    </label><br><br>

    <label>ISBN<br>
      <input name="isbn" value="<?=htmlspecialchars($_POST['isbn'] ?? $book['isbn'])?>" maxlength="20" style="width:200px">
    </label><br><br>

    <label><input type="checkbox" name="available" <?= (isset($_POST['available']) ? 'checked' : ($book['available'] ? 'checked' : '')) ?>> Available</label><br><br>

    <button type="submit">Save changes</button>
  </form>
</body>
</html>
