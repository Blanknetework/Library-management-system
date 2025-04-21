<?php
require_once 'config.php';

echo "<h1>ROOM_RESERVATION Table Structure Check</h1>";

$conn = getOracleConnection();
if (!$conn) {
    die("Connection failed");
}

// Check if table exists
$check_table_sql = "SELECT table_name 
                   FROM all_tables 
                   WHERE owner = 'SYS' 
                   AND table_name = 'room_reservation'";

$check_stmt = oci_parse($conn, $check_table_sql);
oci_execute($check_stmt);
$table_exists = oci_fetch_assoc($check_stmt);

if ($table_exists) {
    echo "<p style='color:green;'>ROOM_RESERVATION table exists!</p>";
    
    // Get table structure
    $structure_sql = "SELECT column_name, data_type, data_length, nullable
                     FROM all_tab_columns 
                     WHERE owner = 'SYS' 
                     AND table_name = 'room_reservation'
                     ORDER BY column_id";
    
    $structure_stmt = oci_parse($conn, $structure_sql);
    oci_execute($structure_stmt);
    
    echo "<h2>Table Structure:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
            <th>Column Name</th>
            <th>Data Type</th>
            <th>Length</th>
            <th>Nullable</th>
          </tr>";
    
    while ($row = oci_fetch_assoc($structure_stmt)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['DATA_TYPE']) . "</td>";
        echo "<td>" . htmlspecialchars($row['DATA_LENGTH']) . "</td>";
        echo "<td>" . htmlspecialchars($row['NULLABLE']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>ROOM_RESERVATION table does not exist!</p>";
    
    // Create the table
    echo "<h2>Creating ROOM_RESERVATION table...</h2>";
    $create_table_sql = "CREATE TABLE SYS.room_reservation (
        reservation_id NUMBER PRIMARY KEY,
        room_id NUMBER NOT NULL,
        student_id VARCHAR2(20) NOT NULL,
        start_time TIMESTAMP NOT NULL,
        end_time TIMESTAMP NOT NULL,
        purpose VARCHAR2(50) NOT NULL
    )";
    
    $create_stmt = oci_parse($conn, $create_table_sql);
    if (oci_execute($create_stmt)) {
        echo "<p style='color:green;'>ROOM_RESERVATION table created successfully!</p>";
    } else {
        $e = oci_error($create_stmt);
        echo "<p style='color:red;'>Error creating table: " . htmlspecialchars($e['message']) . "</p>";
    }
}

// Check if sequence exists
$check_sequence_sql = "SELECT sequence_name 
                      FROM all_sequences 
                      WHERE sequence_owner = 'SYS' 
                      AND sequence_name = 'room_reservation_seq'";

$check_seq_stmt = oci_parse($conn, $check_sequence_sql);
oci_execute($check_seq_stmt);
$sequence_exists = oci_fetch_assoc($check_seq_stmt);

if ($sequence_exists) {
    echo "<p style='color:green;'>ROOM_RESERVATION_SEQ sequence exists!</p>";
} else {
    echo "<p style='color:red;'>ROOM_RESERVATION_SEQ sequence does not exist!</p>";
    
    // Create the sequence
    echo "<h2>Creating ROOM_RESERVATION_SEQ sequence...</h2>";
    $create_sequence_sql = "CREATE SEQUENCE SYS.ROOM_RESERVATION_SEQ
                           START WITH 1
                           INCREMENT BY 1
                           NOCACHE
                           NOCYCLE";
    
    $create_seq_stmt = oci_parse($conn, $create_sequence_sql);
    if (oci_execute($create_seq_stmt)) {
        echo "<p style='color:green;'>ROOM_RESERVATION_SEQ sequence created successfully!</p>";
    } else {
        $e = oci_error($create_seq_stmt);
        echo "<p style='color:red;'>Error creating sequence: " . htmlspecialchars($e['message']) . "</p>";
    }
}

oci_free_statement($check_stmt);
if (isset($structure_stmt)) oci_free_statement($structure_stmt);
if (isset($create_stmt)) oci_free_statement($create_stmt);
if (isset($check_seq_stmt)) oci_free_statement($check_seq_stmt);
if (isset($create_seq_stmt)) oci_free_statement($create_seq_stmt);
oci_close($conn);
?> 