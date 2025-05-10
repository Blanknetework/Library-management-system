<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Connect to Oracle
    $conn = getOracleConnection(); // Use your Oracle connection function

    if (!$conn) {
        throw new Exception("Connection failed");
    }
    
    $sql = "SELECT 
                reservation_id,
                pc_id,
                student_id,
                start_time,
                end_time,
                purpose,
                status
    FROM pc_use
            WHERE (status = 'Approved' OR status = 'Pending')
            AND (
                (start_time <= SYSDATE AND end_time >= SYSDATE)
        OR
                (TRUNC(start_time) = TRUNC(SYSDATE))
    )
            ORDER BY start_time ASC";
    
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . oci_error($conn)['message']);
    }
    if (!oci_execute($stmt)) {
        throw new Exception("Failed to execute statement: " . oci_error($stmt)['message']);
    }
    
    $active_reservations = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $status = $row['STATUS'] === 'Pending' ? 'PENDING' : 'ACTIVE';
        if (strtotime($row['START_TIME']) > time()) {
            $status = $row['STATUS'] === 'Pending' ? 'PENDING_UPCOMING' : 'UPCOMING_TODAY';
        }

        $active_reservations[] = [
            'reservation_id' => $row['RESERVATION_ID'],
            'pc_id' => $row['PC_ID'],
            'student_id' => $row['STUDENT_ID'],
            'start_time' => $row['START_TIME'],
            'end_time' => $row['END_TIME'],
            'purpose' => $row['PURPOSE'],
            'status' => $status
        ];
    }

    echo json_encode([
        'success' => true,
        'active_reservations' => $active_reservations
    ]);

    oci_free_statement($stmt);
    oci_close($conn);
    
} catch (Exception $e) {
    error_log("Error in get_pc_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 