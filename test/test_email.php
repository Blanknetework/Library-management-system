<?php
require_once 'config/mailer.php';

// Test data
$studentEmail = 'adamnuevo28@gmail.com'; // The email we added earlier
$studentName = 'Adam Nuevo III. R.';
$bookTitle = 'Database Management Systems';
$dueDate = 'Apr 29, 2025';

// Try to send the email
$result = sendOverdueNotification($studentEmail, $studentName, $bookTitle, $dueDate);

// Display the result
if ($result['success']) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Error: " . $result['message'];
} 