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
    <style>
        .bg-library {
            background-image: url('assets/images/libbg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body class="bg-library min-h-screen" x-data="app">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="text-center text-white mb-6">
            <img src="assets/images/QCU_Logo_2019.png" alt="QCU Logo" class="w-20 h-20 mx-auto mb-2">
            <h1 class="text-2xl font-bold mb-1">QCU Library</h1>
            <p class="text-sm italic">
                "Empowering the students of Quezon City University through knowledge<br>
                and resources for academic excellence."
            </p>
        </div>
        
        <!-- Login Form Card -->
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
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
        <div class="bg-white rounded-lg w-[800px] p-6">
            <!-- Reservation Options -->
            <div class="flex gap-4 mb-6">
                <button @click="activeTab = 'pc'; resetForm()" 
                        :class="activeTab === 'pc' ? 'bg-blue-900 text-white' : 'bg-white text-blue-900 border border-blue-900'"
                        class="flex items-center gap-2 px-4 py-2 rounded text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    PC RESERVATION
                </button>
                <button @click="activeTab = 'room'; resetForm()"
                        :class="activeTab === 'room' ? 'bg-blue-900 text-white' : 'bg-white text-blue-900 border border-blue-900'"
                        class="flex items-center gap-2 px-4 py-2 rounded text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    LIBRARY ROOM RESERVATION
                </button>
                    </div>
                    
            <div class="grid grid-cols-2 gap-4">
                <!-- Left Column - Reservation Form -->
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
                                        :disabled="pcStatus[i]" 
                                        :class="pcStatus[i] ? 'text-red-500' : 'text-green-500'"
                                        x-text="'PC ' + i + ' - ' + (pcStatus[i] ? 'Occupied' : 'Available')">
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
                                <span class="text-xs text-gray-500 mt-1 block">1-2 hours duration</span>
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
                                    <template x-for="i in 4" :key="i">
                                        <option :value="i" 
                                                :disabled="roomStatus[i] === 'ACTIVE' || roomStatus[i] === 'UPCOMING_TODAY'" 
                                                :class="{
                                                    'text-red-500': roomStatus[i] === 'ACTIVE',
                                                    'text-yellow-500': roomStatus[i] === 'UPCOMING_TODAY',
                                                    'text-blue-500': roomStatus[i] === 'FUTURE',
                                                    'text-green-500': roomStatus[i] === 'AVAILABLE'
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
                                    <span class="text-xs text-gray-500 mt-1 block">2-4 hours duration</span>
                                </div>
                            </div>
                            
                            <button @click="submitRoomReservation" 
                                    class="w-full bg-blue-900 text-white py-2 rounded text-sm mt-2">
                                CONFIRM ROOM RESERVATION
                        </button>
                        </div>
                    </template>
                </div>

                <!-- Right Column - Status Display -->
                <div>
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
                                                          'text-yellow-500': pcStatus[i] === 'UPCOMING_TODAY',
                                                          'text-blue-500': pcStatus[i] === 'FUTURE',
                                                          'text-gray-500': pcStatus[i] === 'PAST',
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
                                        <template x-for="i in 4" :key="i">
                                            <tr class="border-b">
                                                <td class="py-1.5 px-3 text-center" x-text="'Room ' + i"></td>
                                                <td class="py-1.5 px-3 text-center">
                                                    <span
                                                          :class="{
                                                              'text-red-500': roomStatus[i] === 'ACTIVE',
                                                              'text-yellow-500': roomStatus[i] === 'UPCOMING_TODAY',
                                                              'text-blue-500': roomStatus[i] === 'FUTURE',
                                                              'text-green-500': roomStatus[i] === 'AVAILABLE'
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

            // PC status computed property
            get pcStatus() {
                return this.pcStatusData;
            },

            set pcStatus(value) {
                this.pcStatusData = { ...value };
            },

            // Room status computed property
            get roomStatus() {
                return this.roomStatusData;
            },

            set roomStatus(value) {
                this.roomStatusData = { ...value };
            },

            init() {
                // Initialize status
                const initialPCStatus = {};
                for (let i = 1; i <= 19; i++) {
                    initialPCStatus[i] = null;
                }
                this.pcStatus = initialPCStatus;

                const initialRoomStatus = {};
                for (let i = 1; i <= 4; i++) {
                    initialRoomStatus[i] = null;
                }
                this.roomStatus = initialRoomStatus;
                
                // Initial load of status
                this.loadPCStatus();
                this.loadRoomStatus();
                
                // Set up auto-refresh every 30 seconds
                this.statusRefreshInterval = setInterval(() => {
                    this.loadPCStatus();
                    this.loadRoomStatus();
                }, 30000);

                // Add event listeners for visibility and focus
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
                
                // Force UI update
                this.$nextTick(() => {
                    console.log(`PC ${pcId} marked as occupied:`, this.pcStatusData);
                    
                    // Update the UI elements directly
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
                    // Add a cache-busting parameter to prevent browser caching
                    const response = await fetch('get_pc_status.php?t=' + new Date().getTime());
                    const data = await response.json();
                    
                    if (data.success) {
                        console.log('Received PC status:', data);
                        
                        // Create new status object
                        const newStatus = {};
                        let hasChanges = false;
                        
                        // First set all PCs as available
                        for (let i = 1; i <= 19; i++) {
                            newStatus[i] = null;
                        }
                        
                        // Update status based on reservations
                        data.active_reservations.forEach(res => {
                            const pcId = parseInt(res.pc_id);
                            if (pcId >= 1 && pcId <= 19) {
                                newStatus[pcId] = res.status;
                                
                                if (newStatus[pcId] !== this.pcStatusData[pcId]) {
                                    hasChanges = true;
                                }
                            }
                        });
                        
                        if (hasChanges) {
                            this.pcStatus = newStatus;
                        }
                        
                        // Clear selection if PC is now occupied or has upcoming reservation
                        if (this.selectedPC && (newStatus[this.selectedPC] === 'ACTIVE' || newStatus[this.selectedPC] === 'UPCOMING_TODAY')) {
                            this.selectedPC = '';
                            this.purpose = '';
                            this.startTime = '';
                            this.endTime = '';
                            alert('Selected PC is no longer available. Please choose another PC.');
                        }
                    }
                } catch (error) {
                    console.error('Error loading PC status:', error);
                }
            },

            validateAndSetEndTime() {
                if (!this.startTime) {
                    this.endTime = '';
                    return;
                }

                // Get current date for base comparison
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

                // Format times for input
                this.minEndTime = minDate.getHours().toString().padStart(2, '0') + ':' + 
                                 minDate.getMinutes().toString().padStart(2, '0');
                this.maxEndTime = maxDate.getHours().toString().padStart(2, '0') + ':' + 
                                 maxDate.getMinutes().toString().padStart(2, '0');

                // Set default end time to minimum (1 hour after start)
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
                        // Mark PC as occupied immediately
                        this.markPCAsOccupied(this.selectedPC);
                        
                        // Show success message
                        alert(`PC ${this.selectedPC} has been successfully reserved\nStart: ${data.data.stored_start_time}\nEnd: ${data.data.stored_end_time}`);
                        
                        // Reset form
                        this.selectedPC = '';
                        this.purpose = '';
                        this.startTime = '';
                        this.endTime = '';
                        this.minEndTime = '';
                        this.maxEndTime = '';
                        
                        // Force an immediate refresh of PC status from the server
                        await this.loadPCStatus();
                        
                        // Keep the reservation modal open
                        // this.showReservationModal = false; // Removed this line
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
                    
                    if (data.success) {
                        console.log('Received room status data:', data);
                        
                        const newStatus = {};
                        let hasChanges = false;
                        
                        // Initialize all rooms as available
                        for (let i = 1; i <= 4; i++) {
                            newStatus[i] = 'AVAILABLE';
                        }
                        
                        // Update status based on active and upcoming reservations
                        data.active_reservations.forEach(res => {
                            const roomId = parseInt(res.room_id);
                            if (roomId >= 1 && roomId <= 4) {
                                newStatus[roomId] = 'ACTIVE';
                            }
                        });

                        data.upcoming_reservations.forEach(res => {
                            const roomId = parseInt(res.room_id);
                            if (roomId >= 1 && roomId <= 4 && newStatus[roomId] !== 'ACTIVE') {
                                newStatus[roomId] = 'UPCOMING_TODAY';
                            }
                        });

                        // Check all reservations for future ones
                        data.all_reservations.forEach(res => {
                            const roomId = parseInt(res.room_id);
                            if (roomId >= 1 && roomId <= 4) {
                                if (res.status === 'FUTURE' && newStatus[roomId] !== 'ACTIVE' && newStatus[roomId] !== 'UPCOMING_TODAY') {
                                    newStatus[roomId] = 'FUTURE';
                                }
                            }
                        });
                        
                        // Check if there are any changes
                        for (let i = 1; i <= 4; i++) {
                            if (newStatus[i] !== this.roomStatusData[i]) {
                                hasChanges = true;
                                break;
                            }
                        }
                        
                        if (hasChanges) {
                            this.roomStatus = newStatus;
                            console.log('Updated room status:', this.roomStatus);
                        }
                        
                        // Clear selection if room is now occupied or has upcoming reservation
                        if (this.selectedRoom && (newStatus[this.selectedRoom] === 'ACTIVE' || newStatus[this.selectedRoom] === 'UPCOMING_TODAY')) {
                            this.selectedRoom = '';
                            this.roomPurpose = '';
                            this.roomStartTime = '';
                            this.roomEndTime = '';
                            alert('Selected room is no longer available. Please choose another room.');
                        }
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

                // Set default end time to minimum (2 hours after start)
                this.roomEndTime = this.minRoomEndTime;
            },

            markRoomAsOccupied(roomId) {
                console.log(`Marking room ${roomId} as occupied`);
                const newStatus = { ...this.roomStatusData };
                newStatus[roomId] = 'ACTIVE';
                this.roomStatus = newStatus;
                
                // Force Alpine.js to update the UI
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
                    // Validate student details
                    if (!this.studentDetails || !this.studentDetails.student_id) {
                        throw new Error('Student details not found. Please try logging in again.');
                    }

                    // Check room availability first
                    await this.loadRoomStatus();
                    if (this.roomStatus[this.selectedRoom] === 'ACTIVE' || 
                        this.roomStatus[this.selectedRoom] === 'UPCOMING_TODAY') {
                        alert('This room is no longer available. Please choose another room.');
                        this.selectedRoom = '';
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
                        // Immediately mark room as occupied
                        const newStatus = { ...this.roomStatusData };
                        newStatus[this.selectedRoom] = 'ACTIVE';
                        this.roomStatus = newStatus;
                        
                        // Force an immediate refresh of room status
                        await this.loadRoomStatus();
                        
                        alert(`Room ${this.selectedRoom} has been successfully reserved\nStart: ${data.data.stored_start_time}\nEnd: ${data.data.stored_end_time}`);
                        
                        this.selectedRoom = '';
                        this.roomPurpose = '';
                        this.roomStartTime = '';
                        this.roomEndTime = '';
                        this.minRoomEndTime = '';
                        this.maxRoomEndTime = '';
                        
                        // Schedule another status refresh after a short delay
                        setTimeout(() => this.loadRoomStatus(), 1000);
                    } else {
                        throw new Error(data.message || 'Failed to make room reservation');
                    }
                } catch (error) {
                    console.error('Error making room reservation:', error);
                    alert('Error making room reservation: ' + error.message);
                    await this.loadRoomStatus();
                }
            },

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
                        // Load PC status immediately when student is verified
                        await this.loadPCStatus();
                    } else {
                        alert(data.message || 'Student not found. Please check your ID.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
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
                    case 'PAST':
                        return 'Available';
                    default:
                        return 'Available';
                }
            }
        }));
    });
    </script>
</body>
</html> 