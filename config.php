<?php
// Oracle database connection parameters
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SID', 'orcl');
define('DB_USERNAME', 'sys');
define('DB_PASSWORD', 'adam1234');
define('DB_SERVICE_NAME', 'LibrarymanagementDB');

putenv("ORACLE_HOME=C:\\Oracle\\instantclient_19_9");
putenv("TNS_ADMIN=C:\\Oracle\\instantclient_19_9\\network\\admin");
putenv("NLS_LANG=AMERICAN_AMERICA.AL32UTF8");

function getOracleConnection() {
    $connectionString = 'orcl';
    
    // Connect with SYSDBA privilege
    $conn = oci_connect(DB_USERNAME, DB_PASSWORD, $connectionString, 'AL32UTF8', OCI_SYSDBA);
    
    if ($conn) {
        // Set session parameters
        $setup_stmts = array(
            "ALTER SESSION SET CURRENT_SCHEMA = SYS",
            "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'",
            "ALTER SESSION SET NLS_LENGTH_SEMANTICS = CHAR",
            "ALTER SESSION SET ISOLATION_LEVEL = READ COMMITTED"
        );
        
        foreach ($setup_stmts as $sql) {
            $stmt = oci_parse($conn, $sql);
            oci_execute($stmt);
            oci_free_statement($stmt);
        }
    }
    
    return $conn;
}

function executeOracleQuery($sql, $params = []) {
    $conn = getOracleConnection();
    if (!$conn) {
        error_log("Failed to get database connection");
        return false;
    }

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        error_log("Oracle Parse Error: " . $e['message']);
        oci_close($conn);
        return false;
    }

    error_log("Executing SQL: " . $sql);
    foreach ($params as $key => $value) {
        error_log("Binding parameter $key: " . $value);
        oci_bind_by_name($stmt, $key, $params[$key]);
    }

    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    if (!$result) {
        $e = oci_error($stmt);
        error_log("Oracle Execute Error: " . $e['message']);
        error_log("Failed SQL: " . $sql);
        error_log("Parameters: " . print_r($params, true));
        oci_free_statement($stmt);
        oci_close($conn);
        return false;
    }

    return $stmt;
}

function closeOracleConnection($stmt = null, $conn = null) {
    if ($stmt) oci_free_statement($stmt);
    if ($conn) oci_close($conn);
}

function testDatabaseConnection() {
    $conn = getOracleConnection();
    if (!$conn) return false;
    oci_close($conn);
    return true;
}

// echo (testDatabaseConnection()) ? "Database connection successful!" : "Database connection failed!";
?>
