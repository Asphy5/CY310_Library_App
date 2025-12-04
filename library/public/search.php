<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_login();

$user = current_user();
$csrf = csrf_token();

$q = trim($_GET['q'] ?? '');
$msg = $_GET['msg'] ?? '';

// Page settings
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get books
if ($q === '') {
    // Default book display
    // Count the total books
    $stmt = $pdo->query("SELECT COUNT(*) FROM books");
    $totalBooks = $stmt->fetchColumn();

    // Prepare query to get books
    $stmt = $pdo->prepare("
        SELECT bid, bname AS title, author, available, isbn
        FROM books
        ORDER BY bname ASC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->execute([
        ':limit' => $limit,
        ':offset' => $offset
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // The user has searched for something
    $like = "%$q%";

    // Count the total results
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM books
        WHERE bname LIKE :b OR author LIKE :a OR isbn LIKE :i
    ");

    $stmt->execute([
        ':b' => $like,
        ':a' => $like,
        ':i' => $like
    ]);
    $totalBooks = $stmt->fetchColumn();

    // Prepare search query
    $stmt = $pdo->prepare("
        SELECT bid, bname AS title, author, available, isbn
        FROM books
        WHERE bname LIKE :b OR author LIKE :a OR isbn LIKE :i
        ORDER BY bname ASC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->execute([
        ':b' => $like,
        ':a' => $like,
        ':i' => $like,
        ':limit' => $limit,
        ':offset' => $offset
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate total pages
$totalPages = max(1, ceil($totalBooks / $limit));

// Get checked out books
$stmt = $pdo->prepare("
    SELECT c.cid, b.bid, b.bname AS title, b.author, b.isbn, c.checkout_date, c.due_date
    FROM checkout c
    JOIN books b ON c.bid = b.bid
    WHERE c.uid = :uid AND c.returned_at IS NULL
    ORDER BY c.due_date ASC
");
$stmt->execute([':uid' => $user['uid']]);
$checkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page URL helper
function page_url($p, $q) {
    return "search.php?page=$p" . ($q !== '' ? "&q=" . urlencode($q) : "");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Search Books</title>
<style>
.container { max-width: 900px; margin: auto; padding: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
.status-available { color: green; font-weight: bold; }
.status-out { color: red; font-weight: bold; }
.logout { margin-bottom: 20px; }
.admin-btn { margin-left: 20px; }
.message { padding: 10px; margin-bottom: 15px; border-left: 4px solid green; background: #e0ffe0; }
.pagination a { margin: 0 5px; text-decoration: none; }
.pagination strong { margin: 0 5px; }
form.inline { display: inline; margin: 0; }
</style>
</head>
<body>
<div class="container">
    <div class="logout">
        Logged in as <strong><?= htmlspecialchars($user['uname']) ?></strong> | 
        <a href="logout.php">Logout</a>
        <?php if ($user['role'] === 'librarian'): ?>
            <a class="admin-btn" href="admin/books.php">Admin Panel</a>
        <?php endif; ?>
    </div>
    <?php if ($msg): ?>
        <div class="message"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <h2>Search Books</h2>
    <form method="get" action="">
        <input type="text" name="q" placeholder="Search by Title, Author, or ISBN..."
               value="<?= htmlspecialchars($q) ?>">
        <button type="submit">Search</button>

        <?php if ($q !== ''): ?>
            <a href="search.php" style="margin-left: 10px;">Clear Search</a>
        <?php endif; ?>
    </form>
    <h3>
        <?php if ($q === ''): ?>
            All Books
        <?php else: ?>
            Results for: <em><?= htmlspecialchars($q) ?></em>
        <?php endif; ?>
        (<?= $totalBooks ?> found)
    </h3>
    <?php if (count($results) === 0): ?>
        <p>No books found.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($results as $book): ?>
            <tr>
                <td><?= htmlspecialchars($book['title']) ?></td>
                <td><?= htmlspecialchars($book['author']) ?></td>
                <td><?= htmlspecialchars($book['isbn']) ?></td>
                <td>
                    <?php if ($book['available'] == 1): ?>
                        <span class="status-available">Available</span>
                    <?php else: ?>
                        <span class="status-out">Checked Out</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="book.php" class="inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="bid" value="<?= htmlspecialchars($book['bid']) ?>">
                        <?php if ($book['available'] == 1): ?>
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit">Checkout</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="return">
                            <button type="submit">Return</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= page_url($page - 1, $q) ?>">&laquo; Prev</a>
            <?php endif; ?>

            <strong>Page <?= $page ?> of <?= $totalPages ?></strong>

            <?php if ($page < $totalPages): ?>
                <a href="<?= page_url($page + 1, $q) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>My Checked Out Books</h2>
    <?php if (empty($checkouts)): ?>
        <p>You have no books checked out.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Checkout Date</th>
                <th>Due Date</th>
                <th>Action</th>
            </tr>
            <?php foreach ($checkouts as $co): ?>
            <tr>
                <td><?= htmlspecialchars($co['title']) ?></td>
                <td><?= htmlspecialchars($co['author']) ?></td>
                <td><?= htmlspecialchars($co['isbn']) ?></td>
                <td><?= htmlspecialchars($co['checkout_date']) ?></td>
                <td><?= htmlspecialchars($co['due_date']) ?></td>
                <td>
                    <form method="post" action="book.php" class="inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="bid" value="<?= htmlspecialchars($co['bid']) ?>">
                        <input type="hidden" name="action" value="return">
                        <button type="submit">Return</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
