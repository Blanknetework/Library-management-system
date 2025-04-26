<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID']);
    exit();
}

$student_id = $data['student_id'];

$conn = getOracleConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Use TO_CHAR to format the date directly in Oracle
$sql = "SELECT 
            br.request_id,
            b.title,
            TO_CHAR(br.request_date, 'Mon DD, YYYY') as formatted_date,
            br.status
        FROM sys.book_borrowing_requests br
        JOIN sys.books b ON br.book_id = b.reference_id
        WHERE br.student_id = :student_id
        ORDER BY br.request_date DESC";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    oci_close($conn);
    exit();
}

oci_bind_by_name($stmt, ":student_id", $student_id);

if (!oci_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Failed to execute query']);
    oci_free_statement($stmt);
    oci_close($conn);
    exit();
}

$requests = [];
while ($row = oci_fetch_assoc($stmt)) {
    $requests[] = [
        'request_id' => $row['REQUEST_ID'],
        'title' => $row['TITLE'],
        'request_date' => $row['FORMATTED_DATE'],
        'status' => $row['STATUS']
    ];
}

oci_free_statement($stmt);
oci_close($conn);

echo json_encode(['success' => true, 'requests' => $requests]); 