<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
if (session_destroy()) {
    // Redirect to login page
    header("Location: admin-login.php");
    exit();
} else {
    // Handle error if session destruction fails (optional)
    echo "Error logging out. Please try again.";
    // You might want to log this error
}
?> 