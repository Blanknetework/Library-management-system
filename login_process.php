<?php
session_start();
require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log("Login process started");

if (!testDatabaseConnection()) {
    error_log("Database connection test failed");
    die("Database connection failed. Please check configuration.");
}

if ($conn = getOracleConnection()) {
    error_log("Database connection successful");
    oci_close($conn);
} else {
    error_log("Failed to establish database connection");
    die("Database connection failed. Please check the configuration.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $full_name = $_POST['full_name'];
    $course = $_POST['course'];
    $section = $_POST['section'];

    error_log("Login attempt - Student ID: $student_id, Name: $full_name, Course: $course, Section: $section");

    $errors = [];

    if (empty($student_id)) {
        $errors[] = "Student ID is required";
    }

    if (empty($full_name)) {
        $errors[] = "Full Name is required";
    }

    if (empty($course)) {
        $errors[] = "Course is required";
    }

    if (empty($section)) {
        $errors[] = "Section is required";
    }

    if (empty($errors)) {
        $sql = "SELECT student_id, full_name, course, section 
                FROM sys.students 
                WHERE student_id = :student_id";

        $params = [':student_id' => $student_id];

        error_log("Looking up student with ID: " . $student_id);
        $conn = getOracleConnection();

        if ($conn) {
            $stmt = oci_parse($conn, $sql);
            if ($stmt) {
                oci_bind_by_name($stmt, ":student_id", $student_id);
                $result = oci_execute($stmt);

                if ($result) {
                    $student = oci_fetch_assoc($stmt);
                    if ($student) {
                        error_log("Found student: " . print_r($student, true));
                    } else {
                        error_log("No student found with ID: " . $student_id);
                    }
                } else {
                    $e = oci_error($stmt);
                    error_log("Query execution failed: " . $e['message']);
                }
                oci_free_statement($stmt);
            } else {
                $e = oci_error($conn);
                error_log("Failed to prepare query: " . $e['message']);
            }
            oci_close($conn);
        } else {
            error_log("Failed to connect to database");
        }

        if ($student) {
            error_log("Comparing input values with database:");
            error_log("Name - Input: " . strtoupper($full_name) . " DB: " . strtoupper($student['FULL_NAME']));
            error_log("Course - Input: " . strtoupper($course) . " DB: " . strtoupper($student['COURSE']));
            error_log("Section - Input: " . strtoupper($section) . " DB: " . strtoupper($student['SECTION']));

            if (strtoupper($student['FULL_NAME']) === strtoupper($full_name) && 
                strtoupper($student['COURSE']) === strtoupper($course) && 
                strtoupper($student['SECTION']) === strtoupper($section)) {

                error_log("Student information matched, recording login");

                $sql = "INSERT INTO sys.login (login_id, student_id, login_time, used_facility, facility_id) 
                        VALUES (sys.login_seq.NEXTVAL, :student_id, CURRENT_TIMESTAMP, 'N', NULL)";

                $params = [':student_id' => $student_id];

                error_log("About to execute login insert with student_id: " . $student_id);
                error_log("SQL: " . $sql);

                $conn = getOracleConnection();
                if (!$conn) {
                    error_log("Failed to get database connection");
                    $errors[] = "Database connection failed. Please try again.";
                } else {
                    $stmt = oci_parse($conn, $sql);
                    if (!$stmt) {
                        $e = oci_error($conn);
                        error_log("Parse error: " . $e['message']);
                        $errors[] = "Failed to prepare login query. Please try again.";
                    } else {
                        oci_bind_by_name($stmt, ":student_id", $student_id);
                        $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
                        if (!$result) {
                            $e = oci_error($stmt);
                            error_log("Execute error: " . $e['message']);
                            $errors[] = "Failed to record login. Please try again.";
                        } else {
                            $_SESSION['logged_in'] = true;
                            $_SESSION['student_id'] = $student_id;
                            $_SESSION['full_name'] = $student['FULL_NAME'];
                            $_SESSION['course'] = $student['COURSE'];
                            $_SESSION['section'] = $student['SECTION'];

                            error_log("Login successful for student: $student_id");
                            header("Location: reservation.php");
                            exit();
                        }
                        oci_free_statement($stmt);
                    }
                    oci_close($conn);
                }
            } else {
                error_log("Information mismatch");
                $errors[] = "Student information doesn't match our records. Please check your details.";
            }
        } else {
            error_log("Student not found in database");
            $errors[] = "Student not found. Please check your Student ID.";
        }
    }

    $_SESSION['login_errors'] = $errors;
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
