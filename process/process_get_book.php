<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['book_id'])) {
        throw new Exception('Book ID is required');
    }

    $book_id = $_GET['book_id'];
    
    $conn = getOracleConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }

    $sql = "SELECT 
                reference_id as book_id,
                title,
                quality as condition,
                NVL(branch, 'Main Library') as branch
            FROM sys.books 
            WHERE reference_id = :book_id";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }

    oci_bind_by_name($stmt, ":book_id", $book_id);
    
    if (!oci_execute($stmt)) {
        throw new Exception("Failed to execute query: " . oci_error($stmt)['message']);
    }

    $row = oci_fetch_assoc($stmt);
    if (!$row) {
        throw new Exception("Book not found");
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'book_id' => $row['BOOK_ID'],
            'title' => $row['TITLE'],
            'condition' => $row['CONDITION'],
            'branch' => $row['BRANCH']
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