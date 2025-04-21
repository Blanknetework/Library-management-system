<?php
require_once 'config.php';

echo "<h1>PC_USE Table Structure Check</h1>";

$conn = getOracleConnection();
if (!$conn) {
    die("Connection failed");
}

// Check if table exists
$check_table_sql = "SELECT table_name 
                   FROM all_tables 
                   WHERE owner = 'SYS' 
                   AND table_name = 'PC_USE'";

$check_stmt = oci_parse($conn, $check_table_sql);
oci_execute($check_stmt);
$table_exists = oci_fetch_assoc($check_stmt);

if ($table_exists) {
    echo "<p style='color:green;'>PC_USE table exists!</p>";
    
    // Get table structure
    $structure_sql = "SELECT column_name, data_type, data_length, nullable
                     FROM all_tab_columns 
                     WHERE owner = 'SYS' 
                     AND table_name = 'PC_USE'
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
    echo "<p style='color:red;'>PC_USE table does not exist!</p>";
    
    // Create the table
    echo "<h2>Creating PC_USE table...</h2>";
    $create_table_sql = "CREATE TABLE SYS.PC_USE (
        reservation_id NUMBER PRIMARY KEY,
        pc_id NUMBER NOT NULL,
        student_id VARCHAR2(20) NOT NULL,
        start_time TIMESTAMP NOT NULL,
        end_time TIMESTAMP NOT NULL,
        purpose VARCHAR2(50) NOT NULL
    )";
    
    $create_stmt = oci_parse($conn, $create_table_sql);
    if (oci_execute($create_stmt)) {
        echo "<p style='color:green;'>PC_USE table created successfully!</p>";
    } else {
        $e = oci_error($create_stmt);
        echo "<p style='color:red;'>Error creating table: " . htmlspecialchars($e['message']) . "</p>";
    }
}

oci_free_statement($check_stmt);
if (isset($structure_stmt)) oci_free_statement($structure_stmt);
if (isset($create_stmt)) oci_free_statement($create_stmt);
oci_close($conn);
?> 