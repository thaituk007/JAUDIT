<?php
set_time_limit(300); // เพิ่มเวลารันสคริปต์เป็น 5 นาที

$config = require 'config.php';
date_default_timezone_set('Asia/Bangkok');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Backup Database Progress</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background: #f0f4f8;
    margin: 0;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .container {
    background: #fff;
    width: 420px;
    padding: 2rem 2.5rem 3rem;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    text-align: center;
  }
  h1 {
    font-weight: 600;
    margin-bottom: 2rem;
    color: #007BFF;
  }
  .progress-bar {
    position: relative;
    width: 100%;
    height: 30px;
    background-color: #e0e0e0;
    border-radius: 15px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
    margin: 1rem 0 0.5rem;
  }
  .progress-bar-inner {
    height: 100%;
    background: linear-gradient(90deg, #007BFF 0%, #00d4ff 100%);
    width: 0%;
    transition: width 0.6s ease;
    border-radius: 15px 0 0 15px;
    position: relative;
  }
  .progress-needle {
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 30px;
    background: #ff3b30;
    border-radius: 4px;
    box-shadow: 0 0 5px rgba(255,59,48,0.7);
    transition: left 0.6s ease;
    pointer-events: none;
  }
  .message {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
  }
  .time-info {
    font-size: 0.9rem;
    color: #555;
    margin-top: 15px;
    line-height: 1.3;
  }
  a.download-link {
    display: inline-block;
    margin-top: 1.8rem;
    padding: 10px 24px;
    font-weight: 600;
    color: #fff;
    background-color: #007BFF;
    border-radius: 30px;
    text-decoration: none;
    box-shadow: 0 6px 12px rgba(0,123,255,0.4);
    transition: background-color 0.3s ease;
  }
  a.download-link:hover {
    background-color: #0056b3;
  }
  p.error {
    color: #d32f2f;
    font-weight: 600;
  }
</style>

<script>
function updateProgress(percent, message) {
  const barInner = document.querySelector('.progress-bar-inner');
  const needle = document.querySelector('.progress-needle');
  const msg = document.querySelector('.message');
  barInner.style.width = percent + '%';
  needle.style.left = percent + '%';
  msg.textContent = message + ' (' + percent + '%)';
}
</script>
</head>
<body>
<div class="container">
  <h1>📦 สำรองฐานข้อมูล (Backup Database)</h1>

<?php
function flushProgress($percent, $message) {
    echo "<script>updateProgress($percent, " . json_encode($message) . ");</script>\n";
    @ob_flush(); flush();
}

// ====== ตรวจสอบและสร้างโฟลเดอร์ backups และ backups/ปี-เดือน ======
$baseDir = __DIR__ . '/backups';
$monthFolder = date('Y-m');
$backupDir = "$baseDir/$monthFolder";

// ตรวจสอบโฟลเดอร์ backups
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0777, true)) {
        echo "<p class='error'>❌ ไม่สามารถสร้างโฟลเดอร์ backups/</p>";
        exit;
    }
}

// ตรวจสอบสิทธิ์เขียน
if (!is_writable($baseDir)) {
    echo "<p class='error'>⚠️ โฟลเดอร์ backups/ ไม่สามารถเขียนได้<br>กรุณาให้สิทธิ์เขียน (chmod 777 หรือ ตั้งสิทธิ์ให้ IIS_IUSRS)</p>";
    exit;
}

// ตรวจสอบโฟลเดอร์รายเดือน
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        echo "<p class='error'>❌ ไม่สามารถสร้างโฟลเดอร์ $monthFolder/</p>";
        exit;
    }
}

$totalSteps = 5;
$step = 0;

echo <<<HTML
<div class="progress-bar">
  <div class="progress-bar-inner"></div>
  <div class="progress-needle"></div>
</div>
<div class="message"></div>
HTML;

$startTime = microtime(true);
flushProgress(0, "เริ่มต้นการสำรองข้อมูล");

// ลบ ZIP ที่เก่ากว่า 7 วันในโฟลเดอร์นี้
$zipFiles = glob("$backupDir/*.zip");
foreach ($zipFiles as $file) {
    if (filemtime($file) < time() - (86400 * 7)) {
        unlink($file);
    }
}

$date = date('Y-m-d_H-i-s');
$sqlFile = "backup_{$date}.sql";
$zipFile = "backup_{$date}.zip";
$sqlPath = "$backupDir/$sqlFile";
$zipPath = "$backupDir/$zipFile";

$mysqldump = '"C:\\Program Files\\JHCIS\\MySQL\\bin\\mysqldump.exe"';

$step++;
flushProgress(($step/$totalSteps)*100, "เริ่มสำรองฐานข้อมูล (mysqldump)");

$command = sprintf(
    '%s --user=%s --password=%s --host=%s --port=%s %s > "%s"',
    $mysqldump,
    escapeshellarg($config['db_user']),
    escapeshellarg($config['db_pass']),
    escapeshellarg($config['db_host']),
    escapeshellarg($config['db_port']),
    escapeshellarg($config['db_name']),
    $sqlPath
);

exec($command, $output, $result);

if ($result !== 0 || !file_exists($sqlPath)) {
    echo "<p class='error'>❌ สำรองฐานข้อมูลล้มเหลว (mysqldump error code: $result)</p>";
    exit;
}

$step++;
flushProgress(($step/$totalSteps)*100, "สำรองฐานข้อมูลเสร็จ");

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
    $zip->addFile($sqlPath, $sqlFile);
    $zip->close();

    $step++;
    flushProgress(($step/$totalSteps)*100, "บีบอัดไฟล์สำเร็จ");

    unlink($sqlPath);

    $step++;
    flushProgress(($step/$totalSteps)*100, "ลบไฟล์ .sql ต้นฉบับแล้ว");

    $step++;
    flushProgress(($step/$totalSteps)*100, "สำรองฐานข้อมูลและบีบอัดเสร็จสมบูรณ์");

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    echo "<p style='font-weight:600; margin-top:20px;'>✅ สำรองฐานข้อมูลเสร็จสมบูรณ์</p>";
    echo "<a class='download-link' href='backups/$monthFolder/$zipFile' target='_blank'>ดาวน์โหลดไฟล์ Backup (.zip)</a>";

    echo '<div class="time-info">';
    echo "<p>🕒 เวลาเริ่มต้น: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>⏳ เวลาที่ใช้: " . number_format($duration, 2) . " วินาที</p>";
    echo '</div>';

} else {
    echo "<p class='error'>❌ ไม่สามารถสร้างไฟล์ ZIP ได้</p>";
}
?>
</div>
</body>
</html>
