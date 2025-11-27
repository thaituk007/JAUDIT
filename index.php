<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

// โหลด config หรือใช้ค่า default
if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
} else {
    $config = [
        'app_name' => 'ชื่อระบบ',
        'version' => '1.0.0',
        'dateversion' => date('Y-m-d'),
    ];
}

// ตรวจสอบผู้ใช้ล็อกอิน
$username = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : 'User';
$loggedIn = isset($_SESSION['user']);
$today = date('Y-m-d');

// Logout process
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JAUDIT</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
body { font-family: 'Kanit', sans-serif; background:#f9fafb; }
.card-hover { transition: all 0.3s ease; }
.card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.pulse-animation { animation: pulse 2s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }
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
                    <h1 class="text-xl font-bold text-gray-800">jAUDIT</h1>
                    <p class="text-xs text-gray-500">Reports</p>
                </div>
            </div>
            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-8">
                <a href="service_daily.php" class="text-gray-700 hover:text-blue-600 font-medium">จำนวนบริการรายวัน</a>
                <a href="#reports" class="text-gray-700 hover:text-blue-600 font-medium">รายงาน</a>
                <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 font-medium">ข้อมูลผ่าน API</a>
                <a href="api_right.php" class="text-gray-700 hover:text-blue-600 font-medium">SRM-API</a>
                <a href="#analytics" class="text-gray-700 hover:text-blue-600 font-medium">วิเคราะห์</a>
                <a href="report_ncds_gis.php" class="text-gray-700 hover:text-blue-600 font-medium">NCDs GIS</a>
                <a href="#drugcat" class="text-gray-700 hover:text-blue-600 font-medium">Drug Catalogue</a>
                <a href="save_config.php" class="text-gray-700 hover:text-blue-600 font-medium">ตั้งค่าระบบ</a>
                <a href="#contact" class="text-gray-700 hover:text-blue-600 font-medium">ติดต่อเรา</a>
                <?php if($loggedIn): ?>
                    <a href="?action=logout" class="text-red-600 hover:text-red-800 font-medium">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="text-gray-700 hover:text-blue-600 font-medium">เข้าสู่ระบบ</a>
                <?php endif; ?>
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
        <a href="api_right.php" class="text-gray-700 hover:text-blue-600">SRM-API</a>
        <a href="save_config.php" class="text-gray-700 hover:text-blue-600">ตั้งค่าระบบ</a>
        <a href="#contact" class="text-gray-700 hover:text-blue-600">ติดต่อเรา</a>
        <?php if($loggedIn): ?>
            <a href="?action=logout" class="text-red-600 hover:text-red-800">Logout</a>
        <?php else: ?>
            <a href="login.php" class="text-gray-700 hover:text-blue-600">Login</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Hero Section -->
<section class="gradient-bg text-white py-20">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-6xl font-bold mb-6">
            ตรวจสอบและรายงาน<br>
            <span class="text-yellow-300">สำหรับหน่วยบริการปฐมภูมิ <br> และจัดการข้อมูล JHCIS</span>
        </h1>
        <p class="text-xl md:text-2xl mb-8 opacity-90">
            วิเคราะห์และติดตามข้อมูลสุขภาพ สำหรับหน่วยบริการปฐมภูม
        </p>
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
                <h3 class="text-xl font-semibold text-gray-800 mb-2">
                    <a href="reports.php" class="text-blue-600 hover:text-blue-800 hover:underline">รายงาน JHCIS</a>
                </h3>
                <a href="report_elderly_adl.php" class="inline-flex items-center text-blue-600 font-medium hover:text-blue-800 transition-colors">
                    ผู้สูงอายุ ADL <i class="fas fa-arrow-right ml-2"></i> <span class="ml-2">รายชื่อผู้สูงอายุพร้อม ADL</span>
                </a><br>
                <a href="dspm_report_year.php" class="inline-flex items-center text-blue-600 font-medium hover:text-blue-800 transition-colors">
                    DSPM <i class="fas fa-arrow-right ml-2"></i> <span class="ml-2">เป้าหมายพัฒนาการเด็ก</span>
                </a><br>
                <a href="rightins_report.php" class="inline-flex items-center text-blue-600 font-medium hover:text-blue-800 transition-colors">
                    รายงาน กลุ่มสิทธิการรักษา
                </a><br>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-search text-green-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">ตรวจสอบข้อมูล</h3>
                <p class="text-gray-600 mb-4">เครื่องมือตรวจสอบความถูกต้องและความสมบูรณ์ของข้อมูล</p>
                <a href="#" class="text-green-600 font-medium hover:text-green-800 transition-colors"><i class="fas fa-arrow-right ml-1"></i><span class="ml-2">PERSON</span></a></a></br>
                <a href="#" class="text-green-600 font-medium hover:text-green-800 transition-colors"><i class="fas fa-arrow-right ml-1"></i><span class="ml-2">DEATH</span></a></a>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">วิเคราะห์ข้อมูล</h3>
                <p class="text-gray-600 mb-4">เครื่องมือวิเคราะห์และสร้างกราฟแบบโต้ตอบ</p>
                <a href="service_daily.php" class="text-purple-600 font-medium hover:text-purple-800 transition-colors">บริการรายวัน</a><br>
                <a href="rightins_report.php" class="text-purple-600 font-medium hover:text-purple-800 transition-colors">รายงานตามสิทธิการรักษา</a>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-stethoscope text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">NDCs</h3>
                <p class="text-gray-600 mb-4">คนไทยห่างไกล NCDs</p>
                <a href="report_ncds_gis.php" class="text-purple-600 font-medium hover:text-purple-800 transition-colors">NCDs GIS <i class="fas fa-arrow-right ml-1"></i><span class="ml-2">ทะเบียนผู้ป่วยเรื้อรัง (NCDs) พร้อมพิกัด GIS</span></a>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-stethoscope text-purple-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">SRM-API</h3>
                <p class="text-gray-600 mb-4">ระบบตรวจสอบสิทธิ NHSO จาก Single Register Management (SRM-API)</p>
                <a href="api_right.php" class="text-purple-600 font-medium hover:text-purple-800 transition-colors">SRM-API<i class="fas fa-arrow-right ml-1"></i><span class="ml-2">ระบบตรวจสอบสิทธิ NHSO จาก Single Register Management (SRM-API)</span></a>
                <a href="updateright.php" class="text-purple-600 font-medium hover:text-purple-800 transition-colors">UPDATE สิทธิ จากข้อมูล SRM-API<i class="fas fa-arrow-right ml-1"></i><span class="ml-2">UPDATE</span></a>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-pills text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">ยา และ Drug Catalogue</h3>
                <p class="text-gray-600 mb-4">สำหรับจัดทำ Drug Catalogue ยาแผนปัจจุบัน และยาสมุนไพร</p>
                <a href="DRUGCAT_TMT.php" class="text-green-600 font-medium hover:text-green-800 transition-colors">ยาแผนปัจจุบัน <i class="fas fa-arrow-right ml-1"></i><span class="ml-2">TMT Drug Catalogue</span></a><br>
                <a href="DRUGCAT_TTMT.php" class="text-green-600 font-medium hover:text-green-800 transition-colors">ยาสมุนไพร <i class="fas fa-arrow-right ml-1"></i><span class="ml-2">TTMT Drug Catalogue</span></a><br>
                <a href="da_allergy_list.php" class="text-green-600 font-medium hover:text-green-800 transition-colors">รายงานผู้แพ้ยา</a>
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
                        <h3 class="text-xl font-bold">jAUDIT</h3>
                        <p class="text-gray-400 text-sm">Health Report & Tools</p>
                    </div>
                </div>
                <p class="text-gray-400 mb-4">
                    ระบบตรวจสอบและรายงานสุขภาพชุมชนสำหรับ JHCIS
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
                    <li><i class="fas fa-envelope mr-2"></i> thaituk007@gmail.com</li>
                    <li><i class="fas fa-phone mr-2"></i> 080-242-8055</li>
                    <li><i class="fas fa-map-marker-alt mr-2"></i> ชลบุรี</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; <?= date("Y") ?> jAUDIT สงวนลิขสิทธิ์</p>
            🧭 <strong><?= htmlspecialchars($config['app_name']) ?></strong> |
            👤 <?= htmlspecialchars($username) ?> |
            🛠️ Version <?= htmlspecialchars($config['version']) ?> | DateVersion <?= htmlspecialchars($config['dateversion']) ?> |
            💡 PHP <?= phpversion(); ?> |
            📅 <?= $today ?>
        </div>
    </div>
</footer>

<script>
document.querySelectorAll('a[href^="#"]').forEach(anchor=>{
    anchor.addEventListener('click',function(e){
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if(target) target.scrollIntoView({behavior:'smooth'});
    });
});

document.getElementById("menuToggle").addEventListener("click",()=>{
    document.getElementById("mobileMenu").classList.toggle("hidden");
});

async function updateOnlineCount(){
    try{
        const res = await fetch("online_count.php");
        const data = await res.json();
        document.getElementById("online-count").textContent = data.online;
    }catch(e){
        console.error("ไม่สามารถโหลดจำนวนออนไลน์", e);
    }
}
setInterval(updateOnlineCount,10000);
updateOnlineCount();
</script>
</body>
</html>
