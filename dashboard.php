<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

$config = include __DIR__ . "/config.php";

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==================== ดึงข้อมูล ====================
$totalPatients = $pdo->query("SELECT COUNT(*) as c FROM person WHERE dischargetype<>1")->fetch()['c'] ?? 0;
$elderly = $pdo->query("SELECT COUNT(*) as c FROM person WHERE TIMESTAMPDIFF(YEAR, birth, CURDATE()) >= 60 AND typelive IN (1,3)")->fetch()['c'] ?? 0;
$male = $pdo->query("SELECT COUNT(*) as c FROM person WHERE sex='1' AND typelive IN (1,3) AND dischargetype<>1")->fetch()['c'] ?? 0;
$female = $pdo->query("SELECT COUNT(*) as c FROM person WHERE sex='2' AND typelive IN (1,3) AND dischargetype<>1")->fetch()['c'] ?? 0;
$patients = $pdo->query("SELECT pid, idcard as cid, prename, fname, lname, birth, sex FROM person ORDER BY pid DESC LIMIT 10")->fetchAll();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(visitdate, '%Y-%m') as ym, COUNT(*) as c
    FROM visit
    WHERE visitdate IS NOT NULL
    GROUP BY ym
    ORDER BY ym DESC LIMIT 12
")->fetchAll();

$chartLabels = array_reverse(array_column($monthly, 'ym'));
$chartData   = array_reverse(array_column($monthly, 'c'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($config['app_name']) ?> - Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: 'Prompt', sans-serif; }
    #apiText, #thaiTime {
      font-weight: 500;
      font-size: 1.125rem;
      transition: color 0.5s linear;
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen">
  <div class="container mx-auto p-6">

    <!-- ปุ่มกลับหน้าหลัก + ชื่อระบบ + ข้อความ API + วันเวลาไทย -->
    <div class="mb-4 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <a href="index.php" class="inline-block bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-5 py-2 rounded-xl shadow-md hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
          กลับหน้าหลัก
        </a>
        <span class="text-gray-800 font-semibold text-lg">
          jAUDIT Report 📊
          <span id="apiText">ข้อมูลผ่าน API</span>
        </span>
      </div>
      <div id="thaiTime"></div>
    </div>

    <!-- Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="bg-white shadow-md rounded-2xl p-4 text-center">
        <h2 class="text-gray-500">ผู้ป่วยทั้งหมด</h2>
        <p class="text-3xl font-bold text-blue-600"><?= number_format($totalPatients) ?></p>
      </div>
      <div class="bg-white shadow-md rounded-2xl p-4 text-center">
        <h2 class="text-gray-500">ผู้สูงอายุ (60+)</h2>
        <p class="text-3xl font-bold text-green-600"><?= number_format($elderly) ?></p>
      </div>
      <div class="bg-white shadow-md rounded-2xl p-4 text-center">
        <h2 class="text-gray-500">เพศชาย</h2>
        <p class="text-3xl font-bold text-indigo-600"><?= number_format($male) ?></p>
      </div>
      <div class="bg-white shadow-md rounded-2xl p-4 text-center">
        <h2 class="text-gray-500">เพศหญิง</h2>
        <p class="text-3xl font-bold text-pink-600"><?= number_format($female) ?></p>
      </div>
    </div>

    <!-- Chart -->
    <div class="bg-white shadow-md rounded-2xl p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">จำนวนผู้ป่วยรายเดือน</h2>
      <canvas id="patientsChart" height="65"></canvas>
    </div>

  </div>

  <script>
    // Chart.js
    const ctx = document.getElementById('patientsChart');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
          label: 'จำนวนผู้ป่วย',
          data: <?= json_encode($chartData) ?>,
          borderColor: 'rgba(37, 99, 235, 1)',
          backgroundColor: 'rgba(37, 99, 235, 0.2)',
          borderWidth: 2,
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } }
      }
    });

    // สีตัวอักษร 36 เฉด
    const colors = ['#EF4444','#F97316','#FACC15','#22C55E','#3B82F6','#8B5CF6','#EC4899'];
    const apiText = document.getElementById('apiText');
    const thaiTime = document.getElementById('thaiTime');
    let colorIndex = 0;

    const thaiDays = ["อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์"];
    const thaiMonths = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];

    function updateThaiTime() {
      const now = new Date();
      const w = now.getDay();
      const d = now.getDate();
      const m = now.getMonth();
      const y = now.getFullYear() + 543;
      const hms = now.toLocaleTimeString('th-TH', { hour12:false });

      thaiTime.textContent = `วัน${thaiDays[w]}ที่ ${d} ${thaiMonths[m]} ${y} ${hms}`;

      // เปลี่ยนสีทั้งสอง
      apiText.style.color = colors[colorIndex];
      thaiTime.style.color = colors[colorIndex];
      colorIndex = (colorIndex + 1) % colors.length;
    }

    updateThaiTime();
    setInterval(updateThaiTime, 500); // เปลี่ยนสีทุก 0.5 วินาที
  </script>
</body>
<?php include 'footer.php'; ?>
</html>
