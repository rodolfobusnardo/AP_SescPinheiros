<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header('Location: index.php?error=emptyfields');
        exit();
    }

    // Additional validation: username length
    if (strlen($username) > 255) {
        header('Location: index.php?error=usernametoolong');
        exit();
    }
    if (strlen($username) < 3) { // Example minimum length
        header('Location: index.php?error=usernametooshort');
        exit();
    }

    // REVERTED SQL to NOT include is_donation_approver
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        // Handle error, e.g., log it or redirect with a generic error
        error_log("SQL Prepare Error in login_handler.php: " . $conn->error);
        header('Location: index.php?error=sqlerror');
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            // REMOVED: Line storing is_donation_approver status in session
            // $_SESSION['is_donation_approver'] = (bool)$user['is_donation_approver'];
            header('Location: home.php'); // Redirect to a home page
            exit();
        } else {
            header('Location: index.php?error=wrongpassword');
            exit();
        }
    } else {
        header('Location: index.php?error=nouser');
        exit();
    }

    $stmt->close();
} else {
    // Not a POST request, redirect to login page or show an error
    header('Location: index.php');
    exit();
}

$conn->close();
?>
