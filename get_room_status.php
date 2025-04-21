<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Start output buffering to catch any unwanted output
ob_start();

require_once 'config.php';

// Clear any output that might have occurred during config include
ob_clean();

header('Content-Type: application/json');

try {
    // Set timezone to match Oracle
    date_default_timezone_set('Asia/Manila');
    
    // Get current date and time from Oracle
    $time_sql = "SELECT 
        TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD HH24:MI:SS.FF TZR') as oracle_time,
        TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD HH24:MI:SS') as current_time_str,
        SYSTIMESTAMP as current_timestamp
    FROM DUAL";
    
    $time_stmt = executeOracleQuery($time_sql);
    if (!$time_stmt) {
        throw new Exception('Failed to execute time query: ' . print_r(oci_error(), true));
    }
    
    $oracle_time = null;
    $current_time_str = null;
    if ($time_row = oci_fetch_assoc($time_stmt)) {
        $oracle_time = $time_row['ORACLE_TIME'];
        $current_time_str = $time_row['CURRENT_TIME_STR'];
        error_log("Oracle server time: " . $oracle_time);
        error_log("Current time string: " . $current_time_str);
    } else {
        throw new Exception('Failed to fetch time data: ' . print_r(oci_error($time_stmt), true));
    }
    
    // Query to get all room reservations that are currently active or upcoming
    $sql = "WITH current_time AS (
        SELECT 
            SYSTIMESTAMP AS now,
            TRUNC(SYSTIMESTAMP) as today_start,
            TRUNC(SYSTIMESTAMP) + INTERVAL '1' DAY - INTERVAL '1' SECOND as today_end
        FROM DUAL
    )
    SELECT DISTINCT 
           room_reservation.room_id, 
           room_reservation.reservation_id,
           room_reservation.student_id,
           room_reservation.start_time as original_start_time,
           TO_CHAR(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as start_time,
           TO_CHAR(CAST(room_reservation.end_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as end_time,
           TO_CHAR(current_time.now, 'YYYY-MM-DD HH24:MI:SS') as current_time,
           CASE 
               WHEN CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' <= current_time.now 
                    AND CAST(room_reservation.end_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' > current_time.now 
               THEN 'ACTIVE'
               WHEN CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' > current_time.now 
                    AND TRUNC(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila') = TRUNC(current_time.now)
               THEN 'UPCOMING_TODAY'
               WHEN TRUNC(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila') > TRUNC(current_time.now)
               THEN 'FUTURE'
               ELSE 'PAST'
           END as reservation_status
    FROM room_reservation
    CROSS JOIN current_time
    WHERE (
        -- Active reservations (happening right now)
        (CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' <= current_time.now 
         AND CAST(room_reservation.end_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' > current_time.now)
        OR
        -- Today's upcoming reservations
        (CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' > current_time.now 
         AND TRUNC(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila') = TRUNC(current_time.now))
        OR
        -- Today's past reservations
        (TRUNC(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila') = TRUNC(current_time.now) 
         AND CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila' < current_time.now)
        OR
        -- Future reservations (within the next 7 days)
        (TRUNC(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila') > TRUNC(current_time.now)
         AND TRUNC(CAST(room_reservation.start_time AS TIMESTAMP) AT TIME ZONE 'Asia/Manila') <= TRUNC(current_time.now) + 7)
    )
    ORDER BY original_start_time";
    
    error_log("Executing room status query: " . $sql);
    
    $stmt = executeOracleQuery($sql);
    if (!$stmt) {
        throw new Exception('Failed to execute room status query: ' . print_r(oci_error(), true));
    }
    
    $occupied_rooms = [];
    $active_reservations = [];
    $upcoming_reservations = [];
    $all_reservations = [];
    
    while ($row = oci_fetch_assoc($stmt)) {
        if (!$row) {
            error_log('Warning: Failed to fetch row: ' . print_r(oci_error($stmt), true));
            continue;
        }
        
        $room_id = intval($row['ROOM_ID']);
        $reservation_data = [
            'room_id' => $room_id,
            'reservation_id' => $row['RESERVATION_ID'],
            'student_id' => $row['STUDENT_ID'],
            'start_time' => $row['START_TIME'],
            'end_time' => $row['END_TIME'],
            'current_time' => $row['CURRENT_TIME'],
            'status' => $row['RESERVATION_STATUS']
        ];
        
        $all_reservations[] = $reservation_data;
        
        if ($row['RESERVATION_STATUS'] === 'ACTIVE') {
            $occupied_rooms[] = $room_id;
            $active_reservations[] = $reservation_data;
            error_log("Room {$room_id} is ACTIVE: Start={$row['START_TIME']}, End={$row['END_TIME']}, Current={$row['CURRENT_TIME']}");
        } elseif ($row['RESERVATION_STATUS'] === 'UPCOMING_TODAY') {
            $upcoming_reservations[] = $reservation_data;
            error_log("Room {$room_id} is UPCOMING: Start={$row['START_TIME']}, End={$row['END_TIME']}, Current={$row['CURRENT_TIME']}");
        }
    }
    
    // Create status array for all rooms (1-4)
    $room_status = [];
    for ($i = 1; $i <= 4; $i++) {
        // Find all reservations for this room
        $room_reservations = array_filter($all_reservations, function($res) use ($i) {
            return $res['room_id'] === $i;
        });
        
        // Get the most relevant status for this room
        $status = 'AVAILABLE'; // Default status
        $end_time = null;
        
        foreach ($room_reservations as $res) {
            if ($res['status'] === 'ACTIVE') {
                $status = 'ACTIVE';
                $end_time = $res['end_time'];
                break;
            } elseif ($res['status'] === 'UPCOMING_TODAY' && $status !== 'ACTIVE') {
                $status = 'UPCOMING_TODAY';
                $end_time = $res['end_time'];
            } elseif ($res['status'] === 'FUTURE' && $status !== 'ACTIVE' && $status !== 'UPCOMING_TODAY') {
                $status = 'FUTURE';
                $end_time = $res['end_time'];
            }
        }
        
        $room_status[$i] = [
            'status' => $status,
            'end_time' => $end_time
        ];
        
        error_log("Setting Room {$i} status to: " . $status . ($end_time ? " (Ends at: $end_time)" : ""));
        
        // Log all reservations for this room
        if (!empty($room_reservations)) {
            error_log("All reservations for Room {$i}: " . json_encode($room_reservations));
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'room_status' => $room_status,
        'oracle_time' => $oracle_time,
        'current_time' => $current_time_str,
        'active_reservations' => $active_reservations,
        'upcoming_reservations' => $upcoming_reservations,
        'all_reservations' => $all_reservations,
        'occupied_rooms' => $occupied_rooms,
        'timestamp' => time()
    ];
    
    // Clear any buffered output before sending JSON
    ob_clean();
    
    echo json_encode($response);
    error_log("Returning room status response: " . json_encode($response));
    
} catch (Exception $e) {
    error_log("Error in get_room_status.php: " . $e->getMessage());
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
    if (isset($stmt)) oci_free_statement($stmt);
    
    // End output buffering
    ob_end_flush();
}
?> 