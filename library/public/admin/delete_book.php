<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();
require_role('librarian');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check csrf token
  if (!csrf_check($_POST['csrf'] ?? '')) {
    die('Invalid CSRF token.');
  }

  // Make sure the given bid is valid (>= 0)
  $bid = isset($_POST['bid']) ? intval($_POST['bid']) : 0;
  if ($bid <= 0) {
    header('Location: books.php?msg=' . urlencode('Invalid book id.'));
    exit;
  }

  // Prepare query to check if the book is checked out
  $check = $pdo->prepare("
    SELECT COUNT(*) 
    FROM checkout 
    WHERE bid = :bid 
    AND returned_at IS NULL
  ");

  $check->execute([':bid' => $bid]);

  // Refuse to delete if the book is checked out
  if ($check->fetchColumn() > 0) {
    header('Location: books.php?msg=' . urlencode('Cannot delete: book currently checked out.'));
    exit;
  }

  // Delete the book
  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
      DELETE FROM books 
      WHERE bid = :bid
    ");

    $stmt->execute([':bid' => $bid]);

    $pdo->commit();

    header('Location: books.php?msg=' . urlencode('Book deleted.'));
    exit;
  } catch (PDOException $e) {
    // Rollback any transactions if there was an error
    if ($pdo->inTransaction()) $pdo->rollBack();

    // Used for debug
    // $msg = "Database error: " . $e->getMessage();

    header('Location: books.php?msg=' . urlencode('Book deletion failed due to a database error.'));
    exit;
  }
} else {
  header('Location: books.php?msg=' . urlencode('Book deletion failed.'));
  exit;
}




