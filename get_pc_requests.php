<?php

require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['student_id'])) {
        throw new Exception('Student ID is required');
    }
    
    $student_id = $data['student_id'];
    
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Get PC requests for the student
    $sql = "SELECT 
                reservation_id,
                pc_id,
                start_time,
                end_time,
                purpose,
                status,
                approved_by,
                approval_date
            FROM sys.pc_use 
            WHERE student_id = ? 
            ORDER BY start_time DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'reservation_id' => $row['reservation_id'],
            'pc_id' => $row['pc_id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'purpose' => $row['purpose'],
            'status' => $row['status'],
            'approved_by' => $row['approved_by'],
            'approval_date' => $row['approval_date']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_pc_requests.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 