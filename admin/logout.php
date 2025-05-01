<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
if (session_destroy()) {
   
    header("Location: admin-login.php");
    exit();
} else {
    
    echo "Error logging out. Please try again.";
   
}
?> 