<?php
// Database Configuration
// pixora/config/database.php

// Database credentials
$servername = "localhost";
$username = "root";  // XAMPP default username
$password = "";      // XAMPP default password (empty)
$dbname = "pixora_db";

// Create connection using MySQLi (Object-Oriented)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for better character support
$conn->set_charset("utf8mb4");

// Optional: Enable error reporting (remove in production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

?>