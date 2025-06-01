<?php
// config/database.php
session_start(); // Start the session at the very beginning

$host = 'localhost';
$dbname = 'ekewpa9';
$username = 'root';
$password = 'JPNA@Password1'; // Your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connected to database successfully!"; // For debugging
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
