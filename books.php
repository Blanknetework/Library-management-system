<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get all books
$books = [];
$sql = "SELECT REFERENCE_ID, TITLE, AUTHOR, QUALITY FROM SYS.BOOKS"; 

$conn = getOracleConnection();
if (!$conn) {
    die("Connection failed");
}

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    die("Parse failed: " . htmlspecialchars($e['message']));
}

// Set the schema before executing the query
$alter_session = "ALTER SESSION SET CURRENT_SCHEMA = SYS";
$stmt_alter = oci_parse($conn, $alter_session);
oci_execute($stmt_alter);

$result = oci_execute($stmt);
if (!$result) {
    $e = oci_error($stmt);
    die("Execute failed: " . htmlspecialchars($e['message']));
}

while ($row = oci_fetch_assoc($stmt)) {
    $books[] = array(
        'REFERENCE_ID' => $row['REFERENCE_ID'],
        'TITLE' => $row['TITLE'],
        'AUTHOR' => $row['AUTHOR'],
        'QUALITY' => $row['QUALITY'],
        'STATUS' => 'Available' 
    );
}

oci_free_statement($stmt);
oci_close($conn);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - QCU Library Management System</title>
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
                            <a href="books.php" class="block py-2 px-4 bg-blue-900 rounded">Books</a>
                        </li>
                        <li class="mb-2">
                            <a href="pc_reservation.php" class="block py-2 px-4 hover:bg-blue-700 rounded">PC Reservation</a>
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
            <div class="mb-8">
                <h1 class="text-2xl font-bold mb-2">Library Books</h1>
                <p class="text-gray-600">Browse and borrow books from our collection.</p>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="relative flex-1">
                        <input 
                            type="text" 
                            id="searchInput"
                            placeholder="Search by title, author, or reference ID..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                    <div class="flex gap-4">
                        <select 
                            id="qualityFilter"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Qualities</option>
                            <option value="Good">Good</option>
                            <option value="Bad">Bad</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Books Table -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">Available Books</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quality</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($book['REFERENCE_ID']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($book['TITLE']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($book['AUTHOR']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $book['QUALITY'] === 'Good' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo htmlspecialchars($book['QUALITY']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button 
                                        onclick="borrowBook('<?php echo htmlspecialchars($book['REFERENCE_ID']); ?>')"
                                        class="text-blue-600 hover:text-blue-900 font-medium">
                                        Borrow
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- My Borrowed Books -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">My Borrowed Books</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrow Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <!-- This will be populated when we implement the borrowing functionality -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    function borrowBook(referenceId) {
        if (!confirm('Do you want to borrow this book?')) {
            return;
        }

        fetch('borrow_book.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `book_id=${encodeURIComponent(referenceId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Book borrowed successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to borrow book. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchText = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });

    // Quality filter functionality
    document.getElementById('qualityFilter').addEventListener('change', function(e) {
        const quality = e.target.value;
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            if (!quality) {
                row.style.display = '';
                return;
            }
            
            const qualityCell = row.querySelector('td:nth-child(4)');
            if (qualityCell) {
                row.style.display = qualityCell.textContent.trim() === quality ? '' : 'none';
            }
        });
    });
    </script>
</body>
</html>