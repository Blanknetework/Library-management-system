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
    
    // Query to get all PC reservations that are currently active
    $sql = "WITH current_time AS (
        SELECT 
            SYSTIMESTAMP AS now,
            TRUNC(SYSTIMESTAMP) as today_start,
            TRUNC(SYSTIMESTAMP) + INTERVAL '1' DAY - INTERVAL '1' SECOND as today_end
        FROM DUAL
    )
    SELECT DISTINCT 
           pc_use.pc_id, 
           pc_use.reservation_id,
           pc_use.student_id,
           pc_use.start_time as original_start_time,
           TO_CHAR(pc_use.start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time,
           TO_CHAR(pc_use.end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time,
           TO_CHAR(current_time.now, 'YYYY-MM-DD HH24:MI:SS') as current_time,
           CASE 
               WHEN pc_use.start_time <= current_time.now AND pc_use.end_time > current_time.now 
               THEN 'ACTIVE'
               WHEN pc_use.start_time > current_time.now AND TRUNC(pc_use.start_time) = TRUNC(current_time.now)
               THEN 'UPCOMING_TODAY'
               WHEN TRUNC(pc_use.start_time) > TRUNC(current_time.now)
               THEN 'FUTURE'
               ELSE 'PAST'
           END as reservation_status
    FROM pc_use
    CROSS JOIN current_time
    WHERE (
        -- Active reservations (happening right now)
        (pc_use.start_time <= current_time.now AND pc_use.end_time > current_time.now)
        OR
        -- Today's upcoming reservations
        (pc_use.start_time > current_time.now AND TRUNC(pc_use.start_time) = TRUNC(current_time.now))
        OR
        -- Today's past reservations
        (TRUNC(pc_use.start_time) = TRUNC(current_time.now) AND pc_use.start_time < current_time.now)
    )
    ORDER BY original_start_time";
    
    error_log("Executing PC status query: " . $sql);
    
    $stmt = executeOracleQuery($sql);
    if (!$stmt) {
        throw new Exception('Failed to execute PC status query: ' . print_r(oci_error(), true));
    }
    
    $occupied_pcs = [];
    $active_reservations = [];
    $upcoming_reservations = [];
    $all_reservations = [];
    
    while ($row = oci_fetch_assoc($stmt)) {
        if (!$row) {
            error_log('Warning: Failed to fetch row: ' . print_r(oci_error($stmt), true));
            continue;
        }
        
        $pc_id = intval($row['PC_ID']);
        $reservation_data = [
            'pc_id' => $pc_id,
            'reservation_id' => $row['RESERVATION_ID'],
            'student_id' => $row['STUDENT_ID'],
            'start_time' => $row['START_TIME'],
            'end_time' => $row['END_TIME'],
            'current_time' => $row['CURRENT_TIME'],
            'status' => $row['RESERVATION_STATUS']
        ];
        
        $all_reservations[] = $reservation_data;
        
        if ($row['RESERVATION_STATUS'] === 'ACTIVE') {
            $occupied_pcs[] = $pc_id;
            $active_reservations[] = $reservation_data;
            error_log("PC {$pc_id} is ACTIVE: Start={$row['START_TIME']}, End={$row['END_TIME']}, Current={$row['CURRENT_TIME']}");
        } elseif ($row['RESERVATION_STATUS'] === 'UPCOMING_TODAY') {
            $upcoming_reservations[] = $reservation_data;
            error_log("PC {$pc_id} is UPCOMING: Start={$row['START_TIME']}, End={$row['END_TIME']}, Current={$row['CURRENT_TIME']}");
        }
    }
    
    // Create status array for all PCs (1-19)
    $pc_status = [];
    for ($i = 1; $i <= 19; $i++) {
        $is_occupied = in_array($i, $occupied_pcs);
        $pc_status[$i] = $is_occupied;
        error_log("Setting PC {$i} status to: " . ($is_occupied ? "occupied" : "available"));
        
        // Log all reservations for this PC
        $pc_reservations = array_filter($all_reservations, function($res) use ($i) {
            return $res['pc_id'] === $i;
        });
        if (!empty($pc_reservations)) {
            error_log("All reservations for PC {$i}: " . json_encode($pc_reservations));
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'pc_status' => $pc_status,
        'oracle_time' => $oracle_time,
        'current_time' => $current_time_str,
        'active_reservations' => $active_reservations,
        'upcoming_reservations' => $upcoming_reservations,
        'all_reservations' => $all_reservations,
        'occupied_pcs' => $occupied_pcs,
        'timestamp' => time()
    ];
    
    // Clear any buffered output before sending JSON
    ob_clean();
    
    echo json_encode($response);
    error_log("Returning PC status response: " . json_encode($response));
    
} catch (Exception $e) {
    error_log("Error in get_pc_status.php: " . $e->getMessage());
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