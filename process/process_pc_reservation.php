<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once '../config.php';


header('Content-Type: application/json');

function handleError($errno, $errstr, $errfile, $errline) {
    $error_message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_message);
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'type' => 'error_handler'
    ]);
    exit;
}

// error handler
set_error_handler('handleError');

try {
   
    date_default_timezone_set('Asia/Manila');
    
   
    $json = file_get_contents('php://input');
    error_log("Received JSON input: " . $json);
    
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid request data: ' . json_last_error_msg());
    }

 
    if (!isset($data['pc_id'], $data['student_id'], $data['start_time'], $data['end_time'], $data['purpose'])) {
        throw new Exception('Missing required fields in request data');
    }

    $pc_id = $data['pc_id'];
    $student_id = $data['student_id'];
    $start_time = $data['start_time'];
    $end_time = $data['end_time'];
    $purpose = $data['purpose'];

    error_log("Processing reservation - PC: $pc_id, Student: $student_id, Start: $start_time, End: $end_time");

    if (empty($pc_id) || empty($student_id) || empty($start_time) || empty($end_time) || empty($purpose)) {
        throw new Exception('All fields are required');
    }

 
    $current_date = date('Y-m-d');
    
    
    $formatted_start = $current_date . ' ' . $start_time . ':00';
    $formatted_end = $current_date . ' ' . $end_time . ':00';

    // If end time is less than start time, it means it's for the next day
    if (strtotime($formatted_end) <= strtotime($formatted_start)) {
        $formatted_end = date('Y-m-d', strtotime('+1 day')) . ' ' . $end_time . ':00';
    }

    error_log("Formatted times - Start: $formatted_start, End: $formatted_end");

    // it will check if the PC is currently in use
    $current_check_sql = "WITH current_time AS (
        SELECT SYSTIMESTAMP AS now FROM DUAL
    )
    SELECT COUNT(*) as count
    FROM pc_use
    CROSS JOIN current_time
    WHERE pc_id = :pc_id
    AND start_time <= current_time.now
    AND end_time > current_time.now";

    $current_check_stmt = executeOracleQuery($current_check_sql, [':pc_id' => $pc_id]);
    if (!$current_check_stmt) {
        throw new Exception('Failed to check current PC status: ' . print_r(oci_error(), true));
    }

    $row = oci_fetch_assoc($current_check_stmt);
    if ($row['COUNT'] > 0) {
        throw new Exception('This PC is currently in use');
    }

    // Check if PC is already reserved for this time period
    $check_sql = "WITH new_reservation AS (
        SELECT 
            TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time,
            TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time
        FROM DUAL
    )
    SELECT COUNT(*) as count
    FROM pc_use
    CROSS JOIN new_reservation
    WHERE pc_id = :pc_id
    AND (
        (pc_use.start_time <= new_reservation.end_time AND pc_use.end_time > new_reservation.start_time)
        OR
        (new_reservation.start_time <= pc_use.end_time AND new_reservation.end_time > pc_use.start_time)
    )";

    $check_params = [
        ':pc_id' => $pc_id,
        ':start_time' => $formatted_start,
        ':end_time' => $formatted_end
    ];

    error_log("Checking for existing reservations with SQL: " . $check_sql);
    error_log("Parameters: " . print_r($check_params, true));

    $check_stmt = executeOracleQuery($check_sql, $check_params);
    if (!$check_stmt) {
        throw new Exception('Failed to check PC availability: ' . print_r(oci_error(), true));
    }
    
    $row = oci_fetch_assoc($check_stmt);
    if (!$row) {
        throw new Exception('Failed to fetch check results: ' . print_r(oci_error($check_stmt), true));
    }

    if ($row['COUNT'] > 0) {
        throw new Exception('This PC is already reserved for this time period');
    }

    // Get next reservation ID
    $max_sql = "SELECT NVL(MAX(reservation_id), 0) + 1 FROM pc_use";
    $stmt = executeOracleQuery($max_sql);
    if (!$stmt) {
        throw new Exception('Failed to generate reservation ID: ' . print_r(oci_error(), true));
    }
    
    $row = oci_fetch_array($stmt);
    if (!$row) {
        throw new Exception('Failed to fetch reservation ID: ' . print_r(oci_error($stmt), true));
    }
    
    $reservation_id = $row[0];
    error_log("Generated reservation ID: $reservation_id");

    // Insert reservation
    $sql = "INSERT INTO pc_use (
        reservation_id, 
        pc_id, 
        student_id, 
        start_time, 
        end_time, 
        purpose
    ) VALUES (
        :reservation_id,
        :pc_id,
        :student_id,
        TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI:SS'),
        TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI:SS'),
        :purpose
    )";

    $params = [
        ':reservation_id' => $reservation_id,
        ':pc_id' => $pc_id,
        ':student_id' => $student_id,
        ':start_time' => $formatted_start,
        ':end_time' => $formatted_end,
        ':purpose' => $purpose
    ];

    error_log("Inserting reservation with SQL: " . $sql);
    error_log("Parameters: " . print_r($params, true));

    $stmt = executeOracleQuery($sql, $params);
    
    if ($stmt) {
        $conn = getOracleConnection();
        if (!$conn) {
            throw new Exception('Failed to get database connection for commit: ' . print_r(oci_error(), true));
        }
        
        if (!oci_commit($conn)) {
            throw new Exception('Failed to commit transaction: ' . print_r(oci_error($conn), true));
        }
        
      
        $verify_sql = "WITH current_time AS (
            SELECT SYSTIMESTAMP AS now FROM DUAL
        )
        SELECT 
            TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time,
            TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time,
            TO_CHAR(current_time.now, 'YYYY-MM-DD HH24:MI:SS') as current_time,
            CASE 
                WHEN start_time <= current_time.now AND end_time > current_time.now 
                THEN 'ACTIVE' 
                ELSE 'INACTIVE' 
            END as status
        FROM pc_use
        CROSS JOIN current_time
        WHERE reservation_id = :reservation_id";
        
        $verify_stmt = executeOracleQuery($verify_sql, [':reservation_id' => $reservation_id]);
        if (!$verify_stmt) {
            throw new Exception('Failed to verify reservation: ' . print_r(oci_error(), true));
        }
        
        $verify_row = oci_fetch_assoc($verify_stmt);
        if (!$verify_row) {
            throw new Exception('Failed to fetch verification data: ' . print_r(oci_error($verify_stmt), true));
        }
        
        error_log("Successfully reserved PC $pc_id. Status: " . 
                 "Start: " . $verify_row['START_TIME'] . 
                 ", End: " . $verify_row['END_TIME'] . 
                 ", Current: " . $verify_row['CURRENT_TIME'] . 
                 ", Status: " . $verify_row['STATUS']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Reservation successful',
            'data' => [
                'reservation_id' => $reservation_id,
                'pc_id' => $pc_id,
                'stored_start_time' => $verify_row['START_TIME'],
                'stored_end_time' => $verify_row['END_TIME'],
                'current_time' => $verify_row['CURRENT_TIME'],
                'status' => $verify_row['STATUS']
            ]
        ]);
    } else {
        throw new Exception('Failed to insert reservation: ' . print_r(oci_error(), true));
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Error in process_pc_reservation.php: " . $error_message);
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'type' => 'exception'
    ]);
} finally {
    if (isset($stmt)) oci_free_statement($stmt);
    if (isset($check_stmt)) oci_free_statement($check_stmt);
    if (isset($verify_stmt)) oci_free_statement($verify_stmt);
    if (isset($conn)) oci_close($conn);
}
?>
