<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Start output buffering to catch any unwanted output
ob_start();

require_once '../config.php';

// Clear any output that might have occurred during config include
ob_clean();

header('Content-Type: application/json');

try {
    // Get and decode JSON input
    $json = file_get_contents('php://input');
    error_log("Received JSON input: " . $json);
    
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }

    // Validate required fields
    if (empty($data['room_id']) || empty($data['student_id']) || 
        empty($data['purpose']) || empty($data['start_time']) || empty($data['end_time'])) {
        throw new Exception('Missing required fields');
    }

    // Set timezone to match Oracle
    date_default_timezone_set('Asia/Manila');
    
    error_log("Processing room reservation request: " . print_r($data, true));
    
    // Get current date and time from Oracle
    $time_sql = "SELECT 
        TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD HH24:MI:SS.FF TZR') as oracle_time,
        TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD') as current_date,
        TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD HH24:MI:SS') as current_time_str,
        SYSTIMESTAMP as current_timestamp
    FROM DUAL";
    
    $time_stmt = executeOracleQuery($time_sql);
    if (!$time_stmt) {
        throw new Exception('Failed to execute time query');
    }
    
    $time_row = oci_fetch_assoc($time_stmt);
    if (!$time_row) {
        throw new Exception('Failed to fetch time data');
    }
    
    $current_date = $time_row['CURRENT_DATE'];
    error_log("Current date from Oracle: " . $current_date);
    error_log("Start time from request: " . $data['start_time']);
    error_log("End time from request: " . $data['end_time']);
    
    // Parse start and end times
    $start_time = $current_date . ' ' . $data['start_time'];
    $end_time = $current_date . ' ' . $data['end_time'];
    
    error_log("Combined start time: " . $start_time);
    error_log("Combined end time: " . $end_time);
    
    // Check if room is already in use for the requested time period
    $check_sql = "WITH current_time AS (
        SELECT SYSTIMESTAMP AS now FROM DUAL
    )
    SELECT COUNT(*) as count 
    FROM room_reservation 
    CROSS JOIN current_time
    WHERE room_id = :room_id 
    AND (
        (CAST(TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI') AS TIMESTAMP) <= end_time
         AND CAST(TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI') AS TIMESTAMP) >= start_time)
    )";

    error_log("Executing check query with params - Room ID: {$data['room_id']}, Start: $start_time, End: $end_time");
    
    $check_stmt = executeOracleQuery($check_sql, [
        ':room_id' => $data['room_id'],
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ]);
    
    if (!$check_stmt) {
        throw new Exception('Failed to check room availability');
    }
    
    $row = oci_fetch_assoc($check_stmt);
    if ($row['COUNT'] > 0) {
        throw new Exception('Room is already reserved for this time period');
    }

    // Insert the reservation
    $insert_sql = "INSERT INTO room_reservation (
                      room_id, 
                      student_id, 
                      start_time, 
                      end_time, 
                      purpose
                  ) VALUES (
                      :room_id,
                      :student_id,
                      CAST(TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI') AS TIMESTAMP),
                      CAST(TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI') AS TIMESTAMP),
                      :purpose
                  )";

    error_log("Executing insert query with params: " . print_r([
        'room_id' => $data['room_id'],
        'student_id' => $data['student_id'],
        'start_time' => $start_time,
        'end_time' => $end_time,
        'purpose' => $data['purpose']
    ], true));

    $insert_stmt = executeOracleQuery($insert_sql, [
        ':room_id' => $data['room_id'],
        ':student_id' => $data['student_id'],
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':purpose' => $data['purpose']
    ]);
    
    if (!$insert_stmt) {
        throw new Exception('Failed to insert reservation');
    }

    // Get the stored times
    $select_sql = "SELECT 
                      TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') as stored_start_time,
                      TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') as stored_end_time
                   FROM room_reservation 
                   WHERE room_id = :room_id 
                   AND student_id = :student_id 
                   AND start_time = CAST(TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI') AS TIMESTAMP)
                   AND end_time = CAST(TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI') AS TIMESTAMP)";

    $select_stmt = executeOracleQuery($select_sql, [
        ':room_id' => $data['room_id'],
        ':student_id' => $data['student_id'],
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ]);

    if (!$select_stmt) {
        throw new Exception('Failed to fetch stored times');
    }

    $row = oci_fetch_assoc($select_stmt);
    if (!$row) {
        throw new Exception('Failed to retrieve stored reservation times');
    }

    // Prepare success response with stored times
    $response = [
        'success' => true,
        'message' => 'Room reservation successful',
        'data' => [
            'stored_start_time' => $row['STORED_START_TIME'],
            'stored_end_time' => $row['STORED_END_TIME']
        ]
    ];
    
    // Log the successful reservation
    error_log("Room reservation successful: Room {$data['room_id']} reserved from {$row['STORED_START_TIME']} to {$row['STORED_END_TIME']}");
    
    // Clear any buffered output before sending JSON
    ob_clean();
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in process_room_reservation.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear any buffered output before sending error JSON
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Free statements
    if (isset($time_stmt)) oci_free_statement($time_stmt);
    if (isset($check_stmt)) oci_free_statement($check_stmt);
    if (isset($insert_stmt)) oci_free_statement($insert_stmt);
    if (isset($select_stmt)) oci_free_statement($select_stmt);
    
    // End output buffering
    ob_end_flush();
}
?> 