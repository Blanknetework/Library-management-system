<?php
session_start();

require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Fetch access logs from database
$access_logs = [];
$conn = getOracleConnection();
if ($conn) {
    $sql = "WITH student_facilities AS (
                SELECT l.login_id, 
                       'PC' as facility_type, 
                       p.start_time as facility_time 
                FROM sys.login l
                JOIN sys.pc_use p ON l.student_id = p.student_id
                WHERE TRUNC(l.login_time) = TRUNC(p.start_time)
                
                UNION ALL
                
                SELECT l.login_id, 
                       'Room' as facility_type, 
                       r.start_time as facility_time 
                FROM sys.login l
                JOIN sys.room_reservation r ON l.student_id = r.student_id
                WHERE TRUNC(l.login_time) = TRUNC(r.start_time)
            ),
            ranked_facilities AS (
                SELECT sf.login_id,
                       sf.facility_type,
                       sf.facility_time,
                       ROW_NUMBER() OVER (PARTITION BY sf.login_id ORDER BY ABS(EXTRACT(SECOND FROM (sf.facility_time - l.login_time)) + 
                                                                           EXTRACT(MINUTE FROM (sf.facility_time - l.login_time)) * 60 + 
                                                                           EXTRACT(HOUR FROM (sf.facility_time - l.login_time)) * 3600) ASC) as rn
                FROM student_facilities sf
                JOIN sys.login l ON sf.login_id = l.login_id
            )
            SELECT l.login_id, l.student_id, s.full_name, s.course, 
                   TO_CHAR(l.login_time, 'YYYY-MM-DD') as login_date,
                   TO_CHAR(l.login_time, 'HH24:MI:SS') as login_time,
                   COALESCE(rf.facility_type, 'None') as facilities_used,
                   (SELECT COUNT(*) FROM sys.book_loans bl 
                    WHERE bl.student_id = l.student_id 
                    AND bl.return_date IS NULL) as pending_books
            FROM sys.login l
            JOIN sys.students s ON l.student_id = s.student_id
            LEFT JOIN ranked_facilities rf ON l.login_id = rf.login_id AND rf.rn = 1
            WHERE TRUNC(l.login_time) = TRUNC(SYSDATE)
            ORDER BY l.login_time DESC";
    
    $stmt = oci_parse($conn, $sql);
    if ($stmt && oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $access_logs[] = [
                'student_id' => $row['STUDENT_ID'],
                'name' => $row['FULL_NAME'],
                'course' => $row['COURSE'],
                'date' => $row['LOGIN_DATE'],
                'time' => $row['LOGIN_TIME'],
                'facilities_used' => $row['FACILITIES_USED'],
                'pending_books' => $row['PENDING_BOOKS']
            ];
        }
        oci_free_statement($stmt);
    }
    oci_close($conn);
}

// Get total logins today
$total_logins = count($access_logs);

// Get most used facility stats
$facility_stats = [
    'PC' => 0,
    'Room' => 0,
    'None' => 0
];

foreach ($access_logs as $log) {
    $facility_stats[$log['facilities_used']]++;
}

// Determine most used facility
$most_used_facility = 'N/A';
$most_used_count = 0;
foreach ($facility_stats as $facility => $count) {
    if ($count > $most_used_count && $facility != 'None') {
        $most_used_facility = $facility;
        $most_used_count = $count;
    }
}

// Format for display
$most_used_display = $most_used_facility;
if ($most_used_facility != 'N/A') {
    $most_used_display .= ' (' . $most_used_count . ')';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
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
            transition: margin-left 0.3s ease-in-out;
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
                        <a href="dashboard.php" class="nav-link flex items-center px-4 py-2 bg-blue-700 rounded-md text-white">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            <span class="nav-text">Access Logs</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="rooms.php" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            <span class="nav-text">Rooms</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="pcs.php" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            <span class="nav-text">PC's</span>
                        </a>
                    </li>
                     <li class="mb-2">
                        <a href="books.php" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                            <span class="nav-text">Book Archives</span>
                        </a>
                    </li>
                     <li class="mb-2">
                        <a href="borrowing.php" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                           <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4v12l-4-2-4 2V4M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="nav-text">Borrowing</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="mt-auto">
                <a href="logout.php" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span class="logout-text">Logout</span>
                </a>
            </div>
        </div>

        <div id="mainContent" class="main-content flex-1 ml-64 flex flex-col overflow-hidden">
            <div id="contentWrapper" class="flex-1 flex flex-col overflow-y-auto p-6">
                <main class="flex-1">
                    <div class="mb-4">
                        <h1 class="text-xl font-bold text-blue-800">Welcome Back, <?php echo htmlspecialchars($admin_username); ?>!</h1>
                    </div>
                    
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Access Logs</h2>
                    </div>

                    <div class="bg-white rounded-lg shadow overflow-hidden border border-blue-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Student Id</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Course</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Facilities Used</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pending Books</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($access_logs)): ?>
                                        <?php for ($i = 0; $i < 10; $i++): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">&nbsp;</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                            </tr>
                                        <?php endfor; ?>
                                    <?php else: ?>
                                        <?php foreach ($access_logs as $log): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($log['student_id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($log['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['course']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['date']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['time']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['facilities_used']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['pending_books']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div class="bg-white p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Total Logins Today</h3>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $total_logins; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow">
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Most Used Facility</h3>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $most_used_display; ?></p>
                            <div class="mt-2 text-sm text-gray-500">
                                <div class="flex items-center">
                                    <span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-1"></span>
                                    <span>PC: <?php echo $facility_stats['PC']; ?></span>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                                    <span>Room: <?php echo $facility_stats['Room']; ?></span>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-block w-3 h-3 bg-gray-300 rounded-full mr-1"></span>
                                    <span>None: <?php echo $facility_stats['None']; ?></span>
                                </div>
                            </div>
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
        });
    </script>
</body>
</html>
