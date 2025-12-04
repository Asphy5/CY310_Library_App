<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

// Clear session data
logout_user();

// Destroy the session
session_unset();
session_destroy();

// Redirect back to the login page
header("Location: /index.php?logged_out=1");
exit;
?>