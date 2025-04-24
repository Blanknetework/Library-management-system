<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once '../config.php';

header('Content-Type: application/json');

try {
    // Get and decode JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['book_id'])) {
        throw new Exception('Invalid request data');
    }
    
    $book_id = $data['book_id'];
    
    // Connect to database
    $conn = getOracleConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Begin transaction
    oci_set_action($conn, 'process_book_delete');
    
    // Check if the book exists
    $book_check_sql = "SELECT quality FROM sys.books WHERE reference_id = :reference_id";
    $book_check_stmt = oci_parse($conn, $book_check_sql);
    
    if (!$book_check_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($book_check_stmt, ":reference_id", $book_id);
    oci_execute($book_check_stmt);
    
    $row = oci_fetch_assoc($book_check_stmt);
    if (!$row) {
        throw new Exception("Book not found");
    }
    
    // Check if there are active loans for this book
    $loan_check_sql = "SELECT COUNT(*) as count FROM sys.book_loans 
                       WHERE book_id = :reference_id AND return_date IS NULL";
    
    $loan_check_stmt = oci_parse($conn, $loan_check_sql);
    if (!$loan_check_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($loan_check_stmt, ":reference_id", $book_id);
    oci_execute($loan_check_stmt);
    
    $loan_row = oci_fetch_assoc($loan_check_stmt);
    if ($loan_row && $loan_row['COUNT'] > 0) {
        throw new Exception("Cannot delete book: it has active loans");
    }
    
    // Delete any facility usage records for this book
    $usage_delete_sql = "DELETE FROM sys.facility_usage 
                         WHERE facility_type = 'Book' AND facility_id = :reference_id";
    
    $usage_delete_stmt = oci_parse($conn, $usage_delete_sql);
    if (!$usage_delete_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($usage_delete_stmt, ":reference_id", $book_id);
    if (!oci_execute($usage_delete_stmt, OCI_NO_AUTO_COMMIT)) {
        throw new Exception("Failed to delete facility usage: " . oci_error($usage_delete_stmt)['message']);
    }
    
    // Delete any loan records for this book
    $loan_delete_sql = "DELETE FROM sys.book_loans WHERE book_id = :reference_id";
    
    $loan_delete_stmt = oci_parse($conn, $loan_delete_sql);
    if (!$loan_delete_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($loan_delete_stmt, ":reference_id", $book_id);
    if (!oci_execute($loan_delete_stmt, OCI_NO_AUTO_COMMIT)) {
        throw new Exception("Failed to delete loan records: " . oci_error($loan_delete_stmt)['message']);
    }
    
    // Finally, delete the book
    $book_delete_sql = "DELETE FROM sys.books WHERE reference_id = :reference_id";
    
    $book_delete_stmt = oci_parse($conn, $book_delete_sql);
    if (!$book_delete_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($book_delete_stmt, ":reference_id", $book_id);
    if (!oci_execute($book_delete_stmt, OCI_NO_AUTO_COMMIT)) {
        throw new Exception("Failed to delete book: " . oci_error($book_delete_stmt)['message']);
    }
    
    // Commit all changes
    if (!oci_commit($conn)) {
        throw new Exception("Failed to commit transaction: " . oci_error($conn)['message']);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Book successfully deleted'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if an error occurred
    if (isset($conn)) {
        oci_rollback($conn);
    }
    
    error_log("Error in process_book_delete.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Clean up resources
    if (isset($book_check_stmt)) oci_free_statement($book_check_stmt);
    if (isset($loan_check_stmt)) oci_free_statement($loan_check_stmt);
    if (isset($usage_delete_stmt)) oci_free_statement($usage_delete_stmt);
    if (isset($loan_delete_stmt)) oci_free_statement($loan_delete_stmt);
    if (isset($book_delete_stmt)) oci_free_statement($book_delete_stmt);
    if (isset($conn)) oci_close($conn);
}
?> 