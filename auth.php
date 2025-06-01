<?php
// auth.php
session_start();
include_once 'config/database.php'; // Includes database connection and session_start()

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password']; // Get raw password for verification

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Sila masukkan nama pengguna dan kata laluan.';
        header('Location: login.php');
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, username, password, role FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit();
        } else {
            // Login failed
            $_SESSION['login_error'] = 'Nama pengguna atau kata laluan tidak sah.';
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['login_error'] = 'Ralat pangkalan data: ' . $e->getMessage();
        header('Location: login.php');
        exit();
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Logout action
    session_unset(); // Unset all session variables
    session_destroy(); // Destroy the session
    header('Location: index.php'); // Redirect to home page
    exit();
} else {
    // If accessed directly without login POST or logout GET
    header('Location: login.php');
    exit();
}
?>
