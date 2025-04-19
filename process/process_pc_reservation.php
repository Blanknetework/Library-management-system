<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pc_id = $_POST['pc_id'];
    $student_id = $_SESSION['student_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $purpose = $_POST['purpose'];

    // Check if PC is already reserved for the given time period
    $check_sql = "SELECT COUNT(*) as count FROM pc_use 
                  WHERE pc_id = :pc_id 
                  AND end_time > TO_TIMESTAMP(:start_time, 'YYYY-MM-DD\"T\"HH24:MI')
                  AND start_time < TO_TIMESTAMP(:end_time, 'YYYY-MM-DD\"T\"HH24:MI')";
    
    $check_params = [
        ':pc_id' => $pc_id,
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ];

    $stmt = executeOracleQuery($check_sql, $check_params);
    $row = oci_fetch_assoc($stmt);
    
    if ($row['COUNT'] > 0) {
        $_SESSION['error_message'] = "PC " . $pc_id . " is already reserved for this time period.";
        header("Location: pc_reservation.php?error=occupied");
        exit();
    }

    // Get max reservation_id and add 1
    $max_sql = "SELECT NVL(MAX(reservation_id), 0) + 1 FROM pc_use";
    $stmt = executeOracleQuery($max_sql);
    $row = oci_fetch_array($stmt);
    $reservation_id = $row[0];

    // Insert reservation
    $sql = "INSERT INTO pc_use (reservation_id, pc_id, student_id, start_time, end_time, purpose) 
            VALUES (:reservation_id, :pc_id, :student_id, 
                    TO_TIMESTAMP(:start_time, 'YYYY-MM-DD\"T\"HH24:MI'), 
                    TO_TIMESTAMP(:end_time, 'YYYY-MM-DD\"T\"HH24:MI'),
                    :purpose)";

    $params = [
        ':reservation_id' => $reservation_id,
        ':pc_id' => $pc_id,
        ':student_id' => $student_id,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':purpose' => $purpose
    ];

    $stmt = executeOracleQuery($sql, $params);
    
    if ($stmt) {
        $_SESSION['success_message'] = "You have successfully reserved PC " . $pc_id;
        header("Location: ../pc_reservation.php?success=1");
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to reserve PC. Please try again.";
        header("Location: ../pc_reservation.php?error=1");
        exit();
    }
}
?>