<?php
require_once 'config.php';

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $student_id = isset($input['student_id']) ? trim($input['student_id']) : '';
    
    error_log("Checking student ID: " . $student_id);
    
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        exit;
    }
    
    // Query student details
    $sql = "SELECT student_id, full_name, course, section 
            FROM sys.students 
            WHERE student_id = :student_id";
    
    error_log("Executing SQL: " . $sql);
    
    $conn = getOracleConnection();
    if ($conn) {
        error_log("Database connection successful");
        
        $stmt = oci_parse($conn, $sql);
        if ($stmt) {
            error_log("SQL parsed successfully");
            
            oci_bind_by_name($stmt, ":student_id", $student_id);
            $result = oci_execute($stmt);
            
            if ($result) {
                error_log("Query executed successfully");
                $student = oci_fetch_assoc($stmt);
                
                if ($student) {
                    error_log("Student found: " . print_r($student, true));
                    
                    // Insert access log record
                    $access_sql = "INSERT INTO sys.login (login_id, student_id, login_time, used_facility, facility_id) 
                                 VALUES (sys.login_seq.NEXTVAL, :student_id, CURRENT_TIMESTAMP, 'N', NULL)";
                    
                    $access_stmt = oci_parse($conn, $access_sql);
                    if ($access_stmt) {
                        oci_bind_by_name($access_stmt, ":student_id", $student_id);
                        $access_result = oci_execute($access_stmt, OCI_COMMIT_ON_SUCCESS);
                        
                        if ($access_result) {
                            error_log("Access log recorded successfully for student: " . $student_id);
                        } else {
                            $e = oci_error($access_stmt);
                            error_log("Failed to record access log: " . $e['message']);
                        }
                        oci_free_statement($access_stmt);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'student' => [
                            'student_id' => $student['STUDENT_ID'],
                            'full_name' => $student['FULL_NAME'],
                            'course' => $student['COURSE'],
                            'section' => $student['SECTION']
                        ]
                    ]);
                } else {
                    error_log("No student found with ID: " . $student_id);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Student not found'
                    ]);
                }
            } else {
                $e = oci_error($stmt);
                error_log("Query execution error: " . $e['message']);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error executing query'
                ]);
            }
            oci_free_statement($stmt);
        } else {
            $e = oci_error($conn);
            error_log("SQL parse error: " . $e['message']);
            echo json_encode([
                'success' => false,
                'message' => 'Error preparing query: ' . $e['message']
            ]);
        }
        oci_close($conn);
    } else {
        error_log("Database connection failed");
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>