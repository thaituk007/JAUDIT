<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JHCIS - ระบบรายงานข้อมูลสุขภาพ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .stat-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card-2 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card-3 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card-4 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="bg-white p-2 rounded-lg">
                        <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">JHCIS Dashboard</h1>
                        <p class="text-blue-100">ระบบรายงานข้อมูลสุขภาพชุมชน</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">วันที่: <span id="currentDate"></span></span>
                    <div class="bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                        <span class="text-sm font-medium">ผู้ใช้: นพ.สมชาย ใจดี</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Menu -->
    <nav class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-6">
            <div class="flex items-center justify-between py-3">
                <!-- Home Button -->
                <button onclick="goHome()" class="flex items-center space-x-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L9 5.414V17a1 1 0 102 0V5.414l5.293 5.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                    <span class="text-sm font-medium">หน้าแรก</span>
                </button>

                <!-- Dropdown Menus -->
                <div class="flex items-center space-x-6">
                    <!-- Patient Management Dropdown -->
                    <div class="relative" id="patientDropdown">
                        <button onclick="toggleDropdown('patientMenu')" class="flex items-center space-x-1 text-gray-700 hover:text-blue-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="text-sm font-medium">จัดการผู้ป่วย</span>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                            </svg>
                        </button>
                        <div id="patientMenu" class="hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg">ลงทะเบียนผู้ป่วยใหม่</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">ค้นหาผู้ป่วย</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">ประวัติการรักษา</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-b-lg">รายงานผู้ป่วย</a>
                        </div>
                    </div>

                    <!-- Appointment Dropdown -->
                    <div class="relative" id="appointmentDropdown">
                        <button onclick="toggleDropdown('appointmentMenu')" class="flex items-center space-x-1 text-gray-700 hover:text-blue-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="text-sm font-medium">การนัดหมาย</span>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                            </svg>
                        </button>
                        <div id="appointmentMenu" class="hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg">จองนัดหมายใหม่</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">ดูการนัดหมายวันนี้</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">ปฏิทินนัดหมาย</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-b-lg">ยกเลิก/เลื่อนนัด</a>
                        </div>
                    </div>

                    <!-- Reports Dropdown -->
                    <div class="relative" id="reportsDropdown">
                        <button onclick="toggleDropdown('reportsMenu')" class="flex items-center space-x-1 text-gray-700 hover:text-blue-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="text-sm font-medium">รายงาน</span>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                            </svg>
                        </button>
                        <div id="reportsMenu" class="hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg">รายงานสถิติผู้ป่วย</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">รายงานการเงิน</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">รายงานโรคประจำถิ่น</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">รายงานยา/เวชภัณฑ์</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-b-lg">ส่งออกข้อมูล</a>
                        </div>
                    </div>

                    <!-- Settings Dropdown -->
                    <div class="relative" id="settingsDropdown">
                        <button onclick="toggleDropdown('settingsMenu')" class="flex items-center space-x-1 text-gray-700 hover:text-blue-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="text-sm font-medium">ตั้งค่า</span>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                            </svg>
                        </button>
                        <div id="settingsMenu" class="hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg">ข้อมูลส่วนตัว</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">เปลี่ยนรหัสผ่าน</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">การแจ้งเตือน</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">สำรองข้อมูล</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-b-lg">ออกจากระบบ</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card text-white p-6 rounded-xl shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">ผู้ป่วยทั้งหมด</p>
                        <p class="text-3xl font-bold">12,847</p>
                        <p class="text-white text-opacity-80 text-xs mt-1">+5.2% จากเดือนที่แล้ว</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card-2 text-white p-6 rounded-xl shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">ผู้ป่วยใหม่วันนี้</p>
                        <p class="text-3xl font-bold">47</p>
                        <p class="text-white text-opacity-80 text-xs mt-1">+12% จากเมื่อวาน</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card-3 text-white p-6 rounded-xl shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">การนัดหมาย</p>
                        <p class="text-3xl font-bold">156</p>
                        <p class="text-white text-opacity-80 text-xs mt-1">สำหรับวันนี้</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card-4 text-white p-6 rounded-xl shadow-lg card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white text-opacity-80 text-sm font-medium">รายได้วันนี้</p>
                        <p class="text-3xl font-bold">₿89,450</p>
                        <p class="text-white text-opacity-80 text-xs mt-1">+8.1% จากเมื่อวาน</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Reports -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Patient Visits Chart -->
            <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">จำนวนผู้ป่วยรายเดือน</h3>
                    <select class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        <option>ปี 2567</option>
                        <option>ปี 2566</option>
                    </select>
                </div>
                <div style="height: 250px;">
                    <canvas id="patientChart"></canvas>
                </div>
            </div>

            <!-- Disease Distribution -->
            <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">สัดส่วนโรคยอดนิยม</h3>
                    <button class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 transition-colors">
                        ดูรายละเอียด
                    </button>
                </div>
                <div style="height: 250px;">
                    <canvas id="diseaseChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-xl shadow-lg card-hover">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800">กิจกรรมล่าสุด</h3>
                    <button class="text-blue-500 hover:text-blue-600 text-sm font-medium">ดูทั้งหมด</button>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                        <div class="bg-green-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-800">ผู้ป่วยใหม่ลงทะเบียน</p>
                            <p class="text-sm text-gray-600">นางสาวสมใจ รักสุขภาพ - HN: 67001234</p>
                        </div>
                        <span class="text-sm text-gray-500">5 นาทีที่แล้ว</span>
                    </div>

                    <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-800">การตรวจสุขภาพเสร็จสิ้น</p>
                            <p class="text-sm text-gray-600">นายสมชาย สุขใส - การตรวจประจำปี</p>
                        </div>
                        <span class="text-sm text-gray-500">15 นาทีที่แล้ว</span>
                    </div>

                    <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                        <div class="bg-yellow-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-800">แจ้งเตือนการนัดหมาย</p>
                            <p class="text-sm text-gray-600">มีการนัดหมาย 12 รายการสำหรับพรุ่งนี้</p>
                        </div>
                        <span class="text-sm text-gray-500">30 นาทีที่แล้ว</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('th-TH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Patient Chart
        const patientCtx = document.getElementById('patientChart').getContext('2d');
        new Chart(patientCtx, {
            type: 'line',
            data: {
                labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                datasets: [{
                    label: 'ผู้ป่วยใหม่',
                    data: [850, 920, 1100, 980, 1200, 1150, 1300, 1250, 1180, 1350, 1280, 1400],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    }
                }
            }
        });

        // Disease Chart
        const diseaseCtx = document.getElementById('diseaseChart').getContext('2d');
        new Chart(diseaseCtx, {
            type: 'doughnut',
            data: {
                labels: ['ความดันโลหิตสูง', 'เบาหวาน', 'โรคหัวใจ', 'โรคไต', 'อื่นๆ'],
                datasets: [{
                    data: [35, 25, 15, 12, 13],
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b',
                        '#fa709a'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Dropdown functionality
        function toggleDropdown(menuId) {
            // Close all other dropdowns first
            const allMenus = ['patientMenu', 'appointmentMenu', 'reportsMenu', 'settingsMenu'];
            allMenus.forEach(id => {
                if (id !== menuId) {
                    document.getElementById(id).classList.add('hidden');
                }
            });

            // Toggle the clicked dropdown
            const menu = document.getElementById(menuId);
            menu.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const dropdowns = ['patientDropdown', 'appointmentDropdown', 'reportsDropdown', 'settingsDropdown'];
            const menus = ['patientMenu', 'appointmentMenu', 'reportsMenu', 'settingsMenu'];

            let clickedInside = false;
            dropdowns.forEach(id => {
                if (document.getElementById(id).contains(event.target)) {
                    clickedInside = true;
                }
            });

            if (!clickedInside) {
                menus.forEach(id => {
                    document.getElementById(id).classList.add('hidden');
                });
            }
        });

        // Home button functionality
        function goHome() {
            // Simulate going to home page
            alert('กลับสู่หน้าแรกแล้ว!');
            // In real application, you would use: window.location.href = '/';
        }

        // Add click handlers for menu items
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const text = this.textContent;
                alert(`คลิกที่: ${text}`);
                // In real application, you would navigate to the appropriate page
            });
        });
    </script>
<script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'97580e39f04e7331',t:'MTc1NjI2MDg2OC4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
