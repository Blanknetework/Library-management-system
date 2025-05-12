<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Include the database configuration
require_once '../config.php';

// Set header to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);
    exit();
}

// Get the JSON data from the request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate the input data
if (!isset($data['student_id']) || !isset($data['name']) || !isset($data['course']) || !isset($data['section']) || !isset($data['contact']) || !isset($data['address']) || !isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Sanitize and validate input
$student_id = trim($data['student_id']);
$name = trim($data['name']);
$course = trim($data['course']);
$section = trim($data['section']);
$contact = trim($data['contact']);
$address = trim($data['address']);
$email = trim($data['email']);

// Simple validation
if (empty($student_id) || empty($name) || empty($course) || empty($section) || empty($contact) || empty($address) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate student ID format (updated for "YY-XXXX" format like "23-2444")
if (!preg_match('/^\d{2}-\d{4}$/', $student_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID format. Expected: YY-XXXX (e.g., 23-2444)']);
    exit();
}

// Validate contact number (11 digits, starting with 09)
if (!preg_match('/^09\d{9}$/', $contact)) {
    echo json_encode(['success' => false, 'message' => 'Invalid contact number. Must be 11 digits starting with 09']);
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Connect to the database
$conn = getOracleConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if the student ID already exists in the students table
$check_sql = "SELECT COUNT(*) as count FROM sys.students WHERE student_id = :student_id";
$check_stmt = oci_parse($conn, $check_sql);
oci_bind_by_name($check_stmt, ":student_id", $student_id);

if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . oci_error($conn)['message']]);
    oci_close($conn);
    exit();
}

if (!oci_execute($check_stmt)) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . oci_error($check_stmt)['message']]);
    oci_free_statement($check_stmt);
    oci_close($conn);
    exit();
}

$row = oci_fetch_assoc($check_stmt);
if ($row['COUNT'] > 0) {
    echo json_encode(['success' => false, 'message' => 'A student account with this ID already exists']);
    oci_free_statement($check_stmt);
    oci_close($conn);
    exit();
}
oci_free_statement($check_stmt);

// Parse the full name (assumed format: Last Name, First Name, M.I.)
$name_parts = explode(',', $name);
$last_name = trim($name_parts[0] ?? '');
$first_name = '';

if (count($name_parts) > 1) {
    $first_name = trim($name_parts[1] ?? '');
}

// Course and section are now provided from the form
// $course = 'N/A';
// $section = 'N/A';

// Check if contact_number and address columns exist
$check_columns_sql = "
    SELECT 
        COUNT(CASE WHEN COLUMN_NAME = 'CONTACT_NUMBER' THEN 1 END) as has_contact,
        COUNT(CASE WHEN COLUMN_NAME = 'ADDRESS' THEN 1 END) as has_address,
        COUNT(CASE WHEN COLUMN_NAME = 'STATUS' THEN 1 END) as has_status,
        COUNT(CASE WHEN COLUMN_NAME = 'FIRST_NAME' THEN 1 END) as has_first_name,
        COUNT(CASE WHEN COLUMN_NAME = 'LAST_NAME' THEN 1 END) as has_last_name
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'STUDENTS'
    AND COLUMN_NAME IN ('CONTACT_NUMBER', 'ADDRESS', 'STATUS', 'FIRST_NAME', 'LAST_NAME')
";

$check_columns_stmt = oci_parse($conn, $check_columns_sql);
if (!$check_columns_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . oci_error($conn)['message']]);
    oci_close($conn);
    exit();
}

if (!oci_execute($check_columns_stmt)) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . oci_error($check_columns_stmt)['message']]);
    oci_free_statement($check_columns_stmt);
    oci_close($conn);
    exit();
}

$columns_info = oci_fetch_assoc($check_columns_stmt);
oci_free_statement($check_columns_stmt);

$has_contact = $columns_info['HAS_CONTACT'] > 0;
$has_address = $columns_info['HAS_ADDRESS'] > 0;
$has_status = $columns_info['HAS_STATUS'] > 0;
$has_first_name = $columns_info['HAS_FIRST_NAME'] > 0;
$has_last_name = $columns_info['HAS_LAST_NAME'] > 0;

// Build dynamic SQL based on available columns
$columns = "
    student_id, 
    full_name, 
    " . ($has_first_name ? "first_name, " : "") . "
    " . ($has_last_name ? "last_name, " : "") . "
    course,
    section,
    " . ($has_contact ? "contact_number, " : "") . "
    " . ($has_address ? "address, " : "") . "
    email, 
    created_at" . 
    ($has_status ? ", status" : "") . "
";

$values = "
    :student_id, 
    :full_name,
    " . ($has_first_name ? ":first_name, " : "") . "
    " . ($has_last_name ? ":last_name, " : "") . "
    :course,
    :section,
    " . ($has_contact ? ":contact, " : "") . "
    " . ($has_address ? ":address, " : "") . "
    :email, 
    SYSDATE" . 
    ($has_status ? ", 'active'" : "") . "
";

// Insert SQL
$insert_sql = "INSERT INTO sys.students ($columns) VALUES ($values)";

$insert_stmt = oci_parse($conn, $insert_sql);
if (!$insert_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . oci_error($conn)['message']]);
    oci_close($conn);
    exit();
}

// Bind the parameters
oci_bind_by_name($insert_stmt, ":student_id", $student_id);
oci_bind_by_name($insert_stmt, ":full_name", $name);
if ($has_first_name) {
    oci_bind_by_name($insert_stmt, ":first_name", $first_name);
}
if ($has_last_name) {
    oci_bind_by_name($insert_stmt, ":last_name", $last_name);
}
oci_bind_by_name($insert_stmt, ":course", $course);
oci_bind_by_name($insert_stmt, ":section", $section);
if ($has_contact) {
    oci_bind_by_name($insert_stmt, ":contact", $contact);
}
if ($has_address) {
    oci_bind_by_name($insert_stmt, ":address", $address);
}
oci_bind_by_name($insert_stmt, ":email", $email);

// Execute the statement
if (!oci_execute($insert_stmt)) {
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . oci_error($insert_stmt)['message']]);
    oci_free_statement($insert_stmt);
    oci_close($conn);
    exit();
}

oci_free_statement($insert_stmt);
oci_close($conn);

// Return success response
echo json_encode([
    'success' => true, 
    'message' => 'Student account registered successfully',
    'data' => [
        'student_id' => $student_id,
        'name' => $name
    ]
]); 