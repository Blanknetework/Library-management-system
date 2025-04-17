<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in, redirect to login page
    header("Location: index.php");
    exit();
}

// Include database configuration
require_once 'config.php';

// Get available PC rooms and PCs
$pc_rooms = [];
$sql = "SELECT pr.*, f.facility_name 
        FROM pc_room pr 
        JOIN facilities f ON pr.facility_id = f.facility_id 
        ORDER BY f.facility_name, pr.pc_id";
$stmt = executeOracleQuery($sql);
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $pc_rooms[] = $row;
    }
    closeOracleConnection($stmt);
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pc_id = isset($_POST['pc_id']) ? trim($_POST['pc_id']) : '';
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
    
    // Validate input
    if (empty($pc_id) || empty($purpose) || empty($start_time) || empty($end_time)) {
        $error_message = "All fields are required.";
    } else {
        // Create PC use record
        $sql = "INSERT INTO pc_use (reservation_id, pc_id, student_id, start_time, end_time, purpose) 
                VALUES (pc_use_seq.NEXTVAL, :pc_id, :student_id, TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI'), 
                TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI'), :purpose)";
        
        $params = [
            ':pc_id' => $pc_id,
            ':student_id' => $_SESSION['student_id'],
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':purpose' => $purpose
        ];
        
        $stmt = executeOracleQuery($sql, $params);
        
        if ($stmt) {
            closeOracleConnection($stmt);
            $success_message = "PC reservation successful!";
        } else {
            $error_message = "Failed to create reservation. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve a PC - QCU Library Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-blue-800 text-white">
            <div class="p-6">
                <div class="flex items-center mb-8">
                    <img src="assets/images/QCU_Logo_2019.png" alt="QCU Logo" class="w-10 h-10 mr-3">
                    <h2 class="text-xl font-bold">QCU Library</h2>
                </div>
                
                <nav>
                    <ul>

                        <li class="mb-2">
                            <a href="dashboard.php" class="block py-2 px-4 hover:bg-blue-700 rounded">Dashboard</a>
                        </li>

                        <li class="mb-2">
                            <a href="books.php" class="block py-2 px-4 hover:bg-blue-700 rounded">Books</a>
                        </li>

                        <li class="mb-2">
                            <a href="pc_reservation.php" class="block py-2 px-4 bg-blue-900 rounded">PC Reservation</a>
                        </li>
                        <li class="mb-2">
                            <a href="room_reservation.php" class="block py-2 px-4 hover:bg-blue-700 rounded">Room Reservation</a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="block py-2 px-4 hover:bg-blue-700 rounded">Usage History</a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="block py-2 px-4 hover:bg-blue-700 rounded">Profile</a>
                        </li>
                        <li class="mt-8">
                            <a href="logout.php" class="block py-2 px-4 hover:bg-red-600 bg-red-700 rounded">Logout</a>
                        </li>

                        
                    </ul>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-10 overflow-y-auto">
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2">Reserve a PC</h1>
                <p class="text-gray-600">Book a computer for your study or research needs.</p>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Reservation Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">PC Reservation Form</h2>
                
                <form action="pc_reservation.php" method="post">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="pc_id" class="block text-sm font-medium text-gray-700 mb-1">Select PC</label>
                            <select id="pc_id" name="pc_id" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">-- Select a PC --</option>
                                <?php foreach ($pc_rooms as $pc): ?>
                                <option value="<?php echo htmlspecialchars($pc['PC_ID']); ?>">
                                    PC <?php echo htmlspecialchars($pc['PC_ID']); ?> - <?php echo htmlspecialchars($pc['FACILITY_NAME']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                            <select id="purpose" name="purpose" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="">-- Select Purpose --</option>
                                <option value="Research">Research</option>
                                <option value="Assignment">Assignment</option>
                                <option value="Thesis">Thesis</option>
                                <option value="Online Class">Online Class</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                            <input type="datetime-local" id="start_time" name="start_time" 
                                   class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        
                        <div>
                            <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                            <input type="datetime-local" id="end_time" name="end_time" 
                                   class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded transition">
                                Reserve PC
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Available PCs -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">Available PCs</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 text-left">PC ID</th>
                                <th class="py-2 px-4 text-left">Location</th>
                                <th class="py-2 px-4 text-left">Status</th>
                                <th class="py-2 px-4 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pc_rooms as $pc): ?>
                            <tr class="border-b">
                                <td class="py-2 px-4">PC <?php echo htmlspecialchars($pc['PC_ID']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($pc['FACILITY_NAME']); ?></td>
                                <td class="py-2 px-4">
                                    <span class="inline-block bg-green-100 text-green-800 px-2 py-1 text-xs rounded-full">Available</span>
                                </td>
                                <td class="py-2 px-4">
                                    <button class="text-blue-600 hover:text-blue-800 text-sm" 
                                            onclick="selectPC('<?php echo htmlspecialchars($pc['PC_ID']); ?>')">
                                        Select
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function selectPC(pcId) {
            document.getElementById('pc_id').value = pcId;
            // Scroll to the form
            document.querySelector('.bg-white.rounded-lg').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html> 