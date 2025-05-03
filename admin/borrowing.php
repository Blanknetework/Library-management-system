<?php
session_start();

require_once '../config.php';
require_once '../config/mailer.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Store notification in variable instead of immediately echoing
$notification_html = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    $alert_class = $notification['type'] === 'success' ? 'bg-green-500 border-green-600 text-white' : 'bg-red-500 border-red-600 text-white';
    $notification_html = "<div id='notification-alert' class='fixed top-6 right-6 z-50 flex items-center px-6 py-4 border-l-4 rounded shadow-lg {$alert_class}' role='alert' style='min-width:300px; max-width:90vw; opacity: 1; transition: opacity 0.5s ease-in-out;'>
            <div class='flex-1'>
                <strong class='font-bold mr-2'>" . ($notification['type'] === 'success' ? 'Success!' : 'Error!') . "</strong>
                <span class='block sm:inline'>" . htmlspecialchars($notification['message']) . "</span>
            </div>
            <button onclick=\"this.parentNode.style.opacity = '0'; setTimeout(() => this.parentNode.remove(), 500);\" class='ml-4 text-white focus:outline-none'>&times;</button>
          </div>";
    
    // Important: unset the notification after storing it
    unset($_SESSION['notification']);
}

// Store notification JS in variable
$notification_js = "<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notification = document.getElementById('notification-alert');
        if (notification) {
            setTimeout(function() {
                notification.style.opacity = '0';
                setTimeout(function() {
                    notification.remove();
                }, 500);
            }, 4000);
        }
    });
</script>";

// Handle search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$has_searched = isset($_GET['search']);

// Handle email notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $student_id = $_POST['student_id'];
    $book_title = $_POST['book_title'];
    $due_date = $_POST['due_date'];
    
    // Get student email from database
    $conn = getOracleConnection();
    if ($conn) {
        $sql = "SELECT email, full_name FROM sys.students WHERE student_id = :student_id";
        $stmt = oci_parse($conn, $sql);
        if ($stmt) {
            oci_bind_by_name($stmt, ":student_id", $student_id);
            if (oci_execute($stmt)) {
                $row = oci_fetch_assoc($stmt);
                if ($row) {
                    // Print all debugging info
                    $_SESSION['notification'] = [
                        'type' => 'info',
                        'message' => 'Debug: Student ID=' . $student_id . 
                                   ', EMAIL=' . $row['EMAIL'] . 
                                   ', FULL_NAME=' . $row['FULL_NAME'] .
                                   ', BOOK=' . $book_title .
                                   ', DUE_DATE=' . $due_date
                    ];
                    
                    // Now try to directly call the email function
                    try {
                        $result = sendOverdueNotification(
                            $row['EMAIL'],
                            $row['FULL_NAME'],
                            $book_title,
                            $due_date
                        );
                        
                        $_SESSION['notification'] = [
                            'type' => $result['success'] ? 'success' : 'error',
                            'message' => $result['message']
                        ];
                    } catch (Exception $e) {
                        $_SESSION['notification'] = [
                            'type' => 'error',
                            'message' => 'Exception caught: ' . $e->getMessage()
                        ];
                    }
                } else {
                    $_SESSION['notification'] = [
                        'type' => 'error',
                        'message' => 'Student record not found for ID: ' . $student_id
                    ];
                }
            } else {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'SQL execution failed'
                ];
            }
            oci_free_statement($stmt);
        } else {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Failed to prepare SQL statement'
            ];
        }
        oci_close($conn);
    } else {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Database connection failed'
        ];
    }
    
    // Use a clean redirect to prevent form resubmission and ensure layout is pristine
    header("Location: borrowing.php");
    exit();
}

// Fetch borrowed books from database
$borrowed_books = [];
$pending_requests = [];
$conn = getOracleConnection();
if ($conn) {
    // Get pending requests
    $pending_sql = "SELECT 
                    br.request_id,
                    br.student_id,
                    s.full_name as student_name,
                    b.reference_id,
                    b.title,
                    TO_CHAR(br.request_date, 'Mon DD, YYYY HH24:MI') as request_date
                FROM sys.book_borrowing_requests br
                JOIN sys.books b ON br.book_id = b.reference_id
                JOIN sys.students s ON br.student_id = s.student_id
                WHERE br.status = 'Pending'
                ORDER BY br.request_date DESC";

    $pending_stmt = oci_parse($conn, $pending_sql);
    if ($pending_stmt && oci_execute($pending_stmt)) {
        while ($row = oci_fetch_assoc($pending_stmt)) {
            $pending_requests[] = [
                'request_id' => $row['REQUEST_ID'],
                'student_id' => $row['STUDENT_ID'],
                'student_name' => $row['STUDENT_NAME'],
                'reference_id' => $row['REFERENCE_ID'],
                'title' => $row['TITLE'],
                'request_date' => $row['REQUEST_DATE']
            ];
        }
        oci_free_statement($pending_stmt);
    }

    // Get approved/active borrowings
    $sql = "SELECT 
                TO_CHAR(bl.borrow_date, 'Mon DD, YYYY') as borrow_date,
                TO_CHAR(bl.return_date, 'Mon DD, YYYY') as return_date,
                s.student_id,
                s.full_name as student_name,
                b.reference_id,
                b.title,
                b.quality as condition,
                CASE 
                    WHEN bl.return_date < SYSDATE THEN 'Overdue'
                    ELSE 'In Progress'
                END as status
            FROM sys.book_loans bl
            JOIN sys.books b ON bl.book_id = b.reference_id
            JOIN sys.students s ON bl.student_id = s.student_id";
    
    // Add search condition if search is performed
    if (!empty($search_term)) {
        $sql .= " WHERE UPPER(b.title) LIKE UPPER('%' || :search_term || '%')
                OR UPPER(s.student_id) LIKE UPPER('%' || :search_term || '%')
                OR UPPER(s.full_name) LIKE UPPER('%' || :search_term || '%')
                OR UPPER(b.reference_id) LIKE UPPER('%' || :search_term || '%')";
    }
    
    $sql .= " ORDER BY bl.borrow_date DESC";
    
    $stmt = oci_parse($conn, $sql);
    if ($stmt) {
        if (!empty($search_term)) {
            oci_bind_by_name($stmt, ":search_term", $search_term);
        }
        
        if (oci_execute($stmt)) {
            while ($row = oci_fetch_assoc($stmt)) {
                $borrowed_books[] = [
                    'borrow_date' => $row['BORROW_DATE'],
                    'return_date' => $row['RETURN_DATE'],
                    'student_id' => $row['STUDENT_ID'],
                    'student_name' => $row['STUDENT_NAME'],
                    'reference_id' => $row['REFERENCE_ID'],
                    'title' => $row['TITLE'],
                    'condition' => $row['CONDITION'],
                    'status' => $row['STATUS']
                ];
            }
        }
        oci_free_statement($stmt);
    }
    oci_close($conn);
}

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'] ?? null;
    $action = $_POST['action'];
    
    if ($request_id && ($action === 'approve' || $action === 'reject')) {
        $conn = getOracleConnection();
        if ($conn) {
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            $admin = $_SESSION['admin_username'];
            
            $sql = "UPDATE sys.book_borrowing_requests 
                    SET status = :status,
                        approved_by = :admin,
                        approval_date = SYSTIMESTAMP
                    WHERE request_id = :request_id";
            
            $stmt = oci_parse($conn, $sql);
            if ($stmt) {
                oci_bind_by_name($stmt, ":status", $status);
                oci_bind_by_name($stmt, ":admin", $admin);
                oci_bind_by_name($stmt, ":request_id", $request_id);
                
                if (oci_execute($stmt)) {
                    // If approved, create a book loan record
                    if ($action === 'approve') {
                        $loan_sql = "INSERT INTO sys.book_loans (student_id, book_id, borrow_date, return_date)
                                    SELECT student_id, book_id, SYSDATE, SYSDATE + 2
                                    FROM sys.book_borrowing_requests
                                    WHERE request_id = :request_id";
                        
                        $loan_stmt = oci_parse($conn, $loan_sql);
                        if ($loan_stmt) {
                            oci_bind_by_name($loan_stmt, ":request_id", $request_id);
                            oci_execute($loan_stmt);
                            oci_free_statement($loan_stmt);
                        }
                    }
                    
                    header("Location: borrowing.php?success=" . ($action === 'approve' ? 'approved' : 'rejected'));
                    exit();
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
    <title>Borrowing - Library Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php echo $notification_js; ?>
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
            margin-left: 16rem;
        }
        
        .main-content.collapsed {
            margin-left: 70px;
        }

        /* Status colors */
        .status-overdue {
            color: #e53e3e;
        }
        .status-returned {
            color: #38a169;
        }
        .status-progress {
            color: #3182ce;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php echo $notification_html; ?>
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
                        <a href="rooms" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
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
                        <a href="borrowing" class="nav-link flex items-center px-4 py-2 bg-blue-700 rounded-md text-white">
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
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Borrowing Records</h2>
                    </div>

                    <form action="" method="GET" class="mb-4">
                        <div class="flex">
                            <input type="text" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search_term); ?>"
                                   placeholder="Search by title, student ID, student name or reference ID..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            <button type="submit" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Search
                            </button>
                            <?php if (!empty($search_term)): ?>
                                <a href="borrowing.php" class="ml-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Pending Book Requests Section -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4">Pending Book Requests</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Book</th>
                                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Request Date</th>
                                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td class="px-6 py-4 border-b border-gray-200">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['student_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['student_id']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 border-b border-gray-200">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['title']); ?></div>
                                            <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($request['reference_id']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 border-b border-gray-200 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($request['request_date']); ?>
                                        </td>
                                        <td class="px-6 py-4 border-b border-gray-200">
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 mr-2">Approve</button>
                                                <button type="submit" name="action" value="reject" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Borrowed Books Section -->
                    <div class="bg-white rounded-lg shadow overflow-hidden border border-blue-200">
                        <h2 class="text-xl font-semibold p-6 mb-4">Book Borrowed</h2>
                        <?php if (empty($borrowed_books) && $has_searched): ?>
                            <div class="p-8 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No results found</h3>
                                <p class="mt-1 text-sm text-gray-500">We couldn't find any borrowing records matching your search.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date Borrowed</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Borrower (ID)</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Book Title</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Reference ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Book Condition</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date Returned</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($borrowed_books)): ?>
                                            <?php for ($i = 0; $i < 10; $i++): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">&nbsp;</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                </tr>
                                            <?php endfor; ?>
                                        <?php else: ?>
                                            <?php foreach ($borrowed_books as $book): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($book['borrow_date']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($book['student_name']); ?> 
                                                        <span class="text-gray-500">(<?php echo htmlspecialchars($book['student_id']); ?>)</span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($book['title']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($book['reference_id']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($book['condition']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <?php 
                                                            $status_class = '';
                                                            switch($book['status']) {
                                                                case 'Overdue':
                                                                    $status_class = 'status-overdue font-medium';
                                                                    break;
                                                                case 'Returned':
                                                                    $status_class = 'status-returned font-medium';
                                                                    break;
                                                                case 'In Progress':
                                                                    $status_class = 'status-progress font-medium';
                                                                    break;
                                                            }
                                                        ?>
                                                        <span class="<?php echo $status_class; ?>">
                                                            <?php echo htmlspecialchars($book['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                            if (!empty($book['return_date'])) {
                                                                echo htmlspecialchars($book['return_date']);
                                                            } else {
                                                                echo '—';
                                                            }
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php if ($book['status'] === 'Overdue'): ?>
                                                            <form method="POST" class="inline-block">
                                                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($book['student_id']); ?>">
                                                                <input type="hidden" name="book_title" value="<?php echo htmlspecialchars($book['title']); ?>">
                                                                <input type="hidden" name="due_date" value="<?php echo htmlspecialchars($book['return_date']); ?>">
                                                                <button type="submit" 
                                                                        name="send_notification" 
                                                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                                                    Send Reminder
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
            
            // Handle notification visibility
            const notification = document.getElementById('notification-alert');
            if (notification) {
                setTimeout(function() {
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        notification.remove();
                    }, 500);
                }, 4000);
            }
        });
    </script>
</body>
</html> 