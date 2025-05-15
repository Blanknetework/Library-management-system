<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once '../config.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    $required_fields = ['book_title', 'book_condition'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // Sanitize input data
    $book_title = filter_var($_POST['book_title'], FILTER_SANITIZE_STRING);
    $book_condition = filter_var($_POST['book_condition'], FILTER_SANITIZE_STRING);
    $branch = filter_var($_POST['branch'], FILTER_SANITIZE_STRING);
    
    // Validate branch
    if (!in_array($branch, ['Main Library', 'Batasan Library', 'SM Library'])) {
        $branch = 'Main Library'; 
    }
    
    // Current date for record keeping
    $current_date = date('Y-m-d');
    
 
    $conn = getOracleConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Begin transaction
    oci_set_action($conn, 'process_book_upload');
    

    $ref_sql = "SELECT MAX(TO_NUMBER(REPLACE(REPLACE(reference_id, 'BK-', ''), '-', ''))) as max_ref 
                FROM sys.books 
                WHERE reference_id LIKE 'BK-%'";
    
    $ref_stmt = oci_parse($conn, $ref_sql);
    if (!$ref_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_execute($ref_stmt);
    $ref_row = oci_fetch_assoc($ref_stmt);
    
   
    $next_id = ($ref_row && isset($ref_row['MAX_REF']) && $ref_row['MAX_REF']) 
               ? (int)$ref_row['MAX_REF'] + 1 
               : 1;
               
   
    $id_part = str_pad($next_id, 5, '0', STR_PAD_LEFT); 
    $reference_id = 'BK-' . $id_part; 
    
   
    error_log("Generated reference ID: " . $reference_id . " (length: " . strlen($reference_id) . ")");
    
    
    $check_ref_sql = "SELECT COUNT(*) as ref_count FROM sys.books WHERE reference_id = :reference_id";
    $check_ref_stmt = oci_parse($conn, $check_ref_sql);
    oci_bind_by_name($check_ref_stmt, ":reference_id", $reference_id);
    oci_execute($check_ref_stmt);
    $ref_count_row = oci_fetch_assoc($check_ref_stmt);
    
    if ($ref_count_row && $ref_count_row['REF_COUNT'] > 0) {
        $next_id++;
        $id_part = str_pad($next_id, 5, '0', STR_PAD_LEFT);
        $reference_id = 'BK-' . $id_part;
        error_log("ID already exists, modified to: " . $reference_id . " (length: " . strlen($reference_id) . ")");
    }
    oci_free_statement($check_ref_stmt);
    
    // Check if branch column exists
    $check_branch_sql = "SELECT column_name 
                         FROM USER_TAB_COLUMNS 
                         WHERE table_name = 'BOOKS' 
                         AND column_name = 'BRANCH'";
    
    $check_branch_stmt = oci_parse($conn, $check_branch_sql);
    if ($check_branch_stmt) {
        oci_execute($check_branch_stmt);
        $has_branch_column = (oci_fetch_assoc($check_branch_stmt) !== false);
        oci_free_statement($check_branch_stmt);
        
        if (!$has_branch_column) {
            $alter_table_sql = "ALTER TABLE sys.books ADD branch VARCHAR2(50) DEFAULT 'Main Library'";
            $alter_stmt = oci_parse($conn, $alter_table_sql);
            if ($alter_stmt) {
                oci_execute($alter_stmt);
                oci_free_statement($alter_stmt);
            }
        }
    }
    
    // Insert the book
    $author = "Unknown"; 
    
    $book_insert_sql = "INSERT INTO sys.books (
                            reference_id,
                            title,
                            author,
                            quality,
                            branch
                        ) VALUES (
                            :reference_id,
                            :title,
                            :author,
                            :quality,
                            :branch
                        )";
    
    $book_insert_stmt = oci_parse($conn, $book_insert_sql);
    if (!$book_insert_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($book_insert_stmt, ":reference_id", $reference_id);
    oci_bind_by_name($book_insert_stmt, ":title", $book_title);
    oci_bind_by_name($book_insert_stmt, ":author", $author);
    oci_bind_by_name($book_insert_stmt, ":quality", $book_condition);
    oci_bind_by_name($book_insert_stmt, ":branch", $branch);
    
    try {
        if (!oci_execute($book_insert_stmt, OCI_NO_AUTO_COMMIT)) {
            $error = oci_error($book_insert_stmt);
            error_log("Database insertion error: " . json_encode($error));
            throw new Exception("Failed to insert book: " . $error['message']);
        }
    } catch (Exception $e) {
        error_log("Exception during book insertion: " . $e->getMessage());
        throw $e;
    }
    
    // Commit all changes
    if (!oci_commit($conn)) {
        throw new Exception("Failed to commit transaction: " . oci_error($conn)['message']);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Book successfully added',
        'data' => [
            'reference_id' => $reference_id,
            'book_title' => $book_title,
            'date_added' => $current_date,
            'branch' => $branch
        ]
    ]);
    
} catch (Exception $e) {

    if (isset($conn)) {
        oci_rollback($conn);
    }
    
    error_log("Error in process_book_upload.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($ref_stmt)) oci_free_statement($ref_stmt);
    if (isset($book_insert_stmt)) oci_free_statement($book_insert_stmt);
    if (isset($conn)) oci_close($conn);
}
?> 