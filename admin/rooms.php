<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Handle reservation removal
$removal_message = '';
if (isset($_POST['remove_reservation']) && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    $conn = getOracleConnection();
    if ($conn) {
        $stmt = oci_parse($conn, "DELETE FROM sys.room_reservation WHERE reservation_id = :reservation_id");
        oci_bind_by_name($stmt, ":reservation_id", $reservation_id);
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $removal_message = '<div id="notification-alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                                  <strong>Success!</strong> Reservation has been removed.
                                </div>';
        } else {
            $e = oci_error($stmt);
            $removal_message = '<div id="notification-alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                                  <strong>Error!</strong> ' . $e['message'] . '
                                </div>';
        }
        oci_free_statement($stmt);
        oci_close($conn);
    }
}

// Handle search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$has_searched = isset($_GET['search']);

// Fetch room reservations from database
$room_reservations = [];
$pending_reservations = [];
$conn = getOracleConnection();
if ($conn) {
    // Get pending reservations
    $pending_sql = "SELECT r.reservation_id, r.room_id, r.student_id, s.full_name, 
                   TO_CHAR(r.start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time,
                   TO_CHAR(r.end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time,
                   r.purpose,
                   r.status,
                   r.approved_by,
                   TO_CHAR(r.approval_date, 'YYYY-MM-DD HH24:MI:SS') as approval_date
            FROM sys.room_reservation r
            JOIN sys.students s ON r.student_id = s.student_id
            WHERE r.status = 'Pending'
            ORDER BY r.start_time DESC";
    $pending_stmt = oci_parse($conn, $pending_sql);
    if ($pending_stmt && oci_execute($pending_stmt)) {
        while ($row = oci_fetch_assoc($pending_stmt)) {
            $pending_reservations[] = [
                'reservation_id' => $row['RESERVATION_ID'],
                'room_id' => $row['ROOM_ID'],
                'student_id' => $row['STUDENT_ID'],
                'student_name' => $row['FULL_NAME'],
                'start_time' => $row['START_TIME'],
                'end_time' => $row['END_TIME'],
                'purpose' => $row['PURPOSE'],
                'status' => $row['STATUS'],
                'approved_by' => $row['APPROVED_BY'],
                'approval_date' => $row['APPROVAL_DATE']
            ];
        }
        oci_free_statement($pending_stmt);
    }

    // Get approved/rejected reservations
    $sql = "SELECT r.reservation_id, r.room_id, r.student_id, s.full_name, 
                   TO_CHAR(r.start_time, 'YYYY-MM-DD HH24:MI:SS') as start_time,
                   TO_CHAR(r.end_time, 'YYYY-MM-DD HH24:MI:SS') as end_time,
                   r.purpose,
                   r.status,
                   r.approved_by,
                   TO_CHAR(r.approval_date, 'YYYY-MM-DD HH24:MI:SS') as approval_date
            FROM sys.room_reservation r
            JOIN sys.students s ON r.student_id = s.student_id
            WHERE r.status != 'Pending'";
    if (!empty($search_term)) {
        $sql .= " AND (UPPER(s.full_name) LIKE UPPER('%' || :search_term || '%') 
                 OR UPPER(s.student_id) LIKE UPPER('%' || :search_term || '%')
                 OR UPPER(r.purpose) LIKE UPPER('%' || :search_term || '%')
                 OR TO_CHAR(r.room_id) LIKE '%' || :search_term || '%')";
    }
    $sql .= " ORDER BY r.start_time DESC";
    $stmt = oci_parse($conn, $sql);
    if (!empty($search_term)) {
        oci_bind_by_name($stmt, ":search_term", $search_term);
    }
    if ($stmt && oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $room_reservations[] = [
                'reservation_id' => $row['RESERVATION_ID'],
                'room_id' => $row['ROOM_ID'],
                'student_id' => $row['STUDENT_ID'],
                'student_name' => $row['FULL_NAME'],
                'start_time' => $row['START_TIME'],
                'end_time' => $row['END_TIME'],
                'purpose' => $row['PURPOSE'],
                'status' => $row['STATUS'],
                'approved_by' => $row['APPROVED_BY'],
                'approval_date' => $row['APPROVAL_DATE']
            ];
        }
        oci_free_statement($stmt);
    }
    oci_close($conn);
}

// Handle reservation approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reservation_id = $_POST['reservation_id'] ?? null;
    $action = $_POST['action'];
    if ($reservation_id && ($action === 'approve' || $action === 'reject')) {
        $conn = getOracleConnection();
        if ($conn) {
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            $admin = $_SESSION['admin_username'];
            
            // First get the room_id and start_time for this reservation
            $get_reservation_sql = "SELECT room_id, start_time FROM sys.room_reservation WHERE reservation_id = :reservation_id";
            $get_stmt = oci_parse($conn, $get_reservation_sql);
            oci_bind_by_name($get_stmt, ":reservation_id", $reservation_id);
            oci_execute($get_stmt);
            $reservation_data = oci_fetch_assoc($get_stmt);
            
            if ($reservation_data) {
                $room_id = $reservation_data['ROOM_ID'];
                $start_time = $reservation_data['START_TIME'];
                
                // Update the reservation status
                $update_sql = "UPDATE sys.room_reservation 
                             SET status = :status,
                                 approved_by = :admin,
                                 approval_date = SYSTIMESTAMP
                             WHERE reservation_id = :reservation_id";
                $stmt = oci_parse($conn, $update_sql);
                if ($stmt) {
                    oci_bind_by_name($stmt, ":status", $status);
                    oci_bind_by_name($stmt, ":admin", $admin);
                    oci_bind_by_name($stmt, ":reservation_id", $reservation_id);
                    
                    if (oci_execute($stmt)) {
                        // If approved, update room status to ACTIVE
                        if ($action === 'approve') {
                            $update_room_sql = "UPDATE sys.room_status 
                                              SET status = 'ACTIVE',
                                                  last_updated = SYSTIMESTAMP
                                              WHERE room_id = :room_id";
                            $room_stmt = oci_parse($conn, $update_room_sql);
                            oci_bind_by_name($room_stmt, ":room_id", $room_id);
                            oci_execute($room_stmt);
                        }
                        // If rejected, delete the reservation completely
                        else if ($action === 'reject') {
                            // First make sure room status is AVAILABLE
                            $update_room_sql = "UPDATE sys.room_status 
                                              SET status = 'AVAILABLE',
                                                  last_updated = SYSTIMESTAMP
                                              WHERE room_id = :room_id";
                            $room_stmt = oci_parse($conn, $update_room_sql);
                            oci_bind_by_name($room_stmt, ":room_id", $room_id);
                            oci_execute($room_stmt);
                            
                            // Then delete the reservation completely
                            $delete_sql = "DELETE FROM sys.room_reservation WHERE reservation_id = :reservation_id";
                            $delete_stmt = oci_parse($conn, $delete_sql);
                            oci_bind_by_name($delete_stmt, ":reservation_id", $reservation_id);
                            oci_execute($delete_stmt);
                        }
                        
                        header("Location: rooms.php?success=" . ($action === 'approve' ? 'approved' : 'rejected'));
                        exit();
                    }
                }
            }
            oci_close($conn);
        }
    }
}
?>  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Reservations - Library Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
      
        /* Hide all scrollbars */
        ::-webkit-scrollbar {
            display: none;
        }
        
        * {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
       
        /* Sidebar transition */
        .sidebar {
            transition: width 0.3s ease-in-out;
            position: relative;
        }
        .sidebar.collapsed {
            width: 70px !important;
        }
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .logout-text {
            display: none;
        }
        .sidebar.collapsed .qcu-logo {
             display: none;
        }
        .sidebar.collapsed .nav-link svg {
            margin-right: 0;
        }
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
       
        .sidebar.collapsed #sidebarToggle {
            right: 50%;
            transform: translateX(50%);
            top: 1rem;
        }
        
        .sidebar.collapsed .pt-8 {
            padding-top: 3rem;
        }

        .main-content {
            margin-left: 16rem;
        }
        
        .main-content.collapsed {
            margin-left: 70px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100 overflow-hidden">
        <div id="sidebar" class="sidebar fixed inset-y-0 left-0 w-64 bg-blue-900 text-white p-4 flex flex-col">
            <button id="sidebarToggle" class="absolute top-4 right-4 p-1 rounded-md bg-blue-800 text-white hover:bg-blue-700 focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <div class="flex items-center mb-8 pt-8">
                <img src="../assets/images/QCU_Logo_2019.png" alt="QCU Logo" class="qcu-logo h-10 w-10 mr-3">
                <span class="logo-text text-xl font-bold">Library Management System</span>
            </div>

            <nav class="flex-1">
                <ul>
                    <li class="mb-2">
                        <a href="dashboard" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            <span class="nav-text">Access Logs</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="rooms" class="nav-link flex items-center px-4 py-2 bg-blue-700 rounded-md text-white">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            <span class="nav-text">Rooms</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="pcs" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            <span class="nav-text">PC's</span>
                        </a>
                    </li>
                     <li class="mb-2">
                        <a href="books" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                            <span class="nav-text">Book Archives</span>
                        </a>
                    </li>
                     <li class="mb-2">
                        <a href="borrowing" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                           <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4v12l-4-2-4 2V4M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="nav-text">Borrowing</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="mt-auto">
                <a href="logout" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span class="logout-text">Logout</span>
                </a>
            </div>
        </div>

        <div id="mainContent" class="main-content flex-1 ml-64 flex flex-col overflow-hidden">
            <div id="contentWrapper" class="flex-1 flex flex-col overflow-y-auto p-6">
                <main class="flex-1">
                    <?php echo $removal_message; ?>
                    
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Room Reservations</h2>
                    </div>

                    <form action="" method="GET" class="mb-4">
                        <div class="flex">
                            <input type="text" 
                                   name="search"
                                   value="<?php echo htmlspecialchars($search_term); ?>"
                                   placeholder="Search by name, student ID, room or purpose..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Search
                            </button>
                            <?php if (!empty($search_term)): ?>
                                <a href="rooms.php" class="ml-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Pending Reservations Section -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Pending Reservations</h3>
                    <div class="bg-white rounded-lg shadow overflow-hidden border border-blue-200">
                            <?php if (empty($pending_reservations)): ?>
                            <div class="p-8 text-center">
                                    <p class="text-gray-500">No pending reservations</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($pending_reservations as $reservation): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        Room <?php echo htmlspecialchars($reservation['room_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($reservation['student_name']); ?>
                                                        <br>
                                                        <span class="text-xs text-gray-400">
                                                            <?php echo htmlspecialchars($reservation['student_id']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($reservation['start_time']); ?> - 
                                                        <?php echo htmlspecialchars($reservation['end_time']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($reservation['purpose']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            Pending
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <form method="post" class="flex space-x-2">
                                                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['reservation_id']); ?>">
                                                            <button type="submit" name="action" value="approve" 
                                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                                Approve
                                                            </button>
                                                            <button type="submit" name="action" value="reject" 
                                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                                Reject
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- All Reservations Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">All Reservations</h3>
                        <div class="bg-white rounded-lg shadow overflow-hidden border border-blue-200">
                            <?php if (empty($room_reservations)): ?>
                                <div class="p-8 text-center">
                                    <p class="text-gray-500">No reservations found</p>
                                </div>
                                        <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($room_reservations as $reservation): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        Room <?php echo htmlspecialchars($reservation['room_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($reservation['student_name']); ?> 
                                                        <br>
                                                        <span class="text-xs text-gray-400">
                                                            <?php echo htmlspecialchars($reservation['student_id']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($reservation['start_time']); ?> - 
                                                        <?php echo htmlspecialchars($reservation['end_time']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($reservation['purpose']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                            <?php echo $reservation['status'] === 'Approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo htmlspecialchars($reservation['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <form method="post" onsubmit="return confirm('Are you sure you want to remove this reservation?');">
                                                            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation['reservation_id']); ?>">
                                                            <button type="submit" name="remove_reservation" 
                                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                                Remove
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            let isCollapsed = false;
            
            sidebarToggle.addEventListener('click', function() {
                isCollapsed = !isCollapsed;
                
                sidebar.classList.toggle('collapsed');
                
                if (isCollapsed) {
                    mainContent.classList.remove('ml-64');
                    mainContent.classList.add('ml-[70px]');
                } else {
                    mainContent.classList.add('ml-64');
                    mainContent.classList.remove('ml-[70px]');
                }

                const icon = sidebarToggle.querySelector('svg');
                if (isCollapsed) {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
                } else {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                }
            });
            
            // Auto-hide notification after 5 seconds
            const notification = document.getElementById('notification-alert');
            if (notification) {
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html> 