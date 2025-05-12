<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

ob_start();

require_once 'config.php';

header('Content-Type: application/json');

// Get input data
$data = json_decode(file_get_contents('php://input'), true);
$student_id = isset($data['student_id']) ? trim($data['student_id']) : '';

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    $conn = getOracleConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Query to get all room reservations for this student
    $sql = "SELECT 
                rr.reservation_id,
                rr.room_id,
                TO_CHAR(rr.start_time, 'HH24:MI') as start_time,
                TO_CHAR(rr.end_time, 'HH24:MI') as end_time,
                TO_CHAR(rr.request_date, 'YYYY-MM-DD HH24:MI:SS') as request_date,
                rr.status,
                rr.purpose
            FROM sys.room_reservation rr
            WHERE rr.student_id = :student_id
            ORDER BY rr.request_date DESC";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":student_id", $student_id);
    oci_execute($stmt);
    
    $requests = [];
    
    while ($row = oci_fetch_assoc($stmt)) {
        $requests[] = [
            'reservation_id' => $row['RESERVATION_ID'],
            'room_id' => $row['ROOM_ID'],
            'start_time' => $row['START_TIME'],
            'end_time' => $row['END_TIME'],
            'request_date' => $row['REQUEST_DATE'],
            'status' => $row['STATUS'],
            'purpose' => $row['PURPOSE']
        ];
    }
    
    echo json_encode(['success' => true, 'requests' => $requests]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        oci_free_statement($stmt);
    }
    if (isset($conn)) {
        oci_close($conn);
    }
    ob_end_flush();
}
?> 