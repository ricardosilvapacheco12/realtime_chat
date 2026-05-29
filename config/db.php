<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "realtime_chat";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$userTable = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($userTable);

$userLastActive = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($userLastActive && $userLastActive->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER created_at");
}

$messageTable = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    receiver_id INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($messageTable);

$messageReceiver = $conn->query("SHOW COLUMNS FROM messages LIKE 'receiver_id'");
if ($messageReceiver && $messageReceiver->num_rows === 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN receiver_id INT NULL AFTER user_id");
}
