<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Check for login errors
$login_errors = [];
if (isset($_SESSION['login_errors'])) {
    $login_errors = $_SESSION['login_errors'];
    // Clear errors after displaying
    unset($_SESSION['login_errors']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QCU Library Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100" x-data="app">


<div class="flex h-screen overflow-hidden">
     <div class="w-[60%] bg-gradient-to-br from-blue-400 to-blue-800 text-white p-10 flex flex-col justify-center items-center">
            <div class="max-w-2xl mx-auto text-center">
                <img src="assets/images/QCU_Logo_2019.png" alt="QCU Logo" class="w-24 h-24 mx-auto mb-4">
                <h1 class="text-3xl font-bold mb-4">QCU Library</h1>
                <p class="text-center text-lg">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit, 
                    sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. 
                    Ut enim ad minim veniam, quis nostrud exercitation ullamco 
                    laboris nisi ut aliquip ex ea commodo consequat.
                </p>
            </div>
        </div>
        
        <!-- Right Panel - Login form -->
        <div class="w-full md:w-[40%] bg-white p-6 flex items-center justify-center">
            <div class="w-full max-w-sm">
                <div class="text-center mb-6">
                    <h2 class="text-sm font-medium uppercase">WELCOME TO</h2>
                    <h1 class="text-2xl font-bold">QCU LIBRARY</h1>
                    <p class="text-gray-500 text-xs mt-2">
                        Please enter your Student ID to access library services.
                    </p>
                </div>
                
                <!-- Display error messages if any -->
                <?php if (!empty($login_errors)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                    <ul class="list-disc pl-5">
                        <?php foreach ($login_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Student ID Form -->
                <form id="studentIdForm" class="space-y-6" @submit.prevent="checkStudent">
                    <div>
                        <label for="student_id" class="block text-sm font-medium text-gray-700 mb-2">Student ID</label>
                        <input type="text" id="student_id" x-ref="studentIdInput" name="student_id" 
                               class="w-full px-4 py-3 text-lg border border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                               placeholder="Enter your student ID"
                               required>
                    </div>
                    <button type="submit" 
                            class="w-full bg-blue-500 text-white text-lg font-semibold rounded-md py-3 hover:bg-blue-600 transition duration-200">
                        Continue
                    </button>
                </form>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div x-show="showModal" 
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4"
             style="display: none;">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h2 class="text-xl font-bold mb-4">Confirm Your Details</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-600">Full Name:</label>
                        <p class="font-medium text-lg" x-text="studentDetails?.full_name"></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Course:</label>
                        <p class="font-medium text-lg" x-text="studentDetails?.course"></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Section:</label>
                        <p class="font-medium text-lg" x-text="studentDetails?.section"></p>
                    </div>
                </div>
                <div class="mt-6 space-y-3">
                    <form action="login_process.php" method="post">
                        <input type="hidden" name="student_id" x-bind:value="studentDetails?.student_id">
                        <input type="hidden" name="full_name" x-bind:value="studentDetails?.full_name">
                        <input type="hidden" name="course" x-bind:value="studentDetails?.course">
                        <input type="hidden" name="section" x-bind:value="studentDetails?.section">
                        
                        <button type="submit" 
                                class="w-full bg-blue-500 text-white text-lg font-semibold rounded-md py-3 hover:bg-blue-600">
                            Proceed to Dashboard
                        </button>
                    </form>
                    <button @click="showModal = false" 
                            class="w-full border border-gray-300 text-lg font-semibold rounded-md py-3 hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('app', () => ({
            showModal: false,
            studentDetails: null,
            async checkStudent() {
                const studentId = this.$refs.studentIdInput.value;
                
                try {
                    const response = await fetch('check_student.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `student_id=${encodeURIComponent(studentId)}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.studentDetails = data.student;
                        this.showModal = true;
                    } else {
                        alert(data.message || 'Student not found. Please check your ID.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                }
            }
        }));
    });
    </script>
</body>
</html> 