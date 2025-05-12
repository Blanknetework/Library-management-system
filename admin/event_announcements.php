<?php
session_start();

require_once '../config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

// Check if user is an event announcer
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'event_announcer') {
    header("Location: dashboard.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'EventAnnouncer';

// Process form submission for new announcement
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle announcement creation
        if ($_POST['action'] === 'create') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $event_date = trim($_POST['event_date'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $created_by = $admin_username;

            // Validate inputs
            if (empty($title) || empty($description) || empty($event_date)) {
                $error_message = "Please fill in all required fields.";
            } else {
                // Insert the announcement
                $conn = getOracleConnection();
                if ($conn) {
                    $sql = "INSERT INTO sys.event_announcements 
                            (announcement_id, title, description, event_date, location, created_by, created_at, status) 
                            VALUES 
                            (sys.event_announcements_seq.NEXTVAL, :title, :description, 
                             TO_DATE(:event_date, 'YYYY-MM-DD'), :location, :created_by, CURRENT_TIMESTAMP, 'active')";
                    
                    $stmt = oci_parse($conn, $sql);
                    if ($stmt) {
                        oci_bind_by_name($stmt, ":title", $title);
                        oci_bind_by_name($stmt, ":description", $description);
                        oci_bind_by_name($stmt, ":event_date", $event_date);
                        oci_bind_by_name($stmt, ":location", $location);
                        oci_bind_by_name($stmt, ":created_by", $created_by);
                        
                        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
                            $success_message = "Announcement created successfully!";
                        } else {
                            $e = oci_error($stmt);
                            $error_message = "Database error: " . $e['message'];
                        }
                        oci_free_statement($stmt);
                    }
                    oci_close($conn);
                } else {
                    $error_message = "Failed to connect to database.";
                }
            }
        }
        // Handle announcement deletion
        else if ($_POST['action'] === 'delete' && isset($_POST['announcement_id'])) {
            $announcement_id = $_POST['announcement_id'];
            
            $conn = getOracleConnection();
            if ($conn) {
                $sql = "DELETE FROM sys.event_announcements WHERE announcement_id = :announcement_id";
                $stmt = oci_parse($conn, $sql);
                if ($stmt) {
                    oci_bind_by_name($stmt, ":announcement_id", $announcement_id);
                    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
                        $success_message = "Announcement deleted successfully!";
                    } else {
                        $e = oci_error($stmt);
                        $error_message = "Database error: " . $e['message'];
                    }
                    oci_free_statement($stmt);
                }
                oci_close($conn);
            }
        }
    }
}

// Fetch existing announcements
$announcements = [];
$conn = getOracleConnection();
if ($conn) {
    $sql = "SELECT 
                announcement_id, 
                title, 
                description, 
                TO_CHAR(event_date, 'YYYY-MM-DD') as event_date, 
                location, 
                created_by, 
                TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI:SS') as created_at,
                status 
            FROM sys.event_announcements 
            ORDER BY created_at DESC";
    
    $stmt = oci_parse($conn, $sql);
    if ($stmt && oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $announcements[] = $row;
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
    <title>Event Announcements - Library Management System</title>
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
            margin-left: 16rem;
        }
        
        .main-content.collapsed {
            margin-left: 70px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100 overflow-hidden">
        <!-- Sidebar -->
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
                        <a href="event_announcements.php" class="nav-link flex items-center px-4 py-2 bg-blue-700 rounded-md text-white">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                            <span class="nav-text">Event Announcements</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="mt-auto">
                <a href="logout.php" class="flex items-center px-4 py-2 text-blue-100 hover:bg-blue-800 hover:text-white rounded-md">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="logout-text">Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div id="mainContent" class="main-content flex-1 overflow-y-auto py-6 px-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Event Announcements</h1>
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-gray-600"><?php echo htmlspecialchars($admin_username); ?></span>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div id="successAlert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div id="errorAlert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Create New Announcement Form -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Create New Announcement</h2>
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Event Title *</label>
                            <input type="text" id="title" name="title" required
                                class="w-full rounded-md border-2 border-gray-300 py-3 px-4 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="event_date" class="block text-sm font-medium text-gray-700 mb-2">Event Date *</label>
                            <input type="date" id="event_date" name="event_date" required
                                class="w-full rounded-md border-2 border-gray-300 py-3 px-4 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                        <input type="text" id="location" name="location"
                            class="w-full rounded-md border-2 border-gray-300 py-3 px-4 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                        <textarea id="description" name="description" rows="4" required
                            class="w-full rounded-md border-2 border-gray-300 py-3 px-4 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white text-base font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Create Announcement
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Announcements List -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">All Announcements</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No announcements found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($announcement['TITLE']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($announcement['EVENT_DATE']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($announcement['LOCATION'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($announcement['CREATED_BY']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($announcement['CREATED_AT']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo htmlspecialchars($announcement['STATUS']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <form action="" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['ANNOUNCEMENT_ID']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900 p-1 rounded-full hover:bg-red-100 transition-colors duration-200">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleButton = document.getElementById('sidebarToggle');
            
            // Check localStorage for collapsed state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            // Apply collapsed class if needed
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
            }

            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                
                // Store state in localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
            
            // Auto-hide alerts after 3 seconds
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease-out';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 3000);
            }
            
            if (errorAlert) {
                setTimeout(function() {
                    errorAlert.style.transition = 'opacity 0.5s ease-out';
                    errorAlert.style.opacity = '0';
                    setTimeout(function() {
                        errorAlert.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html> 