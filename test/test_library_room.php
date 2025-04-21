<?php
require_once 'config.php';

echo "<h1>Library Room Reservation System Test</h1>";
echo "<style>
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .section { margin: 20px 0; padding: 10px; background: #f8f9fa; }
</style>";

function displayError($message) {
    echo "<div class='error'>Error: $message</div>";
}

function displaySuccess($message) {
    echo "<div class='success'>Success: $message</div>";
}

function displayWarning($message) {
    echo "<div class='warning'>Warning: $message</div>";
}

try {
    $conn = getOracleConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }

    // 1. Test Database Connection
    echo "<div class='section'>";
    echo "<h2>1. Database Connection Test</h2>";
    displaySuccess("Successfully connected to database");
    echo "</div>";

    // 2. Check Room Reservation Table Structure
    echo "<div class='section'>";
    echo "<h2>2. Room Reservation Table Structure</h2>";
    $structure_sql = "SELECT column_name, data_type, data_length, nullable 
                     FROM all_tab_columns 
                     WHERE table_name = 'ROOM_RESERVATION'
                     ORDER BY column_id";
    
    $structure_stmt = oci_parse($conn, $structure_sql);
    oci_execute($structure_stmt);
    
    echo "<table>";
    echo "<tr><th>Column Name</th><th>Data Type</th><th>Length</th><th>Nullable</th></tr>";
    
    while ($row = oci_fetch_assoc($structure_stmt)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['DATA_TYPE']) . "</td>";
        echo "<td>" . htmlspecialchars($row['DATA_LENGTH']) . "</td>";
        echo "<td>" . htmlspecialchars($row['NULLABLE']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // 3. Current Active Reservations
    echo "<div class='section'>";
    echo "<h2>3. Current Active Reservations</h2>";
    $active_sql = "WITH current_time AS (
        SELECT SYSTIMESTAMP AT TIME ZONE 'Asia/Manila' as current_ts FROM DUAL
    )
    SELECT r.*, s.full_name,
           TO_CHAR(r.start_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as start_time_local,
           TO_CHAR(r.end_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as end_time_local
    FROM room_reservation r
    LEFT JOIN students s ON r.student_id = s.student_id
    WHERE r.start_time AT TIME ZONE 'Asia/Manila' <= (SELECT current_ts FROM current_time)
    AND r.end_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
    ORDER BY r.room_id";
    
    $active_stmt = oci_parse($conn, $active_sql);
    oci_execute($active_stmt);
    
    $has_active = false;
    echo "<table>";
    echo "<tr><th>Room ID</th><th>Student</th><th>Start Time</th><th>End Time</th><th>Purpose</th></tr>";
    
    while ($row = oci_fetch_assoc($active_stmt)) {
        $has_active = true;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['ROOM_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['FULL_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['START_TIME_LOCAL']) . "</td>";
        echo "<td>" . htmlspecialchars($row['END_TIME_LOCAL']) . "</td>";
        echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$has_active) {
        displayWarning("No active reservations found");
    }
    echo "</div>";

    // 4. Today's Upcoming Reservations
    echo "<div class='section'>";
    echo "<h2>4. Today's Upcoming Reservations</h2>";
    $upcoming_sql = "WITH current_time AS (
        SELECT SYSTIMESTAMP AT TIME ZONE 'Asia/Manila' as current_ts FROM DUAL
    )
    SELECT r.*, s.full_name,
           TO_CHAR(r.start_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as start_time_local,
           TO_CHAR(r.end_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as end_time_local
    FROM room_reservation r
    LEFT JOIN students s ON r.student_id = s.student_id
    WHERE r.start_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
    AND TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') = TRUNC((SELECT current_ts FROM current_time))
    ORDER BY r.start_time";
    
    $upcoming_stmt = oci_parse($conn, $upcoming_sql);
    oci_execute($upcoming_stmt);
    
    $has_upcoming = false;
    echo "<table>";
    echo "<tr><th>Room ID</th><th>Student</th><th>Start Time</th><th>End Time</th><th>Purpose</th></tr>";
    
    while ($row = oci_fetch_assoc($upcoming_stmt)) {
        $has_upcoming = true;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['ROOM_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['FULL_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['START_TIME_LOCAL']) . "</td>";
        echo "<td>" . htmlspecialchars($row['END_TIME_LOCAL']) . "</td>";
        echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$has_upcoming) {
        displayWarning("No upcoming reservations for today");
    }
    echo "</div>";

    // 5. Room Status Summary
    echo "<div class='section'>";
    echo "<h2>5. Room Status Summary</h2>";
    echo "<table>";
    echo "<tr><th>Room ID</th><th>Status</th><th>Current/Next Reservation</th></tr>";
    
    for ($room_id = 1; $room_id <= 4; $room_id++) {
        $status_sql = "WITH current_time AS (
            SELECT SYSTIMESTAMP AT TIME ZONE 'Asia/Manila' as current_ts FROM DUAL
        )
        SELECT r.*, s.full_name,
               TO_CHAR(r.start_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as start_time_local,
               TO_CHAR(r.end_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as end_time_local,
               CASE 
                   WHEN r.start_time AT TIME ZONE 'Asia/Manila' <= (SELECT current_ts FROM current_time)
                        AND r.end_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
                   THEN 'ACTIVE'
                   WHEN r.start_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
                        AND TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') = TRUNC((SELECT current_ts FROM current_time))
                   THEN 'UPCOMING_TODAY'
                   WHEN TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') > TRUNC((SELECT current_ts FROM current_time))
                   THEN 'FUTURE'
                   ELSE 'PAST'
               END as status
        FROM room_reservation r
        LEFT JOIN students s ON r.student_id = s.student_id
        WHERE r.room_id = :room_id
        AND (
            -- Active reservations
            (r.start_time AT TIME ZONE 'Asia/Manila' <= (SELECT current_ts FROM current_time)
             AND r.end_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time))
            OR
            -- Today's upcoming reservations
            (r.start_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
             AND TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') = TRUNC((SELECT current_ts FROM current_time)))
            OR
            -- Future reservations (next 7 days)
            (TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') > TRUNC((SELECT current_ts FROM current_time))
             AND TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') <= TRUNC((SELECT current_ts FROM current_time)) + 7)
        )
        ORDER BY r.start_time
        FETCH FIRST 1 ROW ONLY";
        
        $status_stmt = oci_parse($conn, $status_sql);
        oci_bind_by_name($status_stmt, ":room_id", $room_id);
        oci_execute($status_stmt);
        
        $row = oci_fetch_assoc($status_stmt);
        
        echo "<tr>";
        echo "<td>Room " . $room_id . "</td>";
        if ($row) {
            $status = $row['STATUS'];
            $status_text = match($status) {
                'ACTIVE' => "<span style='color: red'>Currently Occupied</span>",
                'UPCOMING_TODAY' => "<span style='color: orange'>Reserved Today</span>",
                'FUTURE' => "<span style='color: blue'>Reserved</span>",
                default => "<span style='color: green'>Available</span>"
            };
            echo "<td>" . $status_text . "</td>";
            echo "<td>" . htmlspecialchars($row['FULL_NAME']) . " (" . 
                 htmlspecialchars($row['START_TIME_LOCAL']) . " - " . 
                 htmlspecialchars($row['END_TIME_LOCAL']) . ")</td>";
        } else {
            echo "<td><span style='color: green'>Available</span></td>";
            echo "<td>No upcoming reservations</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // 6. Recent Reservation History
    echo "<div class='section'>";
    echo "<h2>6. Recent Reservation History (Last 5 Days)</h2>";
    $history_sql = "WITH current_time AS (
        SELECT SYSTIMESTAMP AT TIME ZONE 'Asia/Manila' as current_ts FROM DUAL
    )
    SELECT r.*, s.full_name,
           TO_CHAR(r.start_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as start_time_local,
           TO_CHAR(r.end_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS') as end_time_local,
           CASE 
               WHEN r.start_time AT TIME ZONE 'Asia/Manila' <= (SELECT current_ts FROM current_time)
                    AND r.end_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
               THEN 'ACTIVE'
               WHEN r.start_time AT TIME ZONE 'Asia/Manila' > (SELECT current_ts FROM current_time)
                    AND TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') = TRUNC((SELECT current_ts FROM current_time))
               THEN 'UPCOMING_TODAY'
               WHEN TRUNC(r.start_time AT TIME ZONE 'Asia/Manila') > TRUNC((SELECT current_ts FROM current_time))
               THEN 'FUTURE'
               ELSE 'COMPLETED'
           END as reservation_status
    FROM room_reservation r
    LEFT JOIN students s ON r.student_id = s.student_id
    WHERE r.start_time AT TIME ZONE 'Asia/Manila' >= (SELECT current_ts FROM current_time) - INTERVAL '5' DAY
    ORDER BY r.start_time DESC";
    
    $history_stmt = oci_parse($conn, $history_sql);
    oci_execute($history_stmt);
    
    $has_history = false;
    echo "<table>";
    echo "<tr><th>Room ID</th><th>Student</th><th>Start Time</th><th>End Time</th><th>Purpose</th><th>Status</th></tr>";
    
    while ($row = oci_fetch_assoc($history_stmt)) {
        $has_history = true;
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['ROOM_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['FULL_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['START_TIME_LOCAL']) . "</td>";
        echo "<td>" . htmlspecialchars($row['END_TIME_LOCAL']) . "</td>";
        echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
        echo "<td>" . htmlspecialchars($row['RESERVATION_STATUS']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$has_history) {
        displayWarning("No reservation history found for the last 5 days");
    }
    echo "</div>";

    // 7. Debug Information
    echo "<div class='section'>";
    echo "<h2>7. Debug Information</h2>";
    echo "<h3>Current Oracle Timestamp</h3>";
    $time_sql = "SELECT 
                 TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD HH24:MI:SS.FF TZR') as current_time,
                 TO_CHAR(SYSTIMESTAMP AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS.FF TZR') as manila_time
                 FROM DUAL";
    $time_stmt = oci_parse($conn, $time_sql);
    oci_execute($time_stmt);
    $time_row = oci_fetch_assoc($time_stmt);
    echo "<p>Current UTC Time: " . htmlspecialchars($time_row['CURRENT_TIME']) . "</p>";
    echo "<p>Current Manila Time: " . htmlspecialchars($time_row['MANILA_TIME']) . "</p>";

    echo "<h3>Last 5 Reservations (Raw Data)</h3>";
    $debug_sql = "SELECT r.*, s.full_name,
                  TO_CHAR(r.start_time, 'YYYY-MM-DD HH24:MI:SS.FF TZR') as start_time_utc,
                  TO_CHAR(r.end_time, 'YYYY-MM-DD HH24:MI:SS.FF TZR') as end_time_utc,
                  TO_CHAR(r.start_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS.FF TZR') as start_time_manila,
                  TO_CHAR(r.end_time AT TIME ZONE 'Asia/Manila', 'YYYY-MM-DD HH24:MI:SS.FF TZR') as end_time_manila
                  FROM room_reservation r
                  LEFT JOIN students s ON r.student_id = s.student_id
                  ORDER BY r.reservation_id DESC
                  FETCH FIRST 5 ROWS ONLY";
    
    $debug_stmt = oci_parse($conn, $debug_sql);
    oci_execute($debug_stmt);
    
    echo "<table>";
    echo "<tr><th>Reservation ID</th><th>Room ID</th><th>Student</th><th>Start Time</th><th>End Time</th><th>Purpose</th></tr>";
    
    while ($row = oci_fetch_assoc($debug_stmt)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['RESERVATION_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['ROOM_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['FULL_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['START_TIME_UTC']) . "</td>";
        echo "<td>" . htmlspecialchars($row['END_TIME_UTC']) . "</td>";
        echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

} catch (Exception $e) {
    displayError($e->getMessage());
    if (isset($conn)) {
        displayError("Database error: " . oci_error($conn)['message']);
    }
} finally {
    // Clean up
    if (isset($structure_stmt)) oci_free_statement($structure_stmt);
    if (isset($active_stmt)) oci_free_statement($active_stmt);
    if (isset($upcoming_stmt)) oci_free_statement($upcoming_stmt);
    if (isset($status_stmt)) oci_free_statement($status_stmt);
    if (isset($history_stmt)) oci_free_statement($history_stmt);
    if (isset($conn)) oci_close($conn);
}
?> 