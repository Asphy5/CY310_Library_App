<?php
session_start();

// Make session cookie secure
ini_set('session.cookie_httponly', value: 1);
ini_set('session.use_strict_mode', 1);

// Database configuration
$dbhost = '127.0.0.1';
$dbname = 'library_db';
$dbuser = 'library_user';
$dbpass = 'Password123!';

// Create a database object
$dsn = "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (PDOException $e) {
  // Used for debugging only
  // die('Database connection failed: ' . $e->getMessage());
}
