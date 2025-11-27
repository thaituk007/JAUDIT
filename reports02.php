<?php
// เริ่มต้น Session
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

// โหลด config (ถ้ามีไฟล์ config.php)
$config = file_exists('config.php') ? include 'config.php' : [];

// ข้อมูลผู้ใช้งาน
$username   = isset($_SESSION['username']) ? $_SESSION['username'] : "ผู้ดูแลระบบ";
$last_login = date("d/m/Y H:i");

// ฟังก์ชัน Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ระบบรายงาน - Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
      @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');
      body { font-family: 'Prompt', sans-serif; }
      .menu-item { transition: all 0.3s ease; }
      .submenu { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
      .submenu.open { max-height: 500px; }
      .rotate-180 { transform: rotate(180deg); }
      .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
      .card-hover { transition: all 0.3s ease; }
      .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
      .pulse-animation { animation: pulse 2s infinite; }
      @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
  </style>
</head>
<body class="bg-gray-50">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-80 bg-white shadow-lg border-r border-gray-200">
      <!-- Header -->
      <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-indigo-600">
        <h1 class="text-xl font-bold text-white">📊 ระบบรายงาน</h1>
        <p class="text-blue-100 text-sm mt-1">JHCISAUDIT Health Report & Tools Dashboard</p>
      </div>

      <!-- Navigation Menu -->
      <nav class="p-4 space-y-2">
        <!-- รายงาน -->
        <div class="menu-group">
          <button onclick="toggleSubmenu('reports')"
                  class="menu-item w-full flex items-center justify-between p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg font-medium">
            <div class="flex items-center">
              <span class="text-lg mr-3">📈</span>
              <span>รายงาน</span>
            </div>
            <svg id="reports-arrow" class="w-4 h-4 transition-transform" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4
                4a1 1 0 01-1.414 0l-4-4a1 1 0
                010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
          <div id="reports-submenu" class="submenu ml-6 mt-2 space-y-1">
            <a href="#" onclick="loadContent('รายงานสถิติผู้ป่วย')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">
               รายงานสถิติผู้ป่วย</a>
            <a href="#" onclick="loadContent('รายงานการเงิน')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">
               รายงานการเงิน</a>
            <a href="#" onclick="loadContent('รายงานโรคประจำถิ่น')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">
               รายงานโรคประจำถิ่น</a>
            <a href="#" onclick="loadContent('รายงานยา/เวชภัณฑ์')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">
               รายงานยา/เวชภัณฑ์</a>
            <a href="#" onclick="loadContent('ส่งออกข้อมูล')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">
               ส่งออกข้อมูล</a>
          </div>
        </div>

        <!-- ตรวจสอบข้อมูล -->
        <div class="menu-group">
          <button onclick="toggleSubmenu('data-check')"
                  class="menu-item w-full flex items-center justify-between p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg font-medium">
            <div class="flex items-center">
              <span class="text-lg mr-3">🔍</span>
              <span>ข้อมูลพื้นฐาน 12 แฟ้ม</span>
            </div>
            <svg id="data-check-arrow" class="w-4 h-4 transition-transform" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5.293 7.293a1 1
                0 011.414 0L10 10.586l3.293-3.293a1
                1 0 111.414 1.414l-4
                4a1 1 0 01-1.414 0l-4-4a1
                1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
          <div id="data-check-submenu" class="submenu ml-6 mt-2 space-y-1">
            <a href="#" onclick="loadContent('PERSON')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">PERSON</a>
            <a href="#" onclick="loadContent('DEATH')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">DEATH</a>
          </div>
        </div>

        <!-- ตั้งค่า -->
        <div class="menu-group">
          <button onclick="toggleSubmenu('settings')"
                  class="menu-item w-full flex items-center justify-between p-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-lg font-medium">
            <div class="flex items-center">
              <span class="text-lg mr-3">⚙️</span>
              <span>ตั้งค่า</span>
            </div>
            <svg id="settings-arrow" class="w-4 h-4 transition-transform" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5.293 7.293a1
                1 0 011.414 0L10 10.586l3.293-3.293a1
                1 0 111.414 1.414l-4
                4a1 1 0 01-1.414 0l-4-4a1
                1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
          <div id="settings-submenu" class="submenu ml-6 mt-2 space-y-1">
            <a href="#" onclick="loadContent('ข้อมูลส่วนตัว')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">ข้อมูลส่วนตัว</a>
            <a href="#" onclick="loadContent('เปลี่ยนรหัสผ่าน')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">เปลี่ยนรหัสผ่าน</a>
            <a href="#" onclick="loadContent('การแจ้งเตือน')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">การแจ้งเตือน</a>
            <a href="#" onclick="loadContent('สำรองข้อมูล')"
               class="block p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded text-sm">สำรองข้อมูล</a>
            <a href="?logout=1"
               class="block p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded text-sm font-medium">ออกจากระบบ</a>
          </div>
        </div>
      </nav>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
      <!-- Top Bar -->
      <header class="bg-white shadow-sm border-b border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 id="page-title" class="text-2xl font-bold text-gray-800">แดชบอร์ด</h2>
            <p class="text-gray-600 text-sm mt-1">ยินดีต้อนรับสู่ระบบรายงาน</p>

            <!-- ปุ่มกลับหน้าแรก -->
            <a href="index.php"
               class="inline-flex items-center mt-3 px-5 py-2.5
                      bg-gradient-to-r from-blue-500 to-indigo-600
                      text-white font-medium rounded-full shadow-md
                      hover:from-indigo-600 hover:to-blue-500
                      transform hover:scale-105 transition">
              ⬅️ กลับหน้าแรก
            </a>
          </div>
          <div class="flex items-center space-x-4">
            <div class="text-right">
              <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($username) ?></p>
              <p class="text-xs text-gray-500">เข้าสู่ระบบล่าสุด: <?= $last_login ?></p>
            </div>
            <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold">
              <?= strtoupper(substr($username,0,1)) ?>
            </div>
          </div>
        </div>
      </header>

      <!-- Content Area -->
      <main class="flex-1 p-6">
        <div id="content-area" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-2">📊 Dashboard</h3>
          <p class="text-gray-600">หน้านี้เป็น PHP Dashboard ที่ดึงข้อมูลแบบ dynamic ได้</p>

          <!-- ปุ่มกลับหน้าแรก (ล่างสุด) -->
          <div class="mt-6">
            <a href="index.php"
               class="inline-flex items-center px-5 py-2.5
                      bg-gradient-to-r from-blue-500 to-indigo-600
                      text-white font-medium rounded-full shadow-md
                      hover:from-indigo-600 hover:to-blue-500
                      transform hover:scale-105 transition">
              ⬅️ กลับหน้าแรก
            </a>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Scripts -->
  <script>
    function toggleSubmenu(menuId) {
      const submenu = document.getElementById(menuId + '-submenu');
      const arrow   = document.getElementById(menuId + '-arrow');
      if (submenu.classList.contains('open')) {
        submenu.classList.remove('open');
        arrow.classList.remove('rotate-180');
      } else {
        document.querySelectorAll('.submenu').forEach(menu => menu.classList.remove('open'));
        document.querySelectorAll('[id$="-arrow"]').forEach(arr => arr.classList.remove('rotate-180'));
        submenu.classList.add('open');
        arrow.classList.add('rotate-180');
      }
    }

    function loadContent(pageName) {
      const pageTitle   = document.getElementById('page-title');
      const contentArea = document.getElementById('content-area');
      pageTitle.textContent = pageName;
      contentArea.innerHTML = `<div class="p-6 text-gray-700">📌 กำลังโหลดเนื้อหาของหน้า <b>${pageName}</b> ...</div>`;
    }
  </script>
</body>
</html>
