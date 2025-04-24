<?php
session_start();

require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Handle search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$has_searched = isset($_GET['search']);

// Fetch borrowed books from database
$borrowed_books = [];
$conn = getOracleConnection();
if ($conn) {
    $sql = "SELECT 
                bl.borrow_date,
                bl.return_date,
                s.student_id,
                s.full_name as student_name,
                b.reference_id,
                b.title,
                b.quality as condition,
                CASE 
                    WHEN bl.return_date IS NOT NULL AND bl.return_date < SYSDATE THEN 'Overdue'
                    WHEN bl.return_date IS NOT NULL THEN 'In Progress'
                    ELSE 'Returned'
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing - Library Management System</title>
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
                        <a href="dashboard.php" class="nav-link flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
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
                        <a href="borrowing.php" class="nav-link flex items-center px-4 py-2 bg-blue-700 rounded-md text-white">
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

                    <div class="bg-white rounded-lg shadow overflow-hidden border border-blue-200">
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
                                                </tr>
                                            <?php endfor; ?>
                                        <?php else: ?>
                                            <?php foreach ($borrowed_books as $book): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($book['borrow_date']))); ?>
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
                                                                echo htmlspecialchars(date('M d, Y', strtotime($book['return_date'])));
                                                            } else {
                                                                echo '—';
                                                            }
                                                        ?>
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
        });
    </script>
</body>
</html> 