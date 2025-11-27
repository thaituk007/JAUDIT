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
$username = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : '';
$loggedIn = isset($_SESSION['user']);
$today = date('Y-m-d');

// Logout process
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

$error = '';

// ตรวจสอบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputUser = trim($_POST['username'] ?? '');
    $inputPass = trim($_POST['password'] ?? '');

    // ตัวอย่างจำลอง ล็อกอินง่าย ๆ
    if ($inputUser === 'admin' && $inputPass === '123456') {
        $_SESSION['user'] = $inputUser;
        header("Location: index.php");
        exit();
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ - JHCISAUDIT</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
body { font-family: 'Kanit', sans-serif; background:#f9fafb; }
.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.card-hover { transition: all 0.3s ease; }
.card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
</style>
</head>
<body class="bg-gray-50">

<!-- Navigation -->
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-heartbeat text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">JHCISAUDIT</h1>
                    <p class="text-xs text-gray-500">Health Report & Tools</p>
                </div>
            </div>
            <button id="menuToggle" class="md:hidden text-gray-700">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Login Hero Section -->
<section class="gradient-bg text-white py-20">
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg overflow-hidden p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                <i class="fas fa-heartbeat text-white text-2xl"></i>
            </div>
            <h2 class="text-3xl font-bold mt-4 text-gray-800">JHCISAUDIT</h2>
            <p class="text-gray-500 mt-1">เข้าสู่ระบบเพื่อตรวจสอบและวิเคราะห์ข้อมูลสุขภาพ <br>
              สำหรับ JHCIS</p>
        </div>

        <?php if($error): ?>
            <p class="bg-red-100 text-red-700 p-2 mb-4 rounded text-center"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block mb-1 font-medium text-gray-700">ชื่อผู้ใช้</label>
                <input type="text" name="username" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
            </div>

            <div>
                <label class="block mb-1 font-medium text-gray-700">รหัสผ่าน</label>
                <input type="password" name="password" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white p-2 rounded hover:from-blue-600 hover:to-purple-700 transition-colors">เข้าสู่ระบบ</button>
        </form>

        <p class="mt-6 text-gray-500 text-center text-sm">
            <strong>ตัวอย่าง</strong>: username: <strong>admin</strong>, password: <strong>1234</strong>
        </p>
    </div>
    <!-- Header Info Center -->
        <div class="container mx-auto text-center space-x-4"> <br>
            <span>🧭 <strong><?= htmlspecialchars($config['app_name']) ?></strong></span>
            <span>🛠️ Version <?= htmlspecialchars($config['version']) ?></span>
            <span>💡 PHP <?= phpversion(); ?></span>
            <span>📅 <?= $today ?></span>
        </div>
</section>
</body>
</html>
