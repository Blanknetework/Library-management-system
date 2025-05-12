<?php
session_start();
require_once 'config.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    
    if (empty($student_id)) {
        $error_message = 'Student ID is required';
    } else {
        // Query student details
        $sql = "SELECT student_id, full_name, course, section 
                FROM students 
                WHERE student_id = :student_id";
        
        $conn = getOracleConnection();
        if ($conn) {
            $stmt = oci_parse($conn, $sql);
            if ($stmt) {
                oci_bind_by_name($stmt, ":student_id", $student_id);
                $result = oci_execute($stmt);
                
                if ($result) {
                    $student = oci_fetch_assoc($stmt);
                    
                    if ($student) {
                        $success_message = "Login successful! Welcome, " . $student['FULL_NAME'];
                        
                        // Insert access log record
                        $access_sql = "INSERT INTO sys.login (login_id, student_id, login_time, used_facility, facility_id) 
                                     VALUES (sys.login_seq.NEXTVAL, :student_id, CURRENT_TIMESTAMP, 'N', NULL)";
                        
                        $access_stmt = oci_parse($conn, $access_sql);
                        if ($access_stmt) {
                            oci_bind_by_name($access_stmt, ":student_id", $student_id);
                            oci_execute($access_stmt, OCI_COMMIT_ON_SUCCESS);
                            oci_free_statement($access_stmt);
                        }
                    } else {
                        $error_message = 'Student not found';
                    }
                } else {
                    $e = oci_error($stmt);
                    $error_message = 'Error executing query: ' . $e['message'];
                }
                oci_free_statement($stmt);
            } else {
                $e = oci_error($conn);
                $error_message = 'Error preparing query: ' . $e['message'];
            }
            oci_close($conn);
        } else {
            $error_message = 'Database connection failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-library {
            background-image: url('assets/images/libbg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body class="bg-library min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
            <h1 class="text-2xl font-bold text-blue-900 mb-6 text-center">Test Login</h1>
            
            <?php if (!empty($error_message)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Student ID</label>
                    <input type="text" 
                           id="student_id" 
                           name="student_id" 
                           autocomplete="off" 
                           class="w-full px-4 py-2 text-gray-700 border-2 border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           placeholder="Enter your student number"
                           required>
                </div>
                <button type="submit" 
                        class="w-full bg-blue-900 text-white font-medium rounded py-2 hover:bg-blue-800 transition duration-200">
                    Login
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <a href="index.php" class="text-blue-600 hover:text-blue-800">Back to Main Page</a>
            </div>
        </div>
    </div>
</body>
</html> 