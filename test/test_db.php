<?php
require_once 'config.php';

echo "<h1>Database Connection Test</h1>";

$conn = getOracleConnection();
if (!$conn) {
    die("Connection failed");
}

echo "Connection successful!<br>";

// Print current schema
$schema_sql = "SELECT SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') FROM dual";
$schema_stmt = oci_parse($conn, $schema_sql);
oci_execute($schema_stmt);
$row = oci_fetch_array($schema_stmt);
echo "Current Schema: " . $row[0] . "<br><br>";

// Test direct table access
$sql = "SELECT * FROM BOOKS";
echo "Executing query: " . htmlspecialchars($sql) . "<br>";

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

echo "<h2>Books in Database:</h2>";
echo "<table border='1'>";
echo "<tr><th>Reference ID</th><th>Title</th><th>Author</th><th>Quality</th></tr>";

$count = 0;
while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $count++;
    echo "<tr>";
    echo "<td>" . ($row['REFERENCE_ID'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['TITLE'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['AUTHOR'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['QUALITY'] ?? 'NULL') . "</td>";
    echo "</tr>";
    
    // Debug output
    echo "<!-- Debug: ";
    print_r($row);
    echo " -->";
}

echo "</table>";
echo "<br>Total books found: " . $count;

// Try with explicit schema
echo "<h2>Trying with explicit schema:</h2>";
$sql2 = "SELECT * FROM SYSTEM.BOOKS";
$stmt2 = oci_parse($conn, $sql2);
oci_execute($stmt2);

echo "<table border='1'>";
echo "<tr><th>Reference ID</th><th>Title</th><th>Author</th><th>Quality</th></tr>";

$count2 = 0;
while ($row = oci_fetch_array($stmt2, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $count2++;
    echo "<tr>";
    echo "<td>" . ($row['REFERENCE_ID'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['TITLE'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['AUTHOR'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['QUALITY'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<br>Total books found (with schema): " . $count2;

// List all tables accessible to current user
echo "<h2>Available Tables:</h2>";
$tables_sql = "SELECT table_name FROM all_tables WHERE owner = 'SYSTEM'";
$tables_stmt = oci_parse($conn, $tables_sql);
oci_execute($tables_stmt);

echo "<ul>";
while ($row = oci_fetch_array($tables_stmt)) {
    echo "<li>" . htmlspecialchars($row[0]) . "</li>";
}
echo "</ul>";

oci_free_statement($stmt);
oci_close($conn);
?>