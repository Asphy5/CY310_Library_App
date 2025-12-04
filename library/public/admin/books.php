<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();
require_role('librarian');

$csrf = csrf_token();
$msg = $_GET['msg'] ?? '';
$q = trim($_GET['q'] ?? '');

// Page settings
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Default book display
if ($q === '') {
    // Calculate total books
    $stmt = $pdo->query("SELECT COUNT(*) FROM books");
    $totalBooks = $stmt->fetchColumn();

    // Prepare query to get books
    $stmt = $pdo->prepare("
        SELECT 
            b.bid, b.isbn, b.bname, b.author, b.available,
            u.uname AS checked_out_by,
            c.due_date
        FROM books b
        LEFT JOIN checkout c 
            ON c.bid = b.bid AND c.returned_at IS NULL
        LEFT JOIN users u 
            ON u.uid = c.uid
        ORDER BY b.bname ASC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->execute([
      ':limit' => $limit,
      ':offset' => $offset
    ]);

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
        SELECT 
            b.bid, b.isbn, b.bname, b.author, b.available,
            u.uname AS checked_out_by,
            c.due_date
        FROM books b
        LEFT JOIN checkout c 
            ON c.bid = b.bid AND c.returned_at IS NULL
        LEFT JOIN users u 
            ON u.uid = c.uid
        WHERE b.bname LIKE :b OR b.author LIKE :a OR b.isbn LIKE :i
        ORDER BY b.bname ASC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->execute([
        ':b' => $like,
        ':a' => $like,
        ':i' => $like,
        ':limit' => $limit,
        ':offset' => $offset
    ]);
}

// Calculate total pages
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = max(1, ceil($totalBooks / $limit));

// Page URL Helper
function page_url($p, $q) {
    return "books.php?page=$p" . ($q !== '' ? "&q=" . urlencode($q) : "");
}
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin — Books</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: auto; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin-top: 15px; }
    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    .top { margin-bottom: 12px; }
    .pagination a { margin: 0 6px; text-decoration: none; }
    .pagination strong { margin: 0 6px; }
  </style>
</head>
<body>
  <h1>Library Admin — Books</h1>
  <p class="top">
    <a href="../search.php">Back to catalog</a> |
    <a href="add_book.php">Add new book</a> |
    <a href="../logout.php">Logout</a>
  </p>
  <?php if ($msg): ?>
    <div style="color:green; margin-bottom:10px;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="get" action="books.php" style="margin-bottom: 10px;">
    <input type="text" name="q" placeholder="Search by title, author, ISBN..."
           value="<?= htmlspecialchars($q) ?>" style="width:260px;">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?>
      <a href="books.php" style="margin-left:10px;">Clear</a>
    <?php endif; ?>
  </form>
  <h3>
    <?= $q === '' ? "All Books" : "Search Results for: <em>" . htmlspecialchars($q) . "</em>" ?>
    (<?= $totalBooks ?> found)
  </h3>
  <table>
    <thead>
      <tr>
        <th>Title</th>
        <th>Author</th>
        <th>ISBN</th>
        <th>Available</th>
        <th>Checked Out By</th>
        <th>Due Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($books)): ?>
        <tr><td colspan="7">No books found.</td></tr>
      <?php else: ?>
        <?php foreach ($books as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['bname']) ?></td>
            <td><?= htmlspecialchars($b['author']) ?></td>
            <td><?= htmlspecialchars($b['isbn']) ?></td>
            <td><?= $b['available'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($b['checked_out_by'] ?? '-') ?></td>
            <td><?= htmlspecialchars($b['due_date'] ?? '-') ?></td>
            <td>
              <form action="edit_book.php" method="post" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="bid" value="<?= htmlspecialchars($b['bid']) ?>">
                <button type="submit" style="background:none;border:none;color:blue;padding:0;cursor:pointer;text-decoration:underline;">
                    Edit
                </button>
              </form>
              |
              <form action="delete_book.php" method="post" style="display:inline"
                    onsubmit="return confirm('Delete this book?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="bid" value="<?= htmlspecialchars($b['bid']) ?>">
                <button type="submit" style="background:none;border:none;color:#c00;cursor:pointer;padding:0;">
                    Delete
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
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
</body>
</html>
