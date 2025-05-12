<?php

session_start();

require_once 'config.php';

// Initialize $announcements array
$announcements = [];

// Get announcements from database
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
        WHERE status = 'active'
        ORDER BY event_date ASC";
    
    $stmt = oci_parse($conn, $sql);
    if ($stmt && oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $announcements[] = $row;
        }
        oci_free_statement($stmt);
    }
    oci_close($conn);
}

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
    <!-- Load Alpine.js from CDN with defer -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <style>
        .bg-library {
            background-image: url('assets/images/libbg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body class="bg-library min-h-screen" x-data="app()">
    <div class="min-h-screen flex">
        <!-- Two-column layout: Events on left (40%) and Login on right (60%) -->
        <div class="flex flex-col md:flex-row w-full h-screen">
            <!-- Left column for event announcements (40% width) -->
            <div class="w-full md:w-[40%] h-full flex">
                <div class="bg-white w-full flex flex-col shadow-md">
                    <div class="bg-gradient-to-r from-blue-900 to-blue-700 text-white text-center py-4 px-5 border-b border-blue-800">
                        <h2 class="text-xl font-bold tracking-wide">Event Announcements</h2>
                        <p class="text-sm text-blue-100 mt-1">Latest library events and activities</p>
                    </div>
                    
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-16 px-4 flex flex-col items-center justify-center flex-grow bg-gray-50">
                            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 max-w-sm">
                                <svg class="w-20 h-20 text-blue-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-700">No upcoming events</h3>
                                <p class="text-gray-500 mt-2">Check back later for library events and announcements.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="overflow-y-auto flex-grow p-5 bg-gray-50" style="scrollbar-width: thin;">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="mb-5 bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 border border-gray-100">
                                    <div class="bg-gradient-to-r from-blue-50 to-white px-5 py-3 flex justify-between items-center border-b border-gray-100">
                                        <h3 class="font-semibold text-blue-900 truncate mr-2"><?php echo htmlspecialchars($announcement['TITLE']); ?></h3>
                                        <div class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-sm whitespace-nowrap">
                                            <?php 
                                                $event_date = new DateTime($announcement['EVENT_DATE']);
                                                $today = new DateTime();
                                                $interval = $today->diff($event_date);
                                                
                                                if ($interval->days == 0) {
                                                    echo "Today";
                                                } else if ($interval->days == 1) {
                                                    echo "Tomorrow";
                                                } else {
                                                    echo "In " . $interval->days . " days";
                                                }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="p-5">
                                        <div class="flex flex-wrap items-center text-sm text-gray-500 mb-4">
                                            <div class="flex items-center mr-5 mb-2">
                                                <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="font-medium"><?php echo htmlspecialchars($announcement['EVENT_DATE']); ?></span>
                                            </div>
                                            
                                            <?php if (!empty($announcement['LOCATION'])): ?>
                                                <div class="flex items-center mb-2">
                                                    <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                    <span class="font-medium"><?php echo htmlspecialchars($announcement['LOCATION']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-gray-700 text-sm mb-4 leading-relaxed border-l-2 border-blue-100 pl-3">
                                            <?php
                                                $description = $announcement['DESCRIPTION'];
                                                // Convert Oracle LOB to string if needed
                                                if (is_object($description) && method_exists($description, 'load')) {
                                                    $description = $description->load();
                                                }
                                                echo nl2br(htmlspecialchars(strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description));
                                            ?>
                                        </p>
                                        
                                        <div x-data="{ showDetails: false }">
                                            <button 
                                                @click="showDetails = !showDetails" 
                                                class="text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-1 px-3 rounded-full inline-flex items-center transition-colors duration-200"
                                            >
                                                <span x-show="!showDetails">Read more</span>
                                                <span x-show="showDetails">Show less</span>
                                                <svg x-show="!showDetails" class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                                <svg x-show="showDetails" class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                </svg>
                                            </button>
                                            
                                            <div x-show="showDetails" class="mt-4 text-sm text-gray-700 bg-blue-50 p-4 rounded-lg border border-blue-100">
                                                <?php 
                                                    // Convert Oracle LOB to string if needed
                                                    $full_description = $announcement['DESCRIPTION'];
                                                    if (is_object($full_description) && method_exists($full_description, 'load')) {
                                                        $full_description = $full_description->load();
                                                    }
                                                    echo nl2br(htmlspecialchars($full_description)); 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right column for login (60% width) -->
            <div class="w-full md:w-[60%] flex flex-col items-center justify-start pt-20 md:pt-24 pl-0 md:pl-8">
                <div class="text-center text-white mb-8">
            <img src="assets/images/QCU_Logo_2019.png" alt="QCU Logo" class="w-20 h-20 mx-auto mb-2">
            <h1 class="text-2xl font-bold mb-1">QCU Library</h1>
            <p class="text-sm italic">
                "Empowering the students of Quezon City University through knowledge<br>
                and resources for academic excellence."
            </p>
        </div>
        
        <!-- Login Form Card -->
                <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 mt-4">
            <div class="text-center mb-6">
                <h2 class="text-gray-600 text-sm mb-1">WELCOME TO</h2>
                <h1 class="text-blue-900 text-2xl font-bold mb-2">QCU LIBRARY!</h1>
                <p class="text-gray-600 text-sm">Enter your student number to log-in</p>
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
                    <form id="studentIdForm" class="space-y-4" @submit.prevent="checkStudent">
                <div>
                    <input type="text" 
                           id="student_id" 
                           x-ref="studentIdInput"
                           autocomplete="off" 
                           name="student_id" 
                           class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           placeholder="Student Number"
                           required>
                </div>
                        <button type="submit" 
                        class="w-full bg-blue-900 text-white font-medium rounded py-2 hover:bg-blue-800 transition duration-200">
                    CONTINUE
                </button>
            </form>
            
            <!-- Registration Link -->
            <div class="text-center mt-4 pt-4 border-t border-gray-200">
                <p class="text-gray-600 text-sm">Don't have a library account?</p>
                <button @click="showRegistrationModal = true" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium mt-1">
                    Register here
                </button>
            </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div x-show="showRegistrationModal" 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
         style="display: none;">
        <div class="bg-white rounded-lg max-w-xl w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-blue-900">Library Account Registration</h2>
                <button @click="showRegistrationModal = false" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="registrationForm" class="space-y-4" @submit.prevent="submitRegistration">
                <div>
                    <label for="reg_student_id" class="block text-sm font-medium text-gray-700 mb-1">Student Number</label>
                    <input type="text" 
                           id="reg_student_id" 
                           x-model="regStudentId"
                           autocomplete="off"
                           class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           placeholder="e.g., 23-2444"
                           required>
                </div>
                
                <div>
                    <label for="reg_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name (Last Name, First Name, M.I.)</label>
                    <input type="text" 
                           id="reg_name" 
                           x-model="regName"
                           autocomplete="off"
                           class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           placeholder="e.g., Dela Cruz, Juan P."
                           required>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="reg_course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                        <input type="text" 
                               id="reg_course" 
                               x-model="regCourse"
                               autocomplete="off"
                               class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                               placeholder="e.g., BSIT"
                               required>
                    </div>
                    <div>
                        <label for="reg_section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <input type="text" 
                               id="reg_section" 
                               x-model="regSection"
                               autocomplete="off"
                               class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                               placeholder="e.g., 2A"
                               required>
                    </div>
                </div>
                
                <div>
                    <label for="reg_contact" class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                    <input type="text" 
                           id="reg_contact" 
                           x-model="regContact"
                           autocomplete="off"
                           class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           placeholder="e.g., 09123456789"
                           pattern="[0-9]{11}"
                           title="Please enter a valid 11-digit phone number"
                           required>
                    <p class="text-xs text-gray-500 mt-1">Please enter a valid 11-digit phone number (e.g., 09123456789)</p>
                </div>
                
                <div>
                    <label for="reg_address" class="block text-sm font-medium text-gray-700 mb-1">Complete Address</label>
                    <textarea 
                        id="reg_address" 
                        x-model="regAddress"
                        autocomplete="off"
                        class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="Enter your complete address"
                        rows="2"
                        required></textarea>
                </div>
                
                <div>
                    <label for="reg_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" 
                           id="reg_email" 
                           x-model="regEmail"
                           autocomplete="off"
                           class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           placeholder="e.g., juandelacruz@email.com"
                           required>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" 
                            @click="showRegistrationModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-900 text-white rounded-md hover:bg-blue-800">
                        Register
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- First Modal (Confirmation) -->
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
                <button @click="showReservationModal = true; showModal = false" 
                        class="w-full bg-blue-900 text-white text-lg font-semibold rounded-md py-3 hover:bg-blue-800">
                    Proceed
                </button>
                <button @click="showModal = false" 
                        class="w-full border border-gray-300 text-lg font-semibold rounded-md py-3 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Second Modal (Reservation) -->
    <div x-show="showReservationModal" 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-lg w-[800px] p-10">
            <!-- Reservation Options -->
            <div class="flex gap-4 mb-6">
                <button @click="activeTab = 'pc'; resetForm()" 
                        :class="activeTab === 'pc' ? 'bg-blue-900 text-white' : 'bg-white text-blue-900 border border-blue-900'"
                        class="flex items-center gap-2 px-4 py-2 rounded text-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    PC RESERVATION
                </button>
                <button @click="activeTab = 'room'; resetForm()"
                        :class="activeTab === 'room' ? 'bg-blue-900 text-white' : 'bg-white text-blue-900 border border-blue-900'"
                        class="flex items-center gap-2 px-4 py-2 rounded text-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    LIBRARY ROOM RESERVATION
                </button>
                <button @click="activeTab = 'book'; resetForm()"
                        :class="activeTab === 'book' ? 'bg-blue-900 text-white' : 'bg-white text-blue-900 border border-blue-900'"
                        class="flex items-center gap-2 px-4 py-2 rounded text-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4v12l-4-2-4 2V4M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    BOOK BORROWING
                </button>
            </div>
            
            <div :class="{'grid grid-cols-2 gap-4': activeTab !== 'book', 'block': activeTab === 'book'}">
                <!-- Column content -->
                <div class="space-y-3">
                    <!-- PC Reservation Form -->
                    <template x-if="activeTab === 'pc'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Select a PC from the available PC's on the right
                                </label>
                                <select x-model="selectedPC" class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                    <option value="">PC Number</option>
                                    <template x-for="i in 19" :key="i">
                                        <option :value="i" 
                                                :disabled="pcStatus[i] && (pcStatus[i] === 'ACTIVE' || pcStatus[i] === 'UPCOMING_TODAY' || pcStatus[i] === 'PENDING' || pcStatus[i] === 'PENDING_UPCOMING')" 
                                                :class="pcStatus[i] === 'ACTIVE' ? 'text-red-500' : pcStatus[i] === 'PENDING' ? 'text-yellow-500' : 'text-green-500'"
                                                x-text="'PC ' + i + ' - ' + (pcStatus[i] === 'ACTIVE' ? 'Occupied' : pcStatus[i] === 'PENDING' ? 'Pending' : 'Available')">
                                        </option>
                                    </template>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                                <select x-model="purpose" class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                    <option value="">Select purpose...</option>
                                    <option value="research">Research</option>
                                    <option value="assignment">Assignment</option>
                                    <option value="browsing">Internet Browsing</option>
                                    <option value="printing">Printing</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Start time</label>
                                        <input type="time" 
                                            x-model="startTime" 
                                            @change="validateAndSetEndTime"
                                            class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">End time</label>
                                        <input type="time" 
                                            x-model="endTime" 
                                            :min="minEndTime"
                                            :max="maxEndTime"
                                            :disabled="!startTime"
                                            class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                    </div>
                            </div>
                            
                            <button @click="submitReservation" 
                                    class="w-full bg-blue-900 text-white py-2 rounded text-sm mt-2">
                                    CONFIRM PC RESERVATION
                                </button>
                        </div>
                    </template>

                    <!-- Room Reservation Form -->
                    <template x-if="activeTab === 'room'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Select a Room
                                </label>
                                <select x-model="selectedRoom" class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                    <option value="">Room Number</option>
                                    <template x-for="i in 5" :key="i">
                                        <option :value="i" 
                                            :disabled="roomStatus[i] === 'ACTIVE' || roomStatus[i] === 'OCCUPIED' || roomStatus[i] === 'PENDING' || roomStatus[i] === 'UPCOMING_TODAY'"
                                                :class="{
                                                'text-red-500': roomStatus[i] === 'ACTIVE' || roomStatus[i] === 'OCCUPIED',
                                                'text-yellow-500': roomStatus[i] === 'PENDING',
                                                'text-blue-500': roomStatus[i] === 'UPCOMING_TODAY',
                                                'text-green-500': !roomStatus[i] || roomStatus[i] === 'AVAILABLE'
                                                }"
                                            x-text="'Room ' + i + ' - ' + getRoomStatusText(roomStatus[i])">
                                        </option>
                                    </template>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                                <select x-model="roomPurpose" class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                    <option value="">Select purpose...</option>
                                    <option value="group_study">Group Study</option>
                                    <option value="team_meeting">Team Meeting</option>
                                    <option value="presentation">Presentation Practice</option>
                                    <option value="discussion">Group Discussion</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start time</label>
                                    <input type="time" 
                                        x-model="roomStartTime" 
                                        @change="validateAndSetRoomEndTime"
                                        class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">End time</label>
                                    <input type="time" 
                                        x-model="roomEndTime" 
                                        :min="minRoomEndTime"
                                        :max="maxRoomEndTime"
                                        :disabled="!roomStartTime"
                                        class="w-full p-1.5 border-2 border-blue-900 rounded text-sm">
                                </div>
                            </div>
                            
                            <button @click="submitRoomReservation" 
                                    class="w-full bg-blue-900 text-white py-2 rounded text-sm mt-2">
                                CONFIRM ROOM RESERVATION
                        </button>
                        </div>
                    </template>
                    
                    <!-- Book Borrowing Form -->
                    <template x-if="activeTab === 'book'">
                        <div class="space-y-6 max-h-[600px] overflow-y-auto col-span-full w-full px-4">
                            <!-- Header and Search -->
                            <div class="flex flex-col space-y-4 sticky top-0 bg-white z-10 py-4 border-b">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-2xl font-bold text-blue-900">Book Borrowing</h2>
                                    <!-- Book Status Summary -->
                                    <div class="flex gap-6">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                            <span class="text-sm font-medium text-gray-600">Available:</span>
                                            <span class="text-sm font-bold text-gray-800" x-text="filteredBooks.filter(b => b.availability === 'Available').length + ' books'"></span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                            <span class="text-sm font-medium text-gray-600">Borrowed:</span>
                                            <span class="text-sm font-bold text-gray-800" x-text="filteredBooks.filter(b => b.availability !== 'Available').length + ' books'"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Search and Filter Section -->
                                <div class="flex flex-col md:flex-row gap-4">
                                <!-- Search Box -->
                                    <div class="relative w-full md:w-2/3">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <input type="text" 
                                        x-model="bookSearch" 
                                        @input="searchBooks"
                                        class="w-full pl-12 pr-4 py-3 text-sm border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out"
                                        placeholder="Search books by title or author...">
                                    </div>
                                    
                                    <!-- Filters -->
                                    <div class="flex flex-row gap-2 md:w-1/3">
                                        <!-- Condition Filter -->
                                        <select 
                                            x-model="conditionFilter" 
                                            @change="applyFilters()"
                                            class="w-1/2 px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="">All Conditions</option>
                                            <option value="Good">Good</option>
                                            <option value="Bad">Bad</option>
                                        </select>
                                        
                                        <!-- Branch Filter -->
                                        <select 
                                            x-model="branchFilter" 
                                            @change="applyFilters()"
                                            class="w-1/2 px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="">All Branches</option>
                                            <option value="Main Library">Main Library</option>
                                            <option value="Batasan Library">Batasan Library</option>
                                            <option value="SM Library">SM Library</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Books Grid -->
                            <div class="grid grid-cols-2 gap-6">
                                <template x-for="book in filteredBooks" :key="book.book_id">
                                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200 transform hover:-translate-y-1">
                                        <!-- Book Title -->
                                        <div class="p-4 border-b bg-gray-50">
                                            <h3 class="font-semibold text-lg text-blue-900 line-clamp-2" x-text="book.title"></h3>
                                        </div>
                                        
                                        <!-- Book Details -->
                                        <div class="p-4 space-y-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-500 font-medium min-w-[4rem]">Author:</span>
                                                    <span class="text-gray-800" x-text="book.author"></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-500 font-medium min-w-[4rem]">Condition:</span>
                                                    <span class="text-gray-800" x-text="book.condition || 'Not specified'"></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-500 font-medium min-w-[4rem]">Branch:</span>
                                                    <span class="text-gray-800" x-text="book.branch || 'Main Library'"></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-500 font-medium min-w-[4rem]">Status:</span>
                                                    <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium"
                                                        :class="book.availability === 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                                        x-text="book.availability">
                                                    </span>
                                                </div>
                                            </div>
                                            <button @click="requestBook(book)"
                                                :disabled="book.availability !== 'Available'"
                                                :class="book.availability === 'Available' ? 'bg-blue-900 hover:bg-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                                                class="w-full py-2.5 px-4 text-center font-medium rounded-lg transition duration-150 ease-in-out">
                                                Request Book
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- No results message -->
                            <div x-show="filteredBooks.length === 0" class="text-center py-8">
                                <div class="text-gray-400 mb-2">
                                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <p class="text-gray-500 text-lg">No books match your search.</p>
                                <p class="text-gray-400">Try different keywords or browse all books.</p>
                            </div>
                            
                            <!-- My Borrowing Requests -->
                            <div class="mt-8 bg-white rounded-lg border border-gray-200 overflow-hidden">
                                <div class="p-4 bg-gray-50 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-blue-900">My Borrowing Requests</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-blue-900 text-white">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Book Title</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Request Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <template x-for="request in borrowingRequests" :key="request.request_id">
                                                <tr class="hover:bg-gray-50 transition duration-150">
                                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800" x-text="request.title"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600" x-text="request.request_date"></td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span :class="{
                                                            'bg-yellow-100 text-yellow-800': request.status === 'Pending',
                                                            'bg-green-100 text-green-800': request.status === 'Approved',
                                                            'bg-red-100 text-red-800': request.status === 'Rejected'
                                                        }" class="px-3 py-1 rounded-full text-xs font-medium" x-text="request.status">
                                                        </span>
                                                    </td>
                                                </tr>
                                            </template>
                                            <!-- Empty state -->
                                            <tr x-show="borrowingRequests.length === 0">
                                                <td colspan="3" class="px-6 py-8 text-center">
                                                    <p class="text-gray-500 text-sm">You don't have any borrowing requests yet.</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Borrowing Policy and Library Hours -->
                            <div class="grid grid-cols-2 gap-6 mt-8 mb-6">
                                <div class="bg-white p-6 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-lg text-blue-900 mb-4">Library Hours</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                            <span class="text-gray-600">Monday - Friday:</span>
                                            <span class="text-gray-800 font-medium">7:00 AM - 5:00 PM</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                            <span class="text-gray-600">Saturday:</span>
                                            <span class="text-gray-800 font-medium">7:00 AM - 5:00 PM</span>
                                        </div>
                                        <div class="flex justify-between items-center py-1">
                                            <span class="text-gray-600">Sunday:</span>
                                            <span class="text-gray-800 font-medium">Closed</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white p-6 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-lg text-blue-900 mb-4">Borrowing Policy</h4>
                                    <ul class="space-y-2">
                                        <li class="flex items-center gap-2 text-gray-700">
                                            <svg class="w-5 h-5 text-blue-900 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Maximum of 3 books per student
                                        </li>
                                        <li class="flex items-center gap-2 text-gray-700">
                                            <svg class="w-5 h-5 text-blue-900 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Borrowing period: 2 days
                                        </li>
                                        <li class="flex items-center gap-2 text-gray-700">
                                            <svg class="w-5 h-5 text-blue-900 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Renewal possible if no reservation
                                        </li>
                                        <li class="flex items-center gap-2 text-gray-700">
                                            <svg class="w-5 h-5 text-blue-900 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Fine for late returns: ₱10/day
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Right Column - Status Display -->
                <div x-show="activeTab !== 'book'">
                    <!-- Announcements Status -->
                    <template x-if="activeTab === 'announcements'">
                        <!-- Right column removed as requested -->
                    </template>

                    <!-- PC Status -->
                    <template x-if="activeTab === 'pc'">
                    <div class="border rounded">
                        <h3 class="text-center font-bold py-2 bg-gray-50 border-b text-sm">AVAILABLE PC's</h3>
                        <div class="overflow-y-auto max-h-[300px]">
                            <table class="w-full">
                                <thead class="bg-blue-900 text-white sticky top-0">
                                    <tr>
                                        <th class="py-1.5 px-3 text-sm">PC</th>
                                        <th class="py-1.5 px-3 text-sm">PC STATUS</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <template x-for="i in 19" :key="i">
                                        <tr class="border-b">
                                            <td class="py-1.5 px-3 text-center" x-text="'PC ' + i"></td>
                                            <td class="py-1.5 px-3 text-center">
                                                <span x-effect="pcStatus[i]"
                                                      :class="{
                                                          'text-red-500': pcStatus[i] === 'ACTIVE',
                                                          'text-yellow-500': pcStatus[i] === 'PENDING',
                                                          'text-blue-500': pcStatus[i] === 'UPCOMING_TODAY' || pcStatus[i] === 'PENDING_UPCOMING',
                                                          'text-green-500': !pcStatus[i]
                                                      }"
                                                      x-text="getPCStatusText(pcStatus[i])">
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </template>

                    <!-- Room Status -->
                    <template x-if="activeTab === 'room'">
                        <div class="border rounded">
                            <h3 class="text-center font-bold py-2 bg-gray-50 border-b text-sm">AVAILABLE ROOMS</h3>
                            <div class="overflow-y-auto max-h-[300px]">
                                <table class="w-full">
                                    <thead class="bg-blue-900 text-white sticky top-0">
                                        <tr>
                                            <th class="py-1.5 px-3 text-sm">ROOM</th>
                                            <th class="py-1.5 px-3 text-sm">STATUS</th>
                                            <th class="py-1.5 px-3 text-sm">CAPACITY</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm">
                                        <template x-for="i in 5" :key="i">
                                            <tr class="border-b">
                                                <td class="py-1.5 px-3 text-center" x-text="'Room ' + i"></td>
                                                <td class="py-1.5 px-3 text-center">
                                                <span
                                                    :class="{
                                                        'text-red-500': roomStatus[i] === 'ACTIVE',
                                                        'text-yellow-500': roomStatus[i] === 'PENDING',
                                                        'text-blue-500': roomStatus[i] === 'UPCOMING_TODAY',
                                                        'text-green-500': !roomStatus[i] || roomStatus[i] === 'AVAILABLE'
                                                    }"
                                                    x-text="getRoomStatusText(roomStatus[i])">
                                                </span>
                                                </td>
                                                <td class="py-1.5 px-3 text-center" x-text="'10-15 persons'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>


    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('app', () => ({
            showModal: false,
            showReservationModal: false,
            activeTab: 'pc',
            studentDetails: null,
            // PC reservation data
            selectedPC: '',
            purpose: '',
            startTime: '',
            endTime: '',
            pcStatusData: {},
            minEndTime: '',
            maxEndTime: '',
            // Room reservation data
            selectedRoom: '',
            roomPurpose: '',
            roomStartTime: '',
            roomEndTime: '',
            roomStatusData: {},
            minRoomEndTime: '',
            maxRoomEndTime: '',
            statusRefreshInterval: null,
            isLoggedIn: false,
            bookSearch: '',
            books: [],
            filteredBooks: [],
            borrowingRequests: [],
            pcRequests: [],
            todaysReservations: [],
            // Track pending room reservations made by this user that may not be on server yet
            pendingRoomRequests: [],
            conditionFilter: '',
            branchFilter: '',
            showRegistrationModal: false,
            regStudentId: '',
            regName: '',
            regCourse: '',
            regSection: '',
            regContact: '',
            regAddress: '',
            regEmail: '',

            // Add this method to initialize our checkStudent function
            checkStudent: function() {
                console.log('checkStudent function called');
                const studentId = this.$refs.studentIdInput.value.trim();
                console.log('Student ID:', studentId);
                
                if (!studentId) {
                    alert('Please enter your Student ID.');
                    return;
                }
                
                // Debug the exact request being sent
                console.log('Sending request to check_student.php with data:', JSON.stringify({ student_id: studentId }));
                
                // Use a regular Promise pattern instead of async/await to avoid any issues
                fetch('check_student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ student_id: studentId })
                })
                .then(response => {
                    console.log('Response received:', response);
                    // Log the raw response for debugging
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            // Convert text back to JSON
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Failed to parse JSON:', e);
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        this.studentDetails = data.student;
                        this.showModal = true;
                        this.loadPCStatus();
                        this.loadBooks();
                        this.loadBorrowingRequests();
                        this.isLoggedIn = true;
                    } else {
                        alert(data.message || 'Student not found. Please check your ID.');
                    }
                })
                .catch(error => {
                    console.error('Error in checkStudent function:', error);
                    alert('An error occurred: ' + error.message);
                    
                    // Additional debugging
                    if (error.name === 'SyntaxError') {
                        alert('Server returned invalid JSON. Check check_student.php for errors.');
                    } else if (error.message.includes('Failed to fetch')) {
                        alert('Network error. Check if check_student.php exists and server is running.');
                    }
                });
            },

            get pcStatus() {
                return this.pcStatusData;
            },

            set pcStatus(value) {
                this.pcStatusData = { ...value };
            },

          
            get roomStatus() {
                return this.roomStatusData;
            },

            set roomStatus(value) {
                this.roomStatusData = { ...value };
            },

            init() {
               
                const initialPCStatus = {};
                for (let i = 1; i <= 19; i++) {
                    initialPCStatus[i] = null;
                }
                this.pcStatus = initialPCStatus;

                const initialRoomStatus = {};
                for (let i = 1; i <= 5; i++) {
                    initialRoomStatus[i] = null;
                }
                this.roomStatus = initialRoomStatus;
                this.roomStatusData = initialRoomStatus;
                
               
                this.loadPCStatus();
                this.loadRoomStatus();
                
             
                this.statusRefreshInterval = setInterval(() => {
                    this.loadPCStatus();
                    this.loadRoomStatus();
                }, 30000);

               
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.loadPCStatus();
                        this.loadRoomStatus();
                    }
                });

                window.addEventListener('focus', () => {
                    this.loadPCStatus();
                    this.loadRoomStatus();
                });
            },

            destroy() {
                if (this.statusRefreshInterval) {
                    clearInterval(this.statusRefreshInterval);
                }
            },

            resetForm() {
                if (this.activeTab === 'pc') {
                    this.selectedPC = '';
                    this.purpose = '';
                    this.startTime = '';
                    this.endTime = '';
                } else {
                    this.selectedRoom = '';
                    this.roomPurpose = '';
                    this.roomStartTime = '';
                    this.roomEndTime = '';
                }
            },

            markPCAsOccupied(pcId) {
                const newStatus = { ...this.pcStatusData };
                newStatus[pcId] = true;
                this.pcStatus = newStatus;
                
             
                this.$nextTick(() => {
                    console.log(`PC ${pcId} marked as occupied:`, this.pcStatusData);
                    
                   
                    const pcStatusElements = document.querySelectorAll(`[x-text="pcStatus[i] ? 'Occupied' : 'Available'"]`);
                    pcStatusElements.forEach(el => {
                        if (el.closest('tr') && el.closest('tr').querySelector('td').textContent.includes(`PC ${pcId}`)) {
                            el.textContent = 'Occupied';
                            el.classList.remove('text-green-500');
                            el.classList.add('text-red-500');
                        }
                    });
                });
            },

            async loadPCStatus() {
                try {
                    const response = await fetch('get_pc_status.php?t=' + new Date().getTime());
                    const data = await response.json();
                    
                    if (data.success) {
                        const newStatus = {};
                        // Set all to null (available) by default
                        for (let i = 1; i <= 19; i++) {
                            newStatus[i] = null;
                        }
                        
                        // Priority: ACTIVE > PENDING > UPCOMING_TODAY > PENDING_UPCOMING
                        data.active_reservations.forEach(res => {
                            const pcId = parseInt(res.pc_id);
                            if (pcId >= 1 && pcId <= 19) {
                                if (res.status === 'ACTIVE') {
                                    newStatus[pcId] = 'ACTIVE';
                                } else if (res.status === 'PENDING') {
                                    // Only set to PENDING if not already ACTIVE
                                    if (!newStatus[pcId]) newStatus[pcId] = 'PENDING';
                                } else if (res.status === 'UPCOMING_TODAY') {
                                    if (!newStatus[pcId]) newStatus[pcId] = 'UPCOMING_TODAY';
                                } else if (res.status === 'PENDING_UPCOMING') {
                                    if (!newStatus[pcId]) newStatus[pcId] = 'PENDING_UPCOMING';
                                }
                            }
                        });
                        
                            this.pcStatus = newStatus;
                        }
                } catch (error) {
                    console.error('Error loading PC status:', error);
                }
            },

            async loadPCRequests() {
                try {
                    const response = await fetch('get_pc_requests.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ student_id: this.studentDetails.student_id })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.pcRequests = data.requests;
                    }
                } catch (error) {
                    console.error('Error loading PC requests:', error);
                }
            },

            validateAndSetEndTime() {
                if (!this.startTime) {
                    this.endTime = '';
                    return;
                }

              
                const today = new Date();
                const [startHours, startMinutes] = this.startTime.split(':').map(Number);
                
                // Set start time
                const startDate = new Date(today);
                startDate.setHours(startHours, startMinutes, 0);

                // Set minimum end time (1 hour after start)
                const minDate = new Date(startDate);
                minDate.setHours(startDate.getHours() + 1);
                
                // Set maximum end time (2 hours after start)
                const maxDate = new Date(startDate);
                maxDate.setHours(startDate.getHours() + 2);

                
                this.minEndTime = minDate.getHours().toString().padStart(2, '0') + ':' + 
                                 minDate.getMinutes().toString().padStart(2, '0');
                this.maxEndTime = maxDate.getHours().toString().padStart(2, '0') + ':' + 
                                 maxDate.getMinutes().toString().padStart(2, '0');

              
                this.endTime = this.minEndTime;
            },

            async submitReservation() {
                if (!this.selectedPC || !this.purpose || !this.startTime || !this.endTime) {
                    alert('Please fill in all fields');
                    return;
                }

                try {
                    // Check current status
                    await this.loadPCStatus();
                    if (this.pcStatus[this.selectedPC]) {
                        alert('This PC is no longer available. Please choose another PC.');
                        this.selectedPC = '';
                        return;
                    }

                    const response = await fetch('process/process_pc_reservation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            pc_id: this.selectedPC,
                            student_id: this.studentDetails.student_id,
                            purpose: this.purpose,
                            start_time: this.startTime,
                            end_time: this.endTime
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        alert('PC reservation request submitted successfully! Please wait for admin approval.');
                    
                        this.selectedPC = '';
                        this.purpose = '';
                        this.startTime = '';
                        this.endTime = '';
                        this.minEndTime = '';
                        this.maxEndTime = '';
                       
                        await this.loadPCStatus();
                        await this.loadPCRequests();
                    } else {
                        throw new Error(data.message || 'Failed to make reservation');
                    }
                } catch (error) {
                    console.error('Error making reservation:', error);
                    alert('Error making reservation: ' + error.message);
                    await this.loadPCStatus();
                }
            },

            async loadRoomStatus() {
                try {
                    const response = await fetch('get_room_status.php?t=' + new Date().getTime());
                    const data = await response.json();
                    
                    if (data.success && data.room_status_map) {
                        // Save a copy of the current status to preserve pending states
                        const currentStatus = { ...this.roomStatus };
                        
                        // Update with server data
                        const newStatus = { ...data.room_status_map };
                        
                        // Preserve any local PENDING states that might not be reflected on the server yet
                            for (let i = 1; i <= 5; i++) {
                                if (currentStatus[i] === 'PENDING' && newStatus[i] === 'AVAILABLE') {
                                    newStatus[i] = 'PENDING';
                            }
                        }
                        
                        // Apply any pending room requests from this session
                        if (Array.isArray(this.pendingRoomRequests) && this.pendingRoomRequests.length > 0) {
                            // Keep pending requests for up to 1 hour (3600000 ms)
                            const now = new Date().getTime();
                            // Filter out expired requests (older than 1 hour)
                            this.pendingRoomRequests = this.pendingRoomRequests.filter(req => {
                                return (now - req.timestamp) < 3600000;
                            });
                            
                            // Apply remaining pending requests
                            this.pendingRoomRequests.forEach(req => {
                                const roomId = parseInt(req.room_id);
                                if (roomId >= 1 && roomId <= 5) {
                                    // Only override if not already ACTIVE or UPCOMING_TODAY
                                    if (newStatus[roomId] !== 'ACTIVE' && newStatus[roomId] !== 'UPCOMING_TODAY') {
                                        newStatus[roomId] = 'PENDING';
                                    }
                                }
                            });
                        }
                        
                        this.roomStatus = newStatus;
                        this.roomStatusData = { ...newStatus };
                        this.todaysReservations = data.todays_reservations || [];
                        
                        // Add our pending requests to today's reservations if they're not already there
                        if (Array.isArray(this.pendingRoomRequests) && this.pendingRoomRequests.length > 0) {
                            this.pendingRoomRequests.forEach(req => {
                                const roomId = parseInt(req.room_id);
                                // Check if this request is already in todaysReservations
                                const exists = this.todaysReservations.some(res => {
                                    return parseInt(res.room_id) === roomId && 
                                           res.start_time === req.start_time && 
                                           res.end_time === req.end_time;
                                });
                                
                                if (!exists) {
                                    this.todaysReservations.push({
                                        room_id: roomId.toString(),
                                        status: 'Pending',
                                        start_time: req.start_time,
                                        end_time: req.end_time
                                    });
                                }
                            });
                        }
                    }
                    // Optionally, handle clearing the form if the selected room is no longer available
                    if (this.selectedRoom && (this.roomStatus[this.selectedRoom] === 'ACTIVE' || this.roomStatus[this.selectedRoom] === 'UPCOMING_TODAY')) {
                            this.selectedRoom = '';
                            this.roomPurpose = '';
                            this.roomStartTime = '';
                            this.roomEndTime = '';
                            alert('Selected room is no longer available. Please choose another room.');
                    }
                } catch (error) {
                    console.error('Error loading room status:', error);
                }
            },

            validateAndSetRoomEndTime() {
                if (!this.roomStartTime) {
                    this.roomEndTime = '';
                    return;
                }

                const today = new Date();
                const [startHours, startMinutes] = this.roomStartTime.split(':').map(Number);
                
                const startDate = new Date(today);
                startDate.setHours(startHours, startMinutes, 0);

                // Set minimum end time (2 hours after start)
                const minDate = new Date(startDate);
                minDate.setHours(startDate.getHours() + 2);
                
                // Set maximum end time (4 hours after start)
                const maxDate = new Date(startDate);
                maxDate.setHours(startDate.getHours() + 4);

                this.minRoomEndTime = minDate.getHours().toString().padStart(2, '0') + ':' + 
                                    minDate.getMinutes().toString().padStart(2, '0');
                this.maxRoomEndTime = maxDate.getHours().toString().padStart(2, '0') + ':' + 
                                    maxDate.getMinutes().toString().padStart(2, '0');

               
                this.roomEndTime = this.minRoomEndTime;
            },

            markRoomAsOccupied(roomId) {
                console.log(`Marking room ${roomId} as occupied`);
                const newStatus = { ...this.roomStatusData };
                newStatus[roomId] = 'ACTIVE';
                this.roomStatus = newStatus;
                
                
                this.$nextTick(() => {
                    console.log(`Room ${roomId} status after update:`, this.roomStatus[roomId]);
                });
            },

            async submitRoomReservation() {
                if (!this.selectedRoom || !this.roomPurpose || !this.roomStartTime || !this.roomEndTime) {
                    alert('Please fill in all fields');
                    return;
                }

                try {
                    if (!this.studentDetails || !this.studentDetails.student_id) {
                        throw new Error('Student details not found. Please try logging in again.');
                    }

                    // First check if the room is already marked as occupied or pending in our status
                    if (this.roomStatus[this.selectedRoom] === 'ACTIVE' || this.roomStatus[this.selectedRoom] === 'OCCUPIED') {
                        alert('This room is currently occupied. Please choose another room.');
                        return;
                    }
                    
                    if (this.roomStatus[this.selectedRoom] === 'PENDING') {
                        alert('This room already has a pending reservation. Please choose another room.');
                        return;
                    }
                    
                    if (this.roomStatus[this.selectedRoom] === 'UPCOMING_TODAY') {
                        alert('This room is already reserved for today. Please choose another room.');
                        return;
                    }

                    // Then check for time conflicts as a secondary validation
                    if (this.hasApprovedConflict(this.selectedRoom, this.roomStartTime, this.roomEndTime)) {
                        alert('This room is already reserved for this time period (approved reservation)');
                        return;
                    }
                    if (this.hasPendingConflict(this.selectedRoom, this.roomStartTime, this.roomEndTime)) {
                        alert('This room already has a pending reservation for this time period');
                        return;
                    }

                    const requestData = {
                        room_id: this.selectedRoom,
                        student_id: this.studentDetails.student_id,
                        purpose: this.roomPurpose,
                        start_time: this.roomStartTime,
                        end_time: this.roomEndTime
                    };

                    console.log('Sending room reservation request:', requestData);

                    const response = await fetch('process/process_room_reservation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(requestData)
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Server response:', errorText);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('Server response:', data);
                    
                    if (data.success) {
                        // Update the room status immediately to show PENDING
                        const newStatus = { ...this.roomStatusData };
                        newStatus[this.selectedRoom] = 'PENDING';
                        this.roomStatus = newStatus;
                        
                        // Add to today's reservations
                        if (Array.isArray(this.todaysReservations)) {
                            this.todaysReservations.push({
                                room_id: this.selectedRoom,
                                status: 'Pending',
                                start_time: this.roomStartTime,
                                end_time: this.roomEndTime
                            });
                        }
                        
                        // Add to pending room requests to persist across refreshes
                        this.pendingRoomRequests.push({
                            room_id: this.selectedRoom,
                            start_time: this.roomStartTime,
                            end_time: this.roomEndTime,
                            timestamp: new Date().getTime()
                        });
                        
                        // Clear form fields first
                        this.selectedRoom = '';
                        this.roomPurpose = '';
                        this.roomStartTime = '';
                        this.roomEndTime = '';
                        this.minRoomEndTime = '';
                        this.maxRoomEndTime = '';
                        
                        // Now we can safely refresh from server because we track pending requests
                        await this.loadRoomStatus();
                        
                        alert(`Room ${requestData.room_id} has been successfully reserved\nStart: ${data.data.stored_start_time}\nEnd: ${data.data.stored_end_time}`);
                    } else {
                        throw new Error(data.message || 'Failed to make room reservation');
                    }
                } catch (error) {
                    console.error('Error making room reservation:', error);
                    alert('Error making room reservation: ' + error.message);
                    await this.loadRoomStatus();
                }
            },

            getRoomStatusText(status) {
                switch(status) {
                    case 'ACTIVE':
                    case 'OCCUPIED':
                        return 'Currently Occupied';
                    case 'UPCOMING_TODAY':
                        return 'Reserved Today';
                    case 'FUTURE':
                        return 'Reserved';
                    case 'PENDING':
                        return 'Pending Approval';
                    case 'PAST':
                    case 'AVAILABLE':
                    case null:
                    default:
                        return 'Available';
                }
            },

            getPCStatusText(status) {
                switch(status) {
                    case 'ACTIVE':
                        return 'Currently Occupied';
                    case 'UPCOMING_TODAY':
                        return 'Reserved Today';
                    case 'FUTURE':
                        return 'Future Reservation';
                    case 'PENDING':
                        return 'Pending Approval';
                    case 'PENDING_UPCOMING':
                        return 'Pending (Upcoming)';
                    case 'PAST':
                        return 'Available';
                    default:
                        return 'Available';
                }
            },

            async loadBooks() {
                try {
                    const response = await fetch('get_books.php');
                    const data = await response.json();
                    if (data.success) {
                        this.books = data.books;
                        this.filteredBooks = this.books;
                    }
                } catch (error) {
                    console.error('Error loading books:', error);
                }
            },

            async loadBorrowingRequests() {
                try {
                    const response = await fetch('get_borrowing_requests.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ student_id: this.studentDetails.student_id })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.borrowingRequests = data.requests;
                    }
                } catch (error) {
                    console.error('Error loading borrowing requests:', error);
                }
            },

            searchBooks() {
                const searchTerm = this.bookSearch.toLowerCase();
                this.filteredBooks = this.books.filter(book => 
                    book.title.toLowerCase().includes(searchTerm) ||
                    book.author.toLowerCase().includes(searchTerm)
                );
                
                // Apply filters after search
                this.applyFilters();
            },
            
            applyFilters() {
                const searchTerm = this.bookSearch.toLowerCase();
                
                // Start with all books or the current search results
                let filtered = this.books;
                
                // Apply search filter first
                if (searchTerm) {
                    filtered = filtered.filter(book => 
                        book.title.toLowerCase().includes(searchTerm) ||
                        book.author.toLowerCase().includes(searchTerm)
                    );
                }
                
                // Apply condition filter
                if (this.conditionFilter) {
                    filtered = filtered.filter(book => 
                        book.condition && book.condition.toLowerCase() === this.conditionFilter.toLowerCase()
                    );
                }
                
                // Apply branch filter
                if (this.branchFilter) {
                    console.log("Filtering by branch:", this.branchFilter);
                    filtered = filtered.filter(book => {
                        // Log for debugging
                        console.log(`Book ${book.title} - Branch: "${book.branch}" vs Filter: "${this.branchFilter}"`);
                        return book.branch && book.branch.toLowerCase() === this.branchFilter.toLowerCase();
                    });
                }
                
                console.log(`Filter result: ${filtered.length} books remaining`);
                this.filteredBooks = filtered;
            },

            async requestBook(book) {
                try {
                    const response = await fetch('request_book.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            student_id: this.studentDetails.student_id,
                            book_id: book.book_id
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        alert('Book request submitted successfully!');
                        this.loadBorrowingRequests();
                    } else {
                        alert(data.message || 'Failed to submit book request');
                    }
                } catch (error) {
                    console.error('Error requesting book:', error);
                    alert('An error occurred while submitting the request');
                }
            },

            hasApprovedConflict(roomId, startTime, endTime) {
                if (!this.todaysReservations) return false;
                const toMinutes = t => {
                    const [h, m] = t.split(':').map(Number);
                    return h * 60 + m;
                };
                const selStart = toMinutes(startTime);
                const selEnd = toMinutes(endTime);

                return this.todaysReservations.some(res => {
                    if (parseInt(res.room_id) !== parseInt(roomId)) return false;
                    if (res.status !== 'Approved') return false;
                    const resStart = toMinutes(res.start_time);
                    const resEnd = toMinutes(res.end_time);
                    return !(selEnd <= resStart || selStart >= resEnd);
                });
            },

            hasPendingConflict(roomId, startTime, endTime) {
                if (!this.todaysReservations) return false;
                const toMinutes = t => {
                    const [h, m] = t.split(':').map(Number);
                    return h * 60 + m;
                };
                const selStart = toMinutes(startTime);
                const selEnd = toMinutes(endTime);

                return this.todaysReservations.some(res => {
                    if (parseInt(res.room_id) !== parseInt(roomId)) return false;
                    if (res.status !== 'Pending') return false;
                    const resStart = toMinutes(res.start_time);
                    const resEnd = toMinutes(res.end_time);
                    return !(selEnd <= resStart || selStart >= resEnd);
                });
            },

            getNowTime() {
                const now = new Date();
                return now.toTimeString().slice(0,5); // "HH:MM"
            },

            getNowTimePlus(hours) {
                const now = new Date();
                now.setHours(now.getHours() + (hours || 1));
                return now.toTimeString().slice(0,5);
            },

            async submitRegistration() {
                try {
                    // Basic client-side validation
                    if (!this.regStudentId || !this.regName || !this.regContact || !this.regAddress || !this.regEmail) {
                        alert('Please fill in all required fields');
                        return;
                    }
                    
                    // Validate student ID format
                    if (!/^\d{2}-\d{4}$/.test(this.regStudentId)) {
                        alert('Invalid student ID format. Expected: YY-XXXX (e.g., 23-2444)');
                        return;
                    }
                    
                    // Validate contact number
                    if (!/^09\d{9}$/.test(this.regContact)) {
                        alert('Invalid contact number. Must be 11 digits starting with 09');
                        return;
                    }
                    
                    // Show loading state
                    const submitBtn = document.querySelector('#registrationForm button[type="submit"]');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Registering...';
                    submitBtn.disabled = true;
                    
                    // Send registration data to server
                    const response = await fetch('process/process_registration.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            student_id: this.regStudentId,
                            name: this.regName,
                            course: this.regCourse,
                            section: this.regSection,
                            contact: this.regContact,
                            address: this.regAddress,
                            email: this.regEmail
                        })
                    });
                    
                    const data = await response.json();
                    
                    // Reset loading state
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    
                    if (data.success) {
                        // Show success message
                        alert('Library account registered successfully! You can now login with your student ID.');
                        
                        // Reset form and close modal
                        this.regStudentId = '';
                        this.regName = '';
                        this.regCourse = '';
                        this.regSection = '';
                        this.regContact = '';
                        this.regAddress = '';
                        this.regEmail = '';
                        this.showRegistrationModal = false;
                        
                        // Set the student ID in the login form
                        this.$refs.studentIdInput.value = data.data.student_id;
                    } else {
                        // Show error message
                        alert('Registration failed: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error during registration:', error);
                    alert('An error occurred during registration. Please try again later.');
                    
                    // Reset loading state if error occurs
                    const submitBtn = document.querySelector('#registrationForm button[type="submit"]');
                    submitBtn.textContent = 'Register';
                    submitBtn.disabled = false;
                }
            }
        }));
    });
    </script>
</body>
</html> 