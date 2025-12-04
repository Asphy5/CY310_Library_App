<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_login();

// Redirect to search if the request isn't POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: search.php');
    exit;
}

// Check csrf token
if (!csrf_check($_POST['csrf'] ?? '')) {
    die('Invalid CSRF token.');
}

$action = $_POST['action'] ?? '';
$bid = $_POST['bid'];
$uid = $_SESSION['uid'];

// Attempt to checkout or return the book
try {
    // Checkout book
    if ($action === 'checkout') {
        // Start a transaction and prepare statement
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT available 
            FROM books 
            WHERE bid = :bid 
            FOR UPDATE"
        );

        $stmt->execute([':bid' => $bid]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        // Make sure the book is actually available
        if (!$book || !$book['available']) {
            $pdo->rollBack();
            $msg = "Book not available.";
        } else {
            // Update book availability
            $upd = $pdo->prepare("
                UPDATE books 
                SET available = 0 
                WHERE bid = :bid
            ");
            $upd->execute([':bid' => $bid]);

            // Insert checkout record
            $ins = $pdo->prepare("
                INSERT INTO checkout (uid, bid, checkout_date, due_date) 
                VALUES (:uid, :bid, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY))
                ");
            $ins->execute([':uid' => $uid, ':bid' => $bid]);
            $pdo->commit(); // Commit the transaction
            $msg = "Book checked out successfully!";
        }
    } elseif ($action === 'return') { // Return book
        // Start a transaction and prepare statement
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT cid FROM checkout 
            WHERE bid = :bid AND uid = :uid 
            AND returned_at IS NULL FOR UPDATE
        ");
        $stmt->execute([':bid' => $bid, ':uid' => $uid]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);

        // Make sure there was an active checkout
        if (!$c) {
            $pdo->rollBack();
            $msg = "No active checkout found for this book.";
        } else {
            // Prepare the statements
            $upd = $pdo->prepare("
                UPDATE checkout 
                SET returned_at = CURDATE() 
                WHERE cid = :cid
                ");

            $upd->execute([':cid' => $c['cid']]);

            $u2 = $pdo->prepare("
                UPDATE books 
                SET available = 1 
                WHERE bid = :bid
                ");

            $u2->execute([':bid' => $bid]);
            $pdo->commit(); // Commit the transaction
            $msg = "Book returned successfully!";
        }
    } else { // The action was invalid
        $msg = "Invalid action.";
    }
} catch (PDOException $e) {
    // Rollback any transactions if there was an error
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Used for debug
    // $msg = "Database error: " . $e->getMessage();
}

// Redirect back to search
header('Location: search.php?msg=' . urlencode($msg));
exit;