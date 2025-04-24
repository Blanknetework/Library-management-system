<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once '../config.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    $required_fields = ['student_id', 'student_name', 'course_section', 'book_title', 'book_condition', 'date_borrowed'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // Sanitize input data
    $student_id = filter_var($_POST['student_id'], FILTER_SANITIZE_STRING);
    $student_name = filter_var($_POST['student_name'], FILTER_SANITIZE_STRING);
    $course_section = filter_var($_POST['course_section'], FILTER_SANITIZE_STRING);
    $book_title = filter_var($_POST['book_title'], FILTER_SANITIZE_STRING);
    $book_condition = filter_var($_POST['book_condition'], FILTER_SANITIZE_STRING);
    $date_borrowed = filter_var($_POST['date_borrowed'], FILTER_SANITIZE_STRING);
    $branch = filter_var($_POST['branch'], FILTER_SANITIZE_STRING);
    
    // Validate branch
    if (!in_array($branch, ['Main Library', 'Batasan Library', 'SM Library'])) {
        $branch = 'Main Library'; // Default if invalid
    }
    
    // Calculate return date (1 day after borrowed date)
    $return_date = date('Y-m-d', strtotime($date_borrowed . ' + 1 day'));
    
    // Connect to database
    $conn = getOracleConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    // Begin transaction
    oci_set_action($conn, 'process_book_upload');
    
 
    $student_check_sql = "SELECT COUNT(*) as count FROM sys.students WHERE student_id = :student_id";
    $student_check_stmt = oci_parse($conn, $student_check_sql);
    if (!$student_check_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($student_check_stmt, ":student_id", $student_id);
    oci_execute($student_check_stmt);
    
    $row = oci_fetch_assoc($student_check_stmt);
    if (!$row || $row['COUNT'] == 0) {
       
        $student_insert_sql = "INSERT INTO sys.students (student_id, full_name, course) 
                               VALUES (:student_id, :full_name, :course)";
        
        $student_insert_stmt = oci_parse($conn, $student_insert_sql);
        if (!$student_insert_stmt) {
            throw new Exception("Database error: " . oci_error($conn)['message']);
        }
        
        oci_bind_by_name($student_insert_stmt, ":student_id", $student_id);
        oci_bind_by_name($student_insert_stmt, ":full_name", $student_name);
        oci_bind_by_name($student_insert_stmt, ":course", $course_section);
        
        if (!oci_execute($student_insert_stmt, OCI_NO_AUTO_COMMIT)) {
            throw new Exception("Failed to create student record: " . oci_error($student_insert_stmt)['message']);
        }
    }
    
    // Generate a unique reference ID for the book (e.g., BK-2023-00001)
    $ref_sql = "SELECT NVL(MAX(TO_NUMBER(REGEXP_SUBSTR(reference_id, '[0-9]+'))), 0) + 1 as next_id 
                FROM sys.books 
                WHERE reference_id LIKE 'BK-%'";
    
    $ref_stmt = oci_parse($conn, $ref_sql);
    if (!$ref_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_execute($ref_stmt);
    $ref_row = oci_fetch_assoc($ref_stmt);
    $next_id = $ref_row['NEXT_ID'];
    
    $reference_id = 'BK-' . date('Y') . '-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
    
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
    
    if (!oci_execute($book_insert_stmt, OCI_NO_AUTO_COMMIT)) {
        throw new Exception("Failed to insert book: " . oci_error($book_insert_stmt)['message']);
    }
    
    // Record the book loan
    $loan_sql = "INSERT INTO sys.book_loans (
                    book_id,
                    student_id,
                    borrow_date,
                    return_date
                ) VALUES (
                    :book_id,
                    :student_id,
                    TO_TIMESTAMP(:borrow_date, 'YYYY-MM-DD HH24:MI:SS'),
                    TO_TIMESTAMP(:due_date, 'YYYY-MM-DD HH24:MI:SS')
                )";
    
    $loan_stmt = oci_parse($conn, $loan_sql);
    if (!$loan_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    // Create temporary variables for concatenated strings
    $borrow_date_time = $date_borrowed . ' 00:00:00';
    $due_date_time = $return_date . ' 00:00:00';
    
    oci_bind_by_name($loan_stmt, ":book_id", $reference_id);
    oci_bind_by_name($loan_stmt, ":student_id", $student_id);
    oci_bind_by_name($loan_stmt, ":borrow_date", $borrow_date_time);
    oci_bind_by_name($loan_stmt, ":due_date", $due_date_time);
    
    if (!oci_execute($loan_stmt, OCI_NO_AUTO_COMMIT)) {
        throw new Exception("Failed to record loan: " . oci_error($loan_stmt)['message']);
    }
    
    // Record facility usage
    $usage_sql = "INSERT INTO sys.facility_usage (
                    usage_id, 
                    student_id, 
                    facility_type, 
                    facility_id, 
                    usage_date,
                    usage_time
                ) VALUES (
                    sys.facility_usage_seq.NEXTVAL,
                    :student_id,
                    'Book',
                    :book_id,
                    TO_DATE(:usage_date, 'YYYY-MM-DD'),
                    SYSTIMESTAMP
                )";
    
    $usage_stmt = oci_parse($conn, $usage_sql);
    if (!$usage_stmt) {
        throw new Exception("Database error: " . oci_error($conn)['message']);
    }
    
    oci_bind_by_name($usage_stmt, ":student_id", $student_id);
    oci_bind_by_name($usage_stmt, ":book_id", $reference_id);
    oci_bind_by_name($usage_stmt, ":usage_date", $date_borrowed);
    
    if (!oci_execute($usage_stmt, OCI_NO_AUTO_COMMIT)) {
        throw new Exception("Failed to record facility usage: " . oci_error($usage_stmt)['message']);
    }
    
    // Commit all changes
    if (!oci_commit($conn)) {
        throw new Exception("Failed to commit transaction: " . oci_error($conn)['message']);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Book successfully added and borrowed',
        'data' => [
            'reference_id' => $reference_id,
            'book_title' => $book_title,
            'student_id' => $student_id,
            'student_name' => $student_name,
            'borrow_date' => $date_borrowed,
            'return_date' => $return_date,
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
    // Clean up resources
    if (isset($student_check_stmt)) oci_free_statement($student_check_stmt);
    if (isset($student_insert_stmt)) oci_free_statement($student_insert_stmt);
    if (isset($ref_stmt)) oci_free_statement($ref_stmt);
    if (isset($book_insert_stmt)) oci_free_statement($book_insert_stmt);
    if (isset($loan_stmt)) oci_free_statement($loan_stmt);
    if (isset($usage_stmt)) oci_free_statement($usage_stmt);
    if (isset($conn)) oci_close($conn);
}
?> 