<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JHCISAUDIT - Health Report & Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <meta name="description" content="ระบบตรวจสอบและรายงาน JHCIS สำหรับหน่วยบริการปฐมภูมิ วิเคราะห์ ตรวจสอบคุณภาพข้อมูลสุขภาพ">
    <meta name="keywords" content="JHCIS, ระบบสุขภาพ, รายงานสุขภาพ, วิเคราะห์ข้อมูล, ตรวจสอบข้อมูล">
    <meta name="author" content="JHCISAUDIT">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Kanit', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .pulse-animation { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-heartbeat text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">JHCISAUDIT</h1>
                        <p class="text-xs text-gray-500">Health Report & Tools</p>
                    </div>
                </div>
                <!-- Desktop Menu -->
                <div class="hidden md:flex space-x-8">
                    <a href="#dashboard" class="text-gray-700 hover:text-blue-600 font-medium">แดชบอร์ด</a>
                    <a href="#reports" class="text-gray-700 hover:text-blue-600 font-medium">รายงาน</a>
                    <a href="#tools" class="text-gray-700 hover:text-blue-600 font-medium">เครื่องมือ</a>
                    <a href="#analytics" class="text-gray-700 hover:text-blue-600 font-medium">วิเคราะห์</a>
                    <a href="save_config.php" class="text-gray-700 hover:text-blue-600 font-medium">ตั้งค่าระบบ</a>
                    <a href="#contact" class="text-gray-700 hover:text-blue-600 font-medium">ติดต่อเรา</a>
                </div>
                <!-- Mobile Menu Button -->
                <button id="menuToggle" class="md:hidden text-gray-700">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden flex flex-col space-y-2 bg-white shadow-lg p-4">
            <a href="#dashboard" class="text-gray-700 hover:text-blue-600">แดชบอร์ด</a>
            <a href="#reports" class="text-gray-700 hover:text-blue-600">รายงาน</a>
            <a href="#tools" class="text-gray-700 hover:text-blue-600">เครื่องมือ</a>
            <a href="#analytics" class="text-gray-700 hover:text-blue-600">วิเคราะห์</a>
            <a href="save_config.php" class="text-gray-700 hover:text-blue-600">ตั้งค่าระบบ</a>
            <a href="#contact" class="text-gray-700 hover:text-blue-600">ติดต่อเรา</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="gradient-bg text-white py-20">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">
                ระบบตรวจสอบและรายงาน<br>
                <span class="text-yellow-300">สำหรับหน่วยบริการปฐมภูมิ <br> และจัดการข้อมูล JHCIS</span>
            </h1>
            <p class="text-xl md:text-2xl mb-8 opacity-90">
                เครื่องมือครบครันสำหรับการวิเคราะห์และติดตามข้อมูลสุขภาพ สำหรับหน่วยบริการปฐมภูมิ ที่ใช้ JHCIS
            </p>
            <!-- ออนไลน์ -->
            <div class="mt-6">
                <span class="bg-white text-gray-800 px-4 py-2 rounded-lg shadow">
                    👥 ออนไลน์ตอนนี้: <span id="online-count" class="font-bold text-blue-600">0</span> คน
                </span>
            </div>
        </div>
    </section>

    <!-- Dashboard / Reports Section -->
    <section id="reports" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">รายงาน JHCIS</h3>
                    <p class="text-gray-600 mb-4">สร้างรายงานตามมาตรฐาน</p>
                    <a href="adl_report.php" class="text-blue-600 font-medium hover:text-blue-800 transition-colors">
                        ผู้สูงอายุ ADL <i class="fas fa-arrow-right ml-1"></i>
                    </a><br>
                    <a href="dspm_report.php" class="text-blue-600 font-medium hover:text-blue-800 transition-colors">
                        พัฒนาการเด็ก DSPM <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-search text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">ตรวจสอบข้อมูล</h3>
                    <p class="text-gray-600 mb-4">เครื่องมือตรวจสอบความถูกต้องและความสมบูรณ์ของข้อมูล</p>
                    <a href="check_data.php" class="text-green-600 font-medium hover:text-green-800 transition-colors">
                        เริ่มตรวจสอบ <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">วิเคราะห์ข้อมูล</h3>
                    <p class="text-gray-600 mb-4">เครื่องมือวิเคราะห์และสร้างกราฟแบบโต้ตอบ</p>
                    <a href="analytics.php" class="text-purple-600 font-medium hover:text-purple-800 transition-colors">
                        เริ่มวิเคราะห์ <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Tools Section -->
    <section id="tools" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-xl text-gray-600">เครื่องมือครบครันสำหรับการจัดการข้อมูลสุขภาพ</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 card-hover">
                    <i class="fas fa-database text-blue-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">จัดการฐานข้อมูล</h3>
                    <p class="text-gray-600 text-sm">นำเข้า ส่งออก และจัดการข้อมูล JHCIS</p>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 card-hover">
                    <i class="fas fa-shield-alt text-green-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ตรวจสอบคุณภาพ</h3>
                    <p class="text-gray-600 text-sm">ตรวจสอบความถูกต้องของข้อมูลอัตโนมัติ</p>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 card-hover">
                    <i class="fas fa-calendar-alt text-purple-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">รายงานตามช่วงเวลา</h3>
                    <p class="text-gray-600 text-sm">สร้างรายงานรายวัน รายเดือน รายปี</p>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-6 card-hover">
                    <i class="fas fa-bell text-orange-600 text-3xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">การแจ้งเตือน</h3>
                    <p class="text-gray-600 text-sm">แจ้งเตือนข้อมูลผิดปกติและกำหนดส่ง</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-heartbeat text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">JHCISAUDIT</h3>
                            <p class="text-gray-400 text-sm">Health Report & Tools</p>
                        </div>
                    </div>
                    <p class="text-gray-400 mb-4">
                        ระบบตรวจสอบและรายงานสุขภาพชุมชนที่ทันสมัย
                        ช่วยให้การจัดการข้อมูล JHCIS เป็นไปอย่างมีประสิทธิภาพ
                    </p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">เมนูหลัก</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#dashboard" class="hover:text-white transition-colors">แดชบอร์ด</a></li>
                        <li><a href="#reports" class="hover:text-white transition-colors">รายงาน</a></li>
                        <li><a href="#tools" class="hover:text-white transition-colors">เครื่องมือ</a></li>
                        <li><a href="#analytics" class="hover:text-white transition-colors">วิเคราะห์</a></li>
                    </ul>
                </div>
                <div id="contact">
                    <h4 class="text-lg font-semibold mb-4">ติดต่อเรา</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-envelope mr-2"></i> support@gmail.com</li>
                        <li><i class="fas fa-phone mr-2"></i> 038-xxx-xxx</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i> ชลบุรี</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date("Y"); ?> JHCISAUDIT สงวนลิขสิทธิ์ทุกประการ</p>
            </div>
        </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date("Y"); ?> JHCISAUDIT สงวนลิขสิทธิ์ทุกประการ</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth' });
            });
        });

        // Toggle Mobile Menu
        document.getElementById("menuToggle").addEventListener("click", () => {
            document.getElementById("mobileMenu").classList.toggle("hidden");
        });

        // อัปเดตจำนวนออนไลน์ทุก 10 วิ
        async function updateOnlineCount() {
            try {
                const res = await fetch("online_count.php");
                const data = await res.json();
                document.getElementById("online-count").textContent = data.online;
            } catch (e) {
                console.error("ไม่สามารถโหลดจำนวนออนไลน์", e);
            }
        }
        setInterval(updateOnlineCount, 10000);
        updateOnlineCount(); // โหลดครั้งแรกเลย
    </script>
</body>
</html>
