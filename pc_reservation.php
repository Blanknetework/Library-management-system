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

// Get available PCs (numbers 1-30)
$total_pcs = 30;
$pcs = [];

// Get currently in-use PCs with their time slots
$in_use_sql = "SELECT pc_id, 
               TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time, 
               TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time 
               FROM pc_use 
               WHERE end_time > CURRENT_TIMESTAMP";
$stmt = executeOracleQuery($in_use_sql);
$in_use_pcs = [];
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $in_use_pcs[$row['PC_ID']] = [
            'start_time' => $row['START_TIME'],
            'end_time' => $row['END_TIME']
        ];
    }
    closeOracleConnection($stmt);
}

// Create array of all PCs with their status
for ($i = 1; $i <= $total_pcs; $i++) {
    $status = 'Available';
    $time_info = '';
    
    if (isset($in_use_pcs[$i])) {
        $status = 'Occupied';
        try {
            $end = new DateTime($in_use_pcs[$i]['end_time']);
            $time_info = ' until ' . $end->format('H:i');
        } catch (Exception $e) {
            // If there's an error parsing the date, just show "Occupied" without time
            $time_info = '';
        }
    }
    
    $pcs[] = [
        'PC_ID' => $i,
        'PC_NUMBER' => 'PC ' . $i,
        'STATUS' => $status,
        'TIME_INFO' => $time_info
    ];
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
    <title>PC Reservation - QCU Library</title>
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
                        <li class="mt-8">
                            <a href="logout.php" class="block py-2 px-4 hover:bg-red-600 bg-red-700 rounded">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-10 overflow-y-auto">
            <!-- Notifications -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                    <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                    <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2">PC Reservation</h1>
                <p class="text-gray-600">Reserve a PC for your study or research needs.</p>
            </div>
            
            <!-- Reservation Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">PC Reservation Form</h2>
                
                <form action="process_pc_reservation.php" method="post">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="pc_id" class="block text-sm font-medium text-gray-700 mb-1">Select PC</label>
                            <select 
                                id="pc_id" 
                                name="pc_id" 
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option value="">-- Select a PC --</option>
                                <?php foreach ($pcs as $pc): ?>
                                    <?php if ($pc['STATUS'] === 'Available'): ?>
                                    <option value="<?php echo htmlspecialchars($pc['PC_ID']); ?>">
                                        <?php echo htmlspecialchars($pc['PC_NUMBER']); ?> 
                                        (<?php echo htmlspecialchars($pc['STATUS']); ?>)
                                    </option>
                                    <?php else: ?>
                                    <option value="<?php echo htmlspecialchars($pc['PC_ID']); ?>" disabled>
                                        <?php echo htmlspecialchars($pc['PC_NUMBER']); ?> 
                                        (<?php echo htmlspecialchars($pc['STATUS']); ?>)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                            <select 
                                id="purpose" 
                                name="purpose" 
                                class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option value="">-- Select Purpose --</option>
                                <option value="Research">Research</option>
                                <option value="Assignment">Assignment</option>
                                <option value="Project">Project</option>
                                <option value="Others">Others</option>
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
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">PC Status Overview</h2>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-4">
                    <?php foreach ($pcs as $pc): ?>
                    <div class="border rounded-lg p-4 <?php echo $pc['STATUS'] === 'Available' ? 'bg-green-50' : 'bg-red-50'; ?>">
                        <h3 class="font-medium text-center"><?php echo htmlspecialchars($pc['PC_NUMBER']); ?></h3>
                        <div class="mt-2 text-center">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                <?php echo $pc['STATUS'] === 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo htmlspecialchars($pc['STATUS']); ?>
                            </span>
                            <?php if ($pc['TIME_INFO']): ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo htmlspecialchars($pc['TIME_INFO']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add validation to ensure end time is after start time
        document.getElementById('start_time').addEventListener('change', function() {
            document.getElementById('end_time').min = this.value;
        });

        document.getElementById('end_time').addEventListener('change', function() {
            if(this.value <= document.getElementById('start_time').value) {
                alert('End time must be after start time');
                this.value = '';
            }
        });
    </script>
</body>
</html> 