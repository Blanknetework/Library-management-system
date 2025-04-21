<?php
require_once 'config.php';

echo "<h1>Oracle Timezone Check</h1>";

$conn = getOracleConnection();
if (!$conn) {
    die("Connection failed");
}

// Check various time-related settings
$checks = array(
    "SELECT SESSIONTIMEZONE FROM DUAL",
    "SELECT DBTIMEZONE FROM DUAL",
    "SELECT SYSTIMESTAMP FROM DUAL",
    "SELECT CURRENT_TIMESTAMP FROM DUAL",
    "SELECT TO_CHAR(SYSTIMESTAMP, 'YYYY-MM-DD HH24:MI:SS.FF TZH:TZM') FROM DUAL"
);

foreach ($checks as $sql) {
    $stmt = oci_parse($conn, $sql);
    if (oci_execute($stmt)) {
        $row = oci_fetch_array($stmt);
        echo "<p><strong>" . htmlspecialchars($sql) . ":</strong><br>";
        echo htmlspecialchars($row[0]) . "</p>";
    }
    oci_free_statement($stmt);
}

// Also check some active reservations with their times
echo "<h2>Current Active Reservations:</h2>";
$res_sql = "SELECT 
    pc_id,
    TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS.FF TZH:TZM') as start_time,
    TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS.FF TZH:TZM') as end_time,
    CASE 
        WHEN SYSTIMESTAMP BETWEEN start_time AND end_time THEN 'ACTIVE'
        ELSE 'INACTIVE'
    END as status
FROM pc_use
WHERE TRUNC(start_time) = TRUNC(SYSTIMESTAMP)
ORDER BY start_time DESC";

$stmt = oci_parse($conn, $res_sql);
if (oci_execute($stmt)) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>PC ID</th><th>Start Time</th><th>End Time</th><th>Status</th></tr>";
    
    while ($row = oci_fetch_assoc($stmt)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['PC_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['START_TIME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['END_TIME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['STATUS']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

oci_free_statement($stmt);
oci_close($conn);
?> 