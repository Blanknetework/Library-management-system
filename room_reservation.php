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

// Define the rooms
$rooms = [
    1 => 'Discussion Room 1',
    2 => 'Study Room 1',
    3 => 'Conference Room',
    4 => 'Multimedia Room'
];

// Get currently reserved rooms
$in_use_sql = "SELECT room_id, 
               TO_CHAR(start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time, 
               TO_CHAR(end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time 
               FROM room_reservation 
               WHERE end_time > CURRENT_TIMESTAMP";
$stmt = executeOracleQuery($in_use_sql);
$in_use_rooms = [];
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $in_use_rooms[$row['ROOM_ID']] = [
            'start_time' => $row['START_TIME'],
            'end_time' => $row['END_TIME']
        ];
    }
    closeOracleConnection($stmt);
}

// Create array of all rooms with their status
$room_status = [];
foreach ($rooms as $id => $name) {
    $status = 'Available';
    $time_info = '';
    
    if (isset($in_use_rooms[$id])) {
        $status = 'Occupied';
        try {
            $end = new DateTime($in_use_rooms[$id]['end_time']);
            $time_info = ' until ' . $end->format('H:i');
        } catch (Exception $e) {
            $time_info = '';
        }
    }
    
    $room_status[] = [
        'ROOM_ID' => $id,
        'ROOM_NAME' => $name,
        'STATUS' => $status,
        'TIME_INFO' => $time_info
    ];
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = isset($_POST['room_id']) ? trim($_POST['room_id']) : '';
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
    
    // Validate input
    if (empty($room_id) || empty($purpose) || empty($start_time) || empty($end_time)) {
        $error_message = "All fields are required.";
    } else {
        // Create room reservation record
        $sql = "INSERT INTO room_reservation (reservation_id, room_id, student_id, start_time, end_time, purpose) 
                VALUES (room_res_seq.NEXTVAL, :room_id, :student_id, 
                TO_TIMESTAMP(:start_time, 'YYYY-MM-DD HH24:MI'), 
                TO_TIMESTAMP(:end_time, 'YYYY-MM-DD HH24:MI'), :purpose)";
        
        $params = [
            ':room_id' => $room_id,
            ':student_id' => $_SESSION['student_id'],
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':purpose' => $purpose
        ];
        
        $stmt = executeOracleQuery($sql, $params);
        
        if ($stmt) {
            closeOracleConnection($stmt);
            $success_message = "Room reservation successful!";
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
    <title>Reserve a Room - QCU Library Management System</title>
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
                            <a href="pc_reservation.php" class="block py-2 px-4 hover:bg-blue-700 rounded">PC Reservation</a>
                        </li>
                        <li class="mb-2">
                            <a href="room_reservation.php" class="block py-2 px-4 bg-blue-900 rounded">Room Reservation</a>
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
                <h1 class="text-2xl font-bold mb-2">Reserve a Room</h1>
                <p class="text-gray-600">Book a study room for group activities or presentations.</p>
            </div>

      
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


            

            <!-- Room Reservation Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Reserve a Room</h2>
                <form action="process/process_room_reservation.php" method="POST" onsubmit="return validateForm()">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" for="room_id">
                                Select Room
                            </label>
                            <select name="room_id" id="room_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select a room...</option>
                                <?php foreach ($room_status as $room): ?>
                                <option value="<?php echo $room['ROOM_ID']; ?>" 
                                        <?php echo $room['STATUS'] === 'Occupied' ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($room['ROOM_NAME']); ?> 
                                    (<?php echo $room['STATUS'] . $room['TIME_INFO']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" for="purpose">
                                Purpose
                            </label>
                            <select name="purpose" id="purpose" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select purpose...</option>
                                <option value="Group Study">Group Study</option>
                                <option value="Individual Study">Individual Study</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Project Work">Project Work</option>
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
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Reserve Room
                        </button>
                    </div>
                </form>
            </div>

            <!-- Room Status Display -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">Room Status Overview</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach ($room_status as $room): ?>
                    <div class="border rounded-lg p-4 <?php echo $room['STATUS'] === 'Available' ? 'bg-green-50' : 'bg-red-50'; ?>">
                        <h3 class="font-medium"><?php echo htmlspecialchars($room['ROOM_NAME']); ?></h3>
                        <div class="mt-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                <?php echo $room['STATUS'] === 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo htmlspecialchars($room['STATUS']); ?>
                            </span>
                            <?php if ($room['TIME_INFO']): ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo htmlspecialchars($room['TIME_INFO']); ?>
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
    function validateForm() {
        const startTime = new Date(document.getElementById('start_time').value);
        const endTime = new Date(document.getElementById('end_time').value);
        const now = new Date();

        if (startTime < now) {
            alert('Start time must be in the future');
            return false;
        }

        if (endTime <= startTime) {
            alert('End time must be after start time');
            return false;
        }

        const duration = (endTime - startTime) / (1000 * 60 * 60); // Duration in hours
        if (duration > 4) {
            alert('Maximum reservation duration is 4 hours');
            return false;
        }

        return true;
    }

    // Set min datetime to current time
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const nowString = now.toISOString().slice(0, 16);
    document.getElementById('start_time').min = nowString;
    document.getElementById('end_time').min = nowString;
    </script>
</body>
</html> 