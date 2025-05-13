<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');


ob_start();

require_once 'config.php';


ob_clean();

header('Content-Type: application/json');

try {
    $conn = getOracleConnection();
    if ($conn) {
        $response = ['success' => true, 'active_reservations' => [], 'upcoming_reservations' => [], 'all_reservations' => [], 'pending_reservations' => [], 'todays_reservations' => []];
        
        // Get active reservations (approved and current time is between start and end time)
        $active_sql = "SELECT r.*, s.full_name 
                      FROM sys.room_reservation r
                      JOIN sys.students s ON r.student_id = s.student_id
                      WHERE UPPER(r.status) = 'APPROVED'
                      AND SYSTIMESTAMP BETWEEN r.start_time AND r.end_time";
        $active_stmt = oci_parse($conn, $active_sql);
        oci_execute($active_stmt);
        while ($row = oci_fetch_assoc($active_stmt)) {
            $response['active_reservations'][] = $row;
        }
        
        // Get upcoming reservations for today
        $upcoming_sql = "SELECT r.*, s.full_name 
                        FROM sys.room_reservation r
                        JOIN sys.students s ON r.student_id = s.student_id
                        WHERE UPPER(r.status) = 'APPROVED'
                        AND TRUNC(r.start_time) = TRUNC(SYSTIMESTAMP)
                        AND r.start_time > SYSTIMESTAMP";
        $upcoming_stmt = oci_parse($conn, $upcoming_sql);
        oci_execute($upcoming_stmt);
        while ($row = oci_fetch_assoc($upcoming_stmt)) {
            $response['upcoming_reservations'][] = $row;
        }
        
        // Get all reservations for status display
        $all_sql = "SELECT r.*, s.full_name 
                   FROM sys.room_reservation r
                   JOIN sys.students s ON r.student_id = s.student_id
                   WHERE UPPER(r.status) IN ('APPROVED', 'PENDING')
                   AND r.start_time >= SYSTIMESTAMP";
        $all_stmt = oci_parse($conn, $all_sql);
        oci_execute($all_stmt);
        while ($row = oci_fetch_assoc($all_stmt)) {
            $response['all_reservations'][] = $row;
        }
        
        // Get pending reservations (not yet approved, but for today or future)
        $pending_sql = "SELECT r.*, s.full_name 
                        FROM sys.room_reservation r
                        JOIN sys.students s ON r.student_id = s.student_id
                        WHERE UPPER(r.status) = 'PENDING'
                        AND r.start_time >= SYSTIMESTAMP - INTERVAL '15' MINUTE";
        $pending_stmt = oci_parse($conn, $pending_sql);
        oci_execute($pending_stmt);
        while ($row = oci_fetch_assoc($pending_stmt)) {
            $response['pending_reservations'][] = $row;
        }
        
        // Get all today's reservations (pending and approved)
        $todays_sql = "SELECT r.room_id, r.status, r.reservation_id, TO_CHAR(r.start_time, 'HH24:MI') as start_time, TO_CHAR(r.end_time, 'HH24:MI') as end_time
                       FROM sys.room_reservation r
                       WHERE (UPPER(r.status) = 'APPROVED' OR UPPER(r.status) = 'PENDING')
                       AND TRUNC(r.start_time) = TRUNC(SYSDATE)";
        $todays_stmt = oci_parse($conn, $todays_sql);
        oci_execute($todays_stmt);
        $todays_reservations = [];
        while ($row = oci_fetch_assoc($todays_stmt)) {
            $todays_reservations[] = $row;
        }
        $response['todays_reservations'] = $todays_reservations;
        
        // Build a status map for each room (1-5)
        $room_status_map = [];
        for ($i = 1; $i <= 5; $i++) {
            $room_status_map[$i] = 'AVAILABLE';
        }

        // Instead of querying room_status table, we'll determine status from reservations
        // Check for active reservations first (highest priority)
        foreach ($response['active_reservations'] as $res) {
            $room_id = intval($res['ROOM_ID']);
            $room_status_map[$room_id] = 'ACTIVE';
            error_log("Room {$room_id} marked as ACTIVE from active reservations");
        }

        // Mark UPCOMING_TODAY (if not already ACTIVE)
        foreach ($response['upcoming_reservations'] as $res) {
            $room_id = intval($res['ROOM_ID']);
            if ($room_status_map[$room_id] !== 'ACTIVE') {
                $room_status_map[$room_id] = 'UPCOMING_TODAY';
            }
        }

        // Mark approved reservations based on current status regardless of time
        $approved_sql = "SELECT r.room_id, r.status 
                       FROM sys.room_reservation r 
                       WHERE UPPER(r.status) = 'APPROVED'
                       AND TRUNC(r.start_time) = TRUNC(SYSDATE)";
        $approved_stmt = oci_parse($conn, $approved_sql);
        oci_execute($approved_stmt);
        while ($row = oci_fetch_assoc($approved_stmt)) {
            $room_id = intval($row['ROOM_ID']);
            // Mark approved reservations as ACTIVE to ensure they show as occupied
            $room_status_map[$room_id] = 'ACTIVE';
            error_log("Room {$room_id} marked as ACTIVE from approved reservation");
        }

        // Mark FUTURE (if not already ACTIVE or UPCOMING_TODAY)
        foreach ($response['all_reservations'] as $res) {
            $room_id = intval($res['ROOM_ID']);
            $start_time = strtotime($res['START_TIME']);
            if (
                $room_status_map[$room_id] === 'AVAILABLE' &&
                $start_time > strtotime('tomorrow')
            ) {
                $room_status_map[$room_id] = 'FUTURE';
            }
        }

        // Mark PENDING (if not already ACTIVE or UPCOMING_TODAY)
        if (isset($response['pending_reservations'])) {
            foreach ($response['pending_reservations'] as $res) {
                $room_id = intval($res['ROOM_ID']);
                // Mark as PENDING if not already ACTIVE or UPCOMING_TODAY
                if ($room_status_map[$room_id] !== 'ACTIVE' && $room_status_map[$room_id] !== 'UPCOMING_TODAY') {
                    $room_status_map[$room_id] = 'PENDING';
                }
            }
        }

        // Add direct status info using existing room reservations for debugging
        $direct_status = [];
        $direct_sql = "SELECT DISTINCT room_id, status 
                     FROM sys.room_reservation 
                     WHERE TRUNC(start_time) = TRUNC(SYSDATE)
                     AND (UPPER(status) = 'APPROVED' OR UPPER(status) = 'PENDING')";
        $direct_stmt = oci_parse($conn, $direct_sql);
        oci_execute($direct_stmt);
        while ($row = oci_fetch_assoc($direct_stmt)) {
            $direct_status[] = $row;
        }
        $response['direct_room_status'] = $direct_status;

        $response['room_status_map'] = $room_status_map;
    
    echo json_encode($response);
    } else {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Free statements
    if (isset($active_stmt)) oci_free_statement($active_stmt);
    if (isset($upcoming_stmt)) oci_free_statement($upcoming_stmt);
    if (isset($all_stmt)) oci_free_statement($all_stmt);
    if (isset($pending_stmt)) oci_free_statement($pending_stmt);
    if (isset($todays_stmt)) oci_free_statement($todays_stmt);
    if (isset($approved_stmt)) oci_free_statement($approved_stmt);
    if (isset($direct_stmt)) oci_free_statement($direct_stmt);
    
    ob_end_flush();
}
?> 