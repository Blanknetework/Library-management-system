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

// Fetch books from database
$books = [];
$conn = getOracleConnection();
if ($conn) {
    $sql = "SELECT 
                b.reference_id as book_id,
                b.title,
                b.author,
                'General' as category,
                b.reference_id,
                b.quality as condition,
                NVL(b.branch, 'Main Library') as branch,
                'Available' as availability_status,
                b.created_at as uploaded_date
            FROM sys.books b";
    
    // Add search condition if search is performed
    if (!empty($search_term)) {
        $sql .= " WHERE UPPER(b.title) LIKE UPPER('%' || :search_term || '%')
                OR UPPER(b.author) LIKE UPPER('%' || :search_term || '%')
                OR UPPER(b.reference_id) LIKE UPPER('%' || :search_term || '%')";
    }
    
    $sql .= " ORDER BY b.created_at DESC";
    
    $stmt = oci_parse($conn, $sql);
    if ($stmt) {
        if (!empty($search_term)) {
            oci_bind_by_name($stmt, ":search_term", $search_term);
        }
        
        if (oci_execute($stmt)) {
            while ($row = oci_fetch_assoc($stmt)) {
                $books[] = [
                    'book_id' => $row['BOOK_ID'],
                    'title' => $row['TITLE'],
                    'author' => $row['AUTHOR'],
                    'category' => $row['CATEGORY'],
                    'reference_id' => $row['REFERENCE_ID'],
                    'condition' => $row['CONDITION'],
                    'branch' => $row['BRANCH'],
                    'availability' => $row['AVAILABILITY_STATUS'],
                    'uploaded_date' => $row['UPLOADED_DATE']
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
    <title>Book Archives - Library Management System</title>
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            width: 500px;
            max-width: 90%;
            margin: 50px auto;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
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
                        <a href="books" class="nav-link flex items-center px-4 py-2 bg-blue-700 rounded-md text-white">
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
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Library Books</h2>
                        <button id="uploadBookBtn" class="p-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </button>
                    </div>

                    

                    <form action="" method="GET" class="mb-4">
                        <div class="flex">
                            <input type="text" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search_term); ?>"
                                   placeholder="Search by title, author or reference ID..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            <button type="submit" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Search
                            </button>
                            <?php if (!empty($search_term)): ?>
                                <a href="books.php" class="ml-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="bg-white rounded-lg shadow overflow-hidden border border-blue-200">
                        <?php if (empty($books) && $has_searched): ?>
                            <div class="p-8 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No results found</h3>
                                <p class="mt-1 text-sm text-gray-500">We couldn't find any books matching your search.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Book Category</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Book name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Reference ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Book Condition</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch Availability</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($books)): ?>
                                            <?php for ($i = 0; $i < 10; $i++): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">&nbsp;</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                                                </tr>
                                            <?php endfor; ?>
                                        <?php else: ?>
                                            <?php foreach ($books as $book): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($book['category']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($book['title']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($book['reference_id']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($book['condition']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($book['branch']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div class="flex space-x-2">
                                                            <button class="text-blue-600 hover:text-blue-800" onclick="editBook('<?php echo $book['book_id']; ?>')">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                </svg>
                                                            </button>
                                                            <button class="text-red-600 hover:text-red-800" onclick="deleteBook('<?php echo $book['book_id']; ?>')">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
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

    <!-- Upload Book Modal -->
    <div id="uploadBookModal" class="modal">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add New Book</h3>
                    <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="uploadBookForm" method="post" action="../process/process_book_upload.php">
                    <div class="space-y-4">
                        <div>
                            <label for="student_id" class="block text-sm font-medium text-gray-700">Student ID</label>
                            <input type="text" id="student_id" name="student_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="student_name" class="block text-sm font-medium text-gray-700">Student Name</label>
                            <input type="text" id="student_name" name="student_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="course_section" class="block text-sm font-medium text-gray-700">Course/Section</label>
                            <input type="text" id="course_section" name="course_section" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="book_title" class="block text-sm font-medium text-gray-700">Book Title</label>
                            <input type="text" id="book_title" name="book_title" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="book_condition" class="block text-sm font-medium text-gray-700">Book Condition</label>
                            <input type="text" id="book_condition" name="book_condition" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="branch" class="block text-sm font-medium text-gray-700">Branch Availability</label>
                            <select id="branch" name="branch" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="Main Library">Main Library</option>
                                <option value="Batasan Library">Batasan Library</option>
                                <option value="SM Library">SM Library</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="date_borrowed" class="block text-sm font-medium text-gray-700">Date Borrowed</label>
                                <input type="date" id="date_borrowed" name="date_borrowed" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="return_date" class="block text-sm font-medium text-gray-700">Return Date</label>
                                <input type="text" id="return_date" name="return_date" value="automatically 1 day after borrowed date" disabled class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-100 text-gray-500">
                            </div>
                        </div>
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-blue-800 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Confirm
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div id="editBookModal" class="modal">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Book</h3>
                    <button id="closeEditModalBtn" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="editBookForm" method="post" action="../process/process_book_edit.php">
                    <input type="hidden" id="edit_book_id" name="book_id">
                    <div class="space-y-4">
                        <div>
                            <label for="edit_book_title" class="block text-sm font-medium text-gray-700">Book Title</label>
                            <input type="text" id="edit_book_title" name="book_title" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="edit_book_condition" class="block text-sm font-medium text-gray-700">Book Condition</label>
                            <input type="text" id="edit_book_condition" name="book_condition" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="edit_branch" class="block text-sm font-medium text-gray-700">Branch Availability</label>
                            <select id="edit_branch" name="branch" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="Main Library">Main Library</option>
                                <option value="Batasan Library">Batasan Library</option>
                                <option value="SM Library">SM Library</option>
                            </select>
                        </div>
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-blue-800 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Update Book
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Book management functions - defined at global scope
        function editBook(bookId) {
            console.log('Edit function called for book ID:', bookId);
            
            // Get the modal elements
            const editBookModal = document.getElementById('editBookModal');
            
            // Debug URL
            const url = `../process/process_get_book.php?book_id=${bookId}`;
            console.log('Fetching URL:', url);
            
            // Fetch book details
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text().then(text => {
                        try {
                            console.log('Raw response:', text);
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Book data received:', data);
                    if (data.success) {
                        // Populate form with book details
                        document.getElementById('edit_book_id').value = data.data.book_id;
                        document.getElementById('edit_book_title').value = data.data.title;
                        document.getElementById('edit_book_condition').value = data.data.condition;
                        document.getElementById('edit_branch').value = data.data.branch || 'Main Library';
                        
                        // Show modal
                        editBookModal.style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error in fetch operation:', error);
                    alert('An error occurred while fetching book details: ' + error.message);
                });
        }

        function deleteBook(bookId) {
            if (confirm('Are you sure you want to delete this book?')) {
                // Send AJAX request to delete book
                fetch('../process/process_book_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        book_id: bookId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Book successfully deleted');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const uploadBookBtn = document.getElementById('uploadBookBtn');
            const uploadBookModal = document.getElementById('uploadBookModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const uploadBookForm = document.getElementById('uploadBookForm');
            
            // Edit book modal elements
            const editBookModal = document.getElementById('editBookModal');
            const closeEditModalBtn = document.getElementById('closeEditModalBtn');
            const editBookForm = document.getElementById('editBookForm');
            
            // Initialize event handlers for edit modal
            if (closeEditModalBtn) {
                closeEditModalBtn.addEventListener('click', function() {
                    editBookModal.style.display = 'none';
                });
            }

            // Initialize window click handler for edit modal
            window.addEventListener('click', function(event) {
                if (event.target === editBookModal) {
                    editBookModal.style.display = 'none';
                }
            });
            
            // Handle edit form submission
            if (editBookForm) {
                editBookForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    // Show loading state
                    const submitBtn = editBookForm.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.textContent;
                    submitBtn.textContent = 'Updating...';
                    submitBtn.disabled = true;
                    
                    // Send AJAX request
                    fetch('../process/process_book_edit.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Book successfully updated');
                            editBookModal.style.display = 'none';
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.textContent = originalBtnText;
                        submitBtn.disabled = false;
                    });
                });
            }
            
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

            // Modal controls
            uploadBookBtn.addEventListener('click', function() {
                uploadBookModal.style.display = 'block';
            });

            closeModalBtn.addEventListener('click', function() {
                uploadBookModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target === uploadBookModal) {
                    uploadBookModal.style.display = 'none';
                }
            });

            // Date borrowed logic
            const dateBorrowed = document.getElementById('date_borrowed');
            if (dateBorrowed) {
                // Set default to today
                const today = new Date();
                const formattedDate = today.toISOString().split('T')[0];
                dateBorrowed.value = formattedDate;
            }
            
            // Form submission
            if (uploadBookForm) {
                uploadBookForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    
                    // Create form data object
                    const formData = new FormData(this);
                    
                    // Check if all required fields are filled
                    let isValid = true;
                    const requiredFields = ['student_id', 'student_name', 'course_section', 'book_title', 'book_condition', 'date_borrowed'];
                    
                    requiredFields.forEach(field => {
                        const input = document.getElementById(field);
                        if (!input.value.trim()) {
                            isValid = false;
                            input.classList.add('border-red-500');
                        } else {
                            input.classList.remove('border-red-500');
                        }
                    });
                    
                    if (!isValid) {
                        alert('Please fill in all required fields');
                        return;
                    }
                    
                    // Show loading state
                    const submitBtn = uploadBookForm.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.textContent;
                    submitBtn.textContent = 'Processing...';
                    submitBtn.disabled = true;
                    
                    // Send AJAX request
                    fetch('../process/process_book_upload.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            alert('Book successfully added: ' + data.data.book_title);
                            
                            // Reset form
                            uploadBookForm.reset();
                            
                            // Set date back to today
                            if (dateBorrowed) {
                                const today = new Date();
                                const formattedDate = today.toISOString().split('T')[0];
                                dateBorrowed.value = formattedDate;
                            }
                            
                            // Close modal
                            uploadBookModal.style.display = 'none';
                            
                            // Refresh page to show new book
                            window.location.reload();
                        } else {
                            // Show error message
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.textContent = originalBtnText;
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html> 