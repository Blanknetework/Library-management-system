<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    $required_fields = ['book_id', 'book_title', 'book_condition', 'branch'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // Sanitize input data
    $book_id = filter_var($_POST['book_id'], FILTER_SANITIZE_STRING);
    $book_title = filter_var($_POST['book_title'], FILTER_SANITIZE_STRING);
    $book_condition = filter_var($_POST['book_condition'], FILTER_SANITIZE_STRING);
    $branch = filter_var($_POST['branch'], FILTER_SANITIZE_STRING);
    
    // Validate branch
    if (!in_array($branch, ['Main Library', 'Batasan Library', 'SM Library'])) {
        $branch = 'Main Library'; 
    }
    
    // Connect to database
    $conn = getOracleConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Update the book
    $sql = "UPDATE sys.books 
            SET title = :title,
                quality = :condition,
                branch = :branch
            WHERE reference_id = :book_id";
    
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($stmt, ":title", $book_title);
    oci_bind_by_name($stmt, ":condition", $book_condition);
    oci_bind_by_name($stmt, ":branch", $branch);
    oci_bind_by_name($stmt, ":book_id", $book_id);
    
    if (!oci_execute($stmt)) {
        throw new Exception("Failed to update book: " . oci_error($stmt)['message']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Book successfully updated',
        'data' => [
            'book_id' => $book_id,
            'title' => $book_title,
            'condition' => $book_condition,
            'branch' => $branch
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) oci_free_statement($stmt);
    if (isset($conn)) oci_close($conn);
}
?> 