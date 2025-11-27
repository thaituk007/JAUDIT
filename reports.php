<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

// โหลด config
$config = include 'config.php';

// เชื่อมต่อฐานข้อมูลด้วย PDO
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ===================== STAT CARDS ===================== //
$totalPatients = $pdo->query("SELECT COUNT(*) as c FROM person WHERE dischargetype<>1")->fetchColumn();
$today = date("Y-m-d");
$newPatientsToday = $pdo->prepare("SELECT COUNT(*) FROM person WHERE DATE(dateupdate)=?");
$newPatientsToday->execute([$today]);
$newPatientsToday = $newPatientsToday->fetchColumn();
$totalAppointments = $pdo->prepare("SELECT COUNT(*) FROM visitdiagappoint WHERE DATE(appodate)=?");
$totalAppointments->execute([$today]);
$totalAppointments = $totalAppointments->fetchColumn();
$totalRevenue = 0; // สมมติว่าไม่มีตาราง finance

// ===================== CHART: ผู้ป่วยรายเดือน ===================== //
$year = date("Y");
$sql = "SELECT MONTH(visitdate) AS m, COUNT(*) AS c
        FROM visit
        WHERE YEAR(visitdate)=?
        GROUP BY MONTH(visitdate)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$year]);
$monthlyData = array_fill(1, 12, 0);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $monthlyData[(int)$row['m']] = (int)$row['c'];
}
$monthlyDataJson = json_encode(array_values($monthlyData), JSON_UNESCAPED_UNICODE);

// ===================== CHART: โรคยอดนิยม ===================== //
$sql = "SELECT diagcode, COUNT(*) AS c
        FROM visitdiag
        GROUP BY diagcode
        ORDER BY c DESC
        LIMIT 5";
$topDiseases = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$diseaseLabels = json_encode(array_column($topDiseases, 'diagcode'), JSON_UNESCAPED_UNICODE);
$diseaseCounts = json_encode(array_column($topDiseases, 'c'), JSON_UNESCAPED_UNICODE);

// ===================== Menu ===================== //
$menus = [
    'รายงาน' => [
        'รายงานสถิติผู้ป่วย',
        'รายงานการเงิน',
        'รายงานโรคประจำถิ่น',
        'รายงานยา/เวชภัณฑ์',
        'ส่งออกข้อมูล'
    ],
    'ตรวจสอบข้อมูลพื้นฐาน 12 แฟ้ม' => [
        'PERSON',
        'DEATH',
    ],

    'ตั้งค่า' => [
        'ข้อมูลส่วนตัว',
        'เปลี่ยนรหัสผ่าน',
        'การแจ้งเตือน',
        'สำรองข้อมูล',
        '<span class="text-red-600">ออกจากระบบ</span>'
    ]
];

// Recursive function สำหรับ multi-level submenu Desktop
function renderMenu($items, $isSub = false) {
    $classes = $isSub
        ? 'submenu absolute left-full top-0 mt-0 w-48 bg-white rounded-lg shadow-lg border z-50 hidden opacity-0 transition-all duration-300 transform translate-x-2'
        : 'submenu hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border z-50 opacity-0 transition-all duration-300 transform translate-y-2';
    echo '<div class="'.$classes.'">';
    foreach ($items as $key => $item) {
        if (is_array($item)) {
            echo '<div class="relative group">';
            echo '<a href="#" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-50 flex justify-between items-center">';
            echo $key.' <span class="arrow">▶️</span>';
            echo '</a>';
            renderMenu($item, true);
            echo '</div>';
        } else {
            echo '<a href="#" class="block px-4 py-2 text-sm text-gray-800 hover:bg-gray-50">'.$item.'</a>';
        }
    }
    echo '</div>';
}

// Mobile Menu Recursive
function renderMobileMenu($items){
    echo '<ul class="pl-0">';
    foreach($items as $key => $item){
        if(is_array($item)){
            echo '<li class="relative">';
            echo '<button class="w-full text-left px-4 py-2 hover:bg-gray-100 flex justify-between items-center" onclick="toggleSubmenu(this)">';
            echo $key.' <span class="arrow">▶️</span>';
            echo '</button>';
            echo '<div class="hidden ml-4">';
            renderMobileMenu($item);
            echo '</div>';
            echo '</li>';
        } else {
            echo '<li><a href="#" class="block px-6 py-2 text-gray-700 hover:bg-gray-100">'.$item.'</a></li>';
        }
    }
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($config['app_name']) ?> - Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Prompt', sans-serif; background-color:#f3f4f6; color:#1f2937; } /* text-gray-800 */
.gradient-bg { background: linear-gradient(135deg, #4facfe 0%, #667eea 100%); } /* ฟ้า→ม่วง */
.card-hover { transition: all 0.3s ease; }
.card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; }
.stat-card-2 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color:white; }
.stat-card-3 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color:white; }
.stat-card-4 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color:white; }
.arrow { transition: transform 0.3s ease; }
.submenu.show { opacity:1; transform: translate(0,0); }
.submenu { background:white; border-radius:0.5rem; box-shadow:0 10px 15px rgba(0,0,0,0.1); }
</style>
</head>
<body class="bg-gray-50">

<!-- Header -->
<header class="gradient-bg text-white shadow-lg">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <div>
                <h1 class="text-2xl font-bold"><?= htmlspecialchars($config['app_name']) ?> Dashboard</h1>
                <p class="text-blue-100">ระบบรายงานข้อมูลสุขภาพชุมชน</p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm">วันที่: <span id="currentDate"></span></span>
            <div class="bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                <span class="text-sm font-medium">ผู้ใช้: <?= htmlspecialchars($config['nhso_user']) ?></span>
            </div>
            <a href="index.php" class="inline-block bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-5 py-2 rounded-xl shadow-md hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">กลับหน้าหลัก</a>
        </div>
    </div>
</header>

<!-- Main -->
<main class="container mx-auto px-6 py-8">
    <!-- Back Button Top -->

    <!-- Desktop Navigation -->
    <nav class="hidden md:flex bg-white shadow-sm border-b mb-6">
        <div class="container mx-auto px-6 py-3 flex space-x-6">
            <?php
            foreach($menus as $menuName=>$subItems){
                echo '<div class="relative group">';
                echo '<button class="flex items-center space-x-1 text-gray-700 hover:text-blue-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors">';
                echo '<span class="text-sm font-medium">'.$menuName.'</span>';
                echo '<span class="arrow">▶️</span>';
                echo '</button>';
                renderMenu($subItems);
                echo '</div>';
            }
            ?>
        </div>
    </nav>

    <!-- Mobile Sidebar -->
    <div class="md:hidden">
        <button class="px-4 py-2 bg-blue-500 text-white rounded-lg mb-2" onclick="document.getElementById('mobileSidebar').classList.toggle('hidden')">เมนู</button>
        <div id="mobileSidebar" class="hidden bg-white shadow-lg rounded-lg p-4 mb-6">
            <?php renderMobileMenu($menus); ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card text-white p-6 rounded-xl shadow-lg card-hover">
            <p class="text-white text-opacity-60 text-sm">ผู้ป่วยทั้งหมด</p>
            <p class="text-3xl font-bold"><?= number_format($totalPatients) ?></p>
        </div>
        <div class="stat-card-2 text-white p-6 rounded-xl shadow-lg card-hover">
            <p class="text-white text-opacity-60 text-sm">ผู้ป่วยใหม่วันนี้</p>
            <p class="text-3xl font-bold"><?= number_format($newPatientsToday) ?></p>
        </div>
        <div class="stat-card-3 text-white p-6 rounded-xl shadow-lg card-hover">
            <p class="text-white text-opacity-60 text-sm">การนัดหมายวันนี้</p>
            <p class="text-3xl font-bold"><?= number_format($totalAppointments) ?></p>
        </div>
        <div class="stat-card-4 text-white p-6 rounded-xl shadow-lg card-hover">
            <p class="text-white text-opacity-60 text-sm">รายได้วันนี้</p>
            <p class="text-3xl font-bold">฿<?= number_format($totalRevenue,2) ?></p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">จำนวนผู้ป่วยรายเดือน (<?= $year+543 ?>)</h3>
            <div style="height:240px;"><canvas id="patientChart"></canvas></div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg card-hover">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">โรคยอดนิยม</h3>
            <div style="height:240px;"><canvas id="diseaseChart"></canvas></div>
        </div>
    </div>
</main>

<script>
document.getElementById('currentDate').textContent = new Date().toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});

// Desktop hover submenu animation
document.querySelectorAll('.group').forEach(g=>{
    const submenu = g.querySelector('.submenu');
    const arrow = g.querySelector('.arrow');
    g.addEventListener('mouseenter',()=>{
        submenu.classList.add('show');
        submenu.classList.remove('hidden');
        if(arrow) arrow.textContent='🔽';
    });
    g.addEventListener('mouseleave',()=>{
        submenu.classList.remove('show');
        submenu.classList.add('hidden');
        if(arrow) arrow.textContent='▶️';
    });
});

// Mobile nested submenu toggle
function toggleSubmenu(btn){
    const sub = btn.nextElementSibling;
    const arrow = btn.querySelector('.arrow');
    if(sub){
        sub.classList.toggle('show');
        arrow.textContent = sub.classList.contains('show') ? '🔽' : '▶️';
    }
}

// Patient Chart
new Chart(document.getElementById('patientChart').getContext('2d'),{
    type:'line',
    data:{labels:['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
          datasets:[{label:'จำนวนผู้ป่วย',data:<?= $monthlyDataJson ?>,borderColor:'#667eea',backgroundColor:'rgba(102,126,234,0.1)',borderWidth:3,fill:true,tension:0.4}]},
    options:{responsive:true,maintainAspectRatio:false}
});

// Disease Chart
new Chart(document.getElementById('diseaseChart').getContext('2d'),{
    type:'doughnut',
    data:{labels:<?= $diseaseLabels ?>,datasets:[{data:<?= $diseaseCounts ?>,backgroundColor:['#667eea','#f093fb','#4facfe','#43e97b','#fa709a'],borderWidth:0}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},cutout:'60%'}
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
