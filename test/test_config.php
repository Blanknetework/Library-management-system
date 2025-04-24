<?php
// Include database configuration
require_once '../config.php';

echo "<h1>Library Management System Database Test</h1>";

// Show configuration details
echo "<h2>Configuration Details</h2>";
echo "<pre>";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_PORT: " . DB_PORT . "\n";
echo "DB_SID: " . DB_SID . "\n";
echo "DB_USERNAME: " . DB_USERNAME . "\n";
echo "DB_SERVICE_NAME: " . DB_SERVICE_NAME . "\n";
echo "ORACLE_HOME: " . getenv('ORACLE_HOME') . "\n";
echo "TNS_ADMIN: " . getenv('TNS_ADMIN') . "\n";
echo "OCI8 version: " . oci_client_version() . "\n";
echo "OCI8.privileged_connect: " . ini_get('oci8.privileged_connect') . "\n";
echo "</pre>";

// Test connection
echo "<h2>Connection Test</h2>";
$conn = getOracleConnection();

if ($conn) {
    echo "<p style='color:green; font-weight:bold;'>Successfully connected using getOracleConnection()!</p>";

    // Check existing tables
    echo "<h2>Checking Existing Tables</h2>";
    $checkTables = "SELECT table_name 
                   FROM all_tables 
                   WHERE owner = 'SYS' 
                   AND table_name IN ('STUDENTS', 'LOGIN') 
                   ORDER BY table_name";
    $stmt = oci_parse($conn, $checkTables);
    
    if ($stmt && oci_execute($stmt)) {
        $existingTables = [];
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>Table Name</th></tr>";
        
        while ($row = oci_fetch_assoc($stmt)) {
            $tableName = $row['TABLE_NAME'];
            $existingTables[] = $tableName;
            echo "<tr><td>" . htmlspecialchars($tableName) . "</td></tr>";
        }
        echo "</table>";
        
        echo "<p>Found " . count($existingTables) . " required tables.</p>";
        oci_free_statement($stmt);
    }

    // Show students data
    echo "<h2>Students Table Data</h2>";
    $sql = "SELECT student_id, full_name, course, section 
            FROM sys.students 
            ORDER BY student_id";
    $stmt = oci_parse($conn, $sql);
    
    if ($stmt && oci_execute($stmt, OCI_DEFAULT)) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background-color:#f0f0f0;'>";
        echo "<th>STUDENT_ID</th>";
        echo "<th>FULL_NAME</th>";
        echo "<th>COURSE</th>";
        echo "<th>SECTION</th>";
        echo "</tr>";
        
        $rowCount = 0;
        while ($row = oci_fetch_assoc($stmt)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['STUDENT_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['FULL_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($row['COURSE']) . "</td>";
            echo "<td>" . htmlspecialchars($row['SECTION']) . "</td>";
            echo "</tr>";
            $rowCount++;
        }
        echo "</table>";
        echo "<p>Total students: $rowCount</p>";
    }
    oci_free_statement($stmt);

    // Database version
    echo "<h2>Database Version</h2>";
    $sql = "SELECT * FROM v\$version";
    $stmt = oci_parse($conn, $sql);
    if ($stmt && oci_execute($stmt)) {
        while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
            echo htmlspecialchars($row['BANNER']) . "<br>\n";
        }
    }
    oci_free_statement($stmt);

    // Close connection
    oci_close($conn);
}
?> 