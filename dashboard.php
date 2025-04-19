<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

// Get available books
$available_books = [];
$sql = "SELECT b.reference_id, b.title, b.author, b.quality, 
               CASE WHEN bl.return_date IS NULL AND bl.borrow_date IS NOT NULL 
                    THEN 'Borrowed' ELSE 'Available' END as status
        FROM books b
        LEFT JOIN book_loans bl ON b.reference_id = bl.book_id 
            AND bl.return_date IS NULL
        ORDER BY b.title ASC";
$stmt = executeOracleQuery($sql);
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $available_books[] = $row;
    }
    closeOracleConnection($stmt);
}

// Get available facilities
$facilities = [];
$sql = "SELECT * FROM facilities";
$stmt = executeOracleQuery($sql);
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $facilities[] = $row;
    }
    closeOracleConnection($stmt);
}

// Get PC usage history
$pc_usage = [];
$sql = "SELECT p.*, f.facility_name 
        FROM pc_use p 
        JOIN pc_room pr ON p.pc_id = pr.pc_id 
        JOIN facilities f ON pr.facility_id = f.facility_id 
        WHERE p.student_id = :student_id 
        ORDER BY p.start_time DESC";
$params = [':student_id' => $_SESSION['student_id']];
$stmt = executeOracleQuery($sql, $params);
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $pc_usage[] = $row;
    }
    closeOracleConnection($stmt);
}

// Get room reservations
$room_reservations = [];
$sql = "SELECT r.*, lr.room_name 
        FROM room_reservation r 
        JOIN library_rooms lr ON r.room_id = lr.room_id 
        WHERE r.student_id = :student_id 
        ORDER BY r.start_time DESC";
$params = [':student_id' => $_SESSION['student_id']];
$stmt = executeOracleQuery($sql, $params);
if ($stmt) {
    while ($row = oci_fetch_assoc($stmt)) {
        $room_reservations[] = $row;
    }
    closeOracleConnection($stmt);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - QCU Library Management System</title>
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
                <h1 class="text-2xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="text-gray-600">Here's your library dashboard.</p>
            </div>
            
            <!-- User Info Card with Statistics -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">Student Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                    <div>
                        <p class="text-gray-600 text-sm">Student ID</p>
                        <p class="font-medium"><?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Full Name</p>
                        <p class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Course</p>
                        <p class="font-medium"><?php echo htmlspecialchars($_SESSION['course']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Section</p>
                        <p class="font-medium"><?php echo htmlspecialchars($_SESSION['section']); ?></p>
                    </div>
                </div>

                    <!-- Usage Statistics -->
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-600 text-sm">Total Books Borrowed</p>
                            <p class="font-medium">
                                <?php
                                $sql = "SELECT COUNT(*) as total FROM sys.book_loans WHERE student_id = :student_id";
                                $stmt = executeOracleQuery($sql, [':student_id' => $_SESSION['student_id']]);
                                if ($stmt) {
                                    $row = oci_fetch_assoc($stmt);
                                    echo $row['TOTAL'];
                                    closeOracleConnection($stmt);
                                } else {
                                    echo '0';
                                }
                                ?>
                                Books
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Total PC Usage</p>
                            <p class="font-medium">
                                <?php
                                $sql = "SELECT SUM(
                                            EXTRACT(HOUR FROM (END_TIME - START_TIME)) * 60 +
                                            EXTRACT(MINUTE FROM (END_TIME - START_TIME))
                                        ) as total_minutes
                                        FROM pc_use 
                                        WHERE student_id = :student_id 
                                        AND END_TIME IS NOT NULL";
                                $stmt = executeOracleQuery($sql, [':student_id' => $_SESSION['student_id']]);
                                if ($stmt) {
                                    $row = oci_fetch_assoc($stmt);
                                    $total_minutes = $row['TOTAL_MINUTES'] ?? 0;
                                    echo floor($total_minutes / 60) . 'h ' . ($total_minutes % 60) . 'm';
                                    closeOracleConnection($stmt);
                                } else {
                                    echo '0h 0m';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Room Reservations</p>
                            <p class="font-medium">
                                <?php
                                $sql = "SELECT COUNT(*) as total FROM room_reservation WHERE student_id = :student_id";
                                $stmt = executeOracleQuery($sql, [':student_id' => $_SESSION['student_id']]);
                                if ($stmt) {
                                    $row = oci_fetch_assoc($stmt);
                                    echo $row['TOTAL'];
                                    closeOracleConnection($stmt);
                                } else {
                                    echo '0';
                                }
                                ?>

                            </p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Currently Borrowed Books</p>
                            <p class="font-medium">
                                <?php
                                $sql = "SELECT COUNT(*) as total FROM sys.book_loans 
                                       WHERE student_id = :student_id AND return_date IS NULL";
                                $stmt = executeOracleQuery($sql, [':student_id' => $_SESSION['student_id']]);
                                if ($stmt) {
                                    $row = oci_fetch_assoc($stmt);
                                    echo $row['TOTAL'];
                                    closeOracleConnection($stmt);
                                } else {
                                    echo '0';
                                }
                                ?>

                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Available Books Preview -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h2 class="text-lg font-semibold">Available Books</h2>
                    <a href="books.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Books →</a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quality</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            // Show only the first 5 books in the dashboard
                            $preview_books = array_slice($available_books, 0, 5);
                            foreach ($preview_books as $book): 
                            ?>
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $book['STATUS'] === 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo htmlspecialchars($book['STATUS']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($book['STATUS'] === 'Available'): ?>
                                    <button 
                                        onclick="borrowBook('<?php echo htmlspecialchars($book['REFERENCE_ID']); ?>')"
                                        class="text-blue-600 hover:text-blue-900 font-medium">
                                        Borrow
                                    </button>
                                    <?php else: ?>
                                    <span class="text-gray-400">Borrowed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($available_books) > 5): ?>
                <div class="mt-4 text-center">
                    <a href="books.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition">
                        View All Books
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Library Facilities -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">Library Facilities</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($facilities as $facility): ?>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 cursor-pointer transition">
                        <h3 class="font-medium"><?php echo htmlspecialchars($facility['FACILITY_NAME']); ?></h3>
                        <div class="mt-2">
                            <a href="#" class="text-blue-600 hover:underline text-sm">Reserve</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Usage History Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4 border-b pb-2">Usage History</h2>
                
                <!-- Books History -->
                <div class="mb-6">
                    <h3 class="text-md font-medium mb-3">Book Borrowing History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrow Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                // Get book borrowing history
                                $book_history_sql = "SELECT b.title, bl.borrow_date, bl.return_date 
                                                   FROM sys.book_loans bl 
                                                   JOIN sys.books b ON bl.book_id = b.reference_id 
                                                   WHERE bl.student_id = :student_id 
                                                   ORDER BY bl.borrow_date DESC";
                                $stmt = executeOracleQuery($book_history_sql, [':student_id' => $_SESSION['student_id']]);
                                $has_book_history = false;
                                
                                if ($stmt) {
                                    while ($row = oci_fetch_assoc($stmt)) {
                                        $has_book_history = true;
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($row['TITLE']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('Y-m-d', strtotime($row['BORROW_DATE'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $row['RETURN_DATE'] ? date('Y-m-d', strtotime($row['RETURN_DATE'])) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $row['RETURN_DATE'] ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo $row['RETURN_DATE'] ? 'Returned' : 'Borrowed'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    closeOracleConnection($stmt);
                                }
                                
                                if (!$has_book_history) {
                                    echo '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No book borrowing history</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                </div>
            </div>
            
                <!-- PC Usage History -->
                <div class="mb-6">
                    <h3 class="text-md font-medium mb-3">PC Usage History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($pc_usage)): ?>
                                    <?php foreach ($pc_usage as $usage): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($usage['PC_ID']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('Y-m-d H:i', strtotime($usage['START_TIME'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $usage['END_TIME'] ? date('Y-m-d H:i', strtotime($usage['END_TIME'])) : 'Active'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                            if ($usage['END_TIME']) {
                                                $start = new DateTime($usage['START_TIME']);
                                                $end = new DateTime($usage['END_TIME']);
                                                $diff = $start->diff($end);
                                                echo $diff->format('%H:%I');
                                            } else {
                                                echo 'Active';
                                            }
                                            ?>
                                        </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No PC usage history</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Room Reservation History -->
                <div>
                    <h3 class="text-md font-medium mb-3">Room Reservation History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($room_reservations)): ?>
                                    <?php foreach ($room_reservations as $reservation): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($reservation['ROOM_NAME']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('Y-m-d', strtotime($reservation['START_TIME'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('H:i', strtotime($reservation['START_TIME'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('H:i', strtotime($reservation['END_TIME'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $now = new DateTime();
                                            $start = new DateTime($reservation['START_TIME']);
                                            $end = new DateTime($reservation['END_TIME']);
                                            
                                            if ($now < $start) {
                                                $status = 'Upcoming';
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                            } elseif ($now > $end) {
                                                $status = 'Completed';
                                                $statusClass = 'bg-green-100 text-green-800';
                                            } else {
                                                $status = 'Active';
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No room reservation history</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


    <!-- Add this JavaScript at the bottom of the file, before </body> -->
    <script>
    async function borrowBook(referenceId) {
        if (!confirm('Do you want to borrow this book?')) {
            return;
        }

        try {
            const response = await fetch('borrow_book.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `book_id=${encodeURIComponent(referenceId)}`
            });

            const data = await response.json();
            
            if (data.success) {
                alert('Book borrowed successfully!');
                location.reload(); // Refresh the page to update the book status
            } else {
                alert(data.message || 'Failed to borrow book. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    }

    function toggleEditMode() {
        // Implement profile editing functionality if needed
        alert('Profile editing will be implemented in a future update.');
    }
    </script>
</body>
</html> 