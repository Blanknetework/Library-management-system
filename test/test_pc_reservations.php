<?php
require_once 'config.php';

echo "<h1>PC Reservations Test</h1>";

$conn = getOracleConnection();
if (!$conn) {
    die("Connection failed");
}

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

echo "<h2>Current Time: $current_date $current_time</h2>";

// Query to get all PC reservations
$sql = "SELECT 
            reservation_id,
            pc_id,
            student_id,
            TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time,
            TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time,
            purpose
        FROM pc_use
        ORDER BY start_time DESC";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    die("Parse failed: " . htmlspecialchars($e['message']));
}

$result = oci_execute($stmt);
if (!$result) {
    $e = oci_error($stmt);
    die("Execute failed: " . htmlspecialchars($e['message']));
}

echo "<h2>All PC Reservations:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr>
        <th>Reservation ID</th>
        <th>PC ID</th>
        <th>Student ID</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Purpose</th>
      </tr>";

while ($row = oci_fetch_assoc($stmt)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['RESERVATION_ID']) . "</td>";
    echo "<td>" . htmlspecialchars($row['PC_ID']) . "</td>";
    echo "<td>" . htmlspecialchars($row['STUDENT_ID']) . "</td>";
    echo "<td>" . htmlspecialchars($row['START_TIME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['END_TIME']) . "</td>";
    echo "<td>" . htmlspecialchars($row['PURPOSE']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Now show active reservations
$active_sql = "SELECT pc_id 
               FROM pc_use 
               WHERE TO_CHAR(start_time, 'YYYY-MM-DD') = :current_date 
               AND TO_TIMESTAMP(TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS'), 'YYYY-MM-DD HH24:MI:SS') <= 
                   TO_TIMESTAMP(:current_date || ' ' || :current_time, 'YYYY-MM-DD HH24:MI:SS')
               AND TO_TIMESTAMP(TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS'), 'YYYY-MM-DD HH24:MI:SS') > 
                   TO_TIMESTAMP(:current_date || ' ' || :current_time, 'YYYY-MM-DD HH24:MI:SS')";

$active_stmt = oci_parse($conn, $active_sql);
oci_bind_by_name($active_stmt, ":current_date", $current_date);
oci_bind_by_name($active_stmt, ":current_time", $current_time);

$active_result = oci_execute($active_stmt);
if ($active_result) {
    echo "<h2>Currently Active Reservations:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>PC ID</th></tr>";
    
    while ($row = oci_fetch_assoc($active_stmt)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['PC_ID']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

oci_free_statement($stmt);
oci_free_statement($active_stmt);
oci_close($conn);
?> 