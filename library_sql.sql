CREATE DATABASE library_db;
USE library_db;

CREATE TABLE users (
  uid INT AUTO_INCREMENT PRIMARY KEY,
  uname VARCHAR(24) NOT NULL UNIQUE,
  pwd VARCHAR(255) NOT NULL,
  fname VARCHAR(32),
  lname VARCHAR(32),
  email VARCHAR(64) UNIQUE,
  role ENUM('student','librarian') DEFAULT 'student'
);

CREATE TABLE books (
  bid INT AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(20),
  bname VARCHAR(128) NOT NULL,
  author VARCHAR(64),
  available BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE checkout (
  cid INT AUTO_INCREMENT PRIMARY KEY,
  uid INT NOT NULL,
  bid INT NOT NULL,
  checkout_date DATE NOT NULL,
  due_date DATE,
  returned_at DATE NULL,
  FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
  FOREIGN KEY (bid) REFERENCES books(bid) ON DELETE CASCADE,
  INDEX (uid),
  INDEX (bid)
);

CREATE USER 'library_user'@'localhost' IDENTIFIED BY 'Password123!';

INSERT INTO users (uname, pwd, fname, lname, email, role)
VALUES ('librarian', '$2y$10$uwQQ6q1y8AqsXz/moo7jAu8OlYs47kK4vgBz5jj7P0PTmir37cZHa', 'Lee', 'Brarian', 'lib@school.edu', 'librarian');
