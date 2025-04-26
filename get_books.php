<?php
require_once 'config.php';

header('Content-Type: application/json');

$conn = getOracleConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$sql = "SELECT 
            b.reference_id as book_id,
            b.title,
            b.author,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM sys.book_borrowing_requests br 
                    WHERE br.book_id = b.reference_id 
                    AND br.status = 'Approved'
                ) THEN 'Borrowed'
                ELSE 'Available'
            END as availability
        FROM sys.books b
        ORDER BY b.title";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    oci_close($conn);
    exit();
}

if (!oci_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Failed to execute query']);
    oci_free_statement($stmt);
    oci_close($conn);
    exit();
}

$books = [];
while ($row = oci_fetch_assoc($stmt)) {
    $books[] = [
        'book_id' => $row['BOOK_ID'],
        'title' => $row['TITLE'],
        'author' => $row['AUTHOR'],
        'availability' => $row['AVAILABILITY']
    ];
}

oci_free_statement($stmt);
oci_close($conn);

echo json_encode(['success' => true, 'books' => $books]); 