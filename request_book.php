<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['student_id']) || !isset($data['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = $data['student_id'];
$book_id = $data['book_id'];

$conn = getOracleConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if the book is already borrowed
$check_sql = "SELECT 1 FROM sys.book_borrowing_requests 
              WHERE book_id = :book_id 
              AND status = 'Approved'";

$check_stmt = oci_parse($conn, $check_sql);
oci_bind_by_name($check_stmt, ":book_id", $book_id);

if (!oci_execute($check_stmt)) {
    echo json_encode(['success' => false, 'message' => 'Failed to check book availability']);
    oci_close($conn);
    exit();
}

if (oci_fetch($check_stmt)) {
    echo json_encode(['success' => false, 'message' => 'This book is already borrowed']);
    oci_free_statement($check_stmt);
    oci_close($conn);
    exit();
}

oci_free_statement($check_stmt);

// Insert the borrowing request
$sql = "INSERT INTO sys.book_borrowing_requests (student_id, book_id, status) 
        VALUES (:student_id, :book_id, 'Pending')";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    oci_close($conn);
    exit();
}

oci_bind_by_name($stmt, ":student_id", $student_id);
oci_bind_by_name($stmt, ":book_id", $book_id);

if (!oci_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    oci_free_statement($stmt);
    oci_close($conn);
    exit();
}

oci_free_statement($stmt);
oci_close($conn);

echo json_encode(['success' => true, 'message' => 'Book request submitted successfully']); 