<?php
set_time_limit(300);

$config = require 'config.php';
date_default_timezone_set('Asia/Bangkok');

// ====== ฟังก์ชันเสริม ======
function writeLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Backup Database System</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Prompt', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .container {
    background: #fff;
    width: 100%;
    max-width: 520px;
    padding: 2.5rem;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  }
  .header {
    text-align: center;
    margin-bottom: 2rem;
  }
  .header h1 {
    font-weight: 700;
    font-size: 1.8rem;
    color: #667eea;
    margin-bottom: 0.5rem;
  }
  .header p {
    color: #666;
    font-size: 0.95rem;
  }
  .progress-section {
    margin: 2rem 0;
  }
  .progress-bar {
    position: relative;
    width: 100%;
    height: 36px;
    background-color: #e8eaf6;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
  }
  .progress-bar-inner {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    width: 0%;
    transition: width 0.4s ease;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 12px;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
  }
  .message {
    text-align: center;
    font-size: 1.05rem;
    font-weight: 600;
    color: #333;
    margin-top: 1rem;
    min-height: 28px;
  }
  .stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  .stat-card {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 12px;
    text-align: center;
    border: 2px solid #e9ecef;
  }
  .stat-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.3rem;
  }
  .stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #667eea;
  }
  .info-box {
    background: #f0f4ff;
    padding: 1.2rem;
    border-radius: 12px;
    margin-top: 1.5rem;
    border-left: 4px solid #667eea;
  }
  .info-box p {
    font-size: 0.9rem;
    color: #555;
    margin-bottom: 0.5rem;
    line-height: 1.5;
  }
  .info-box p:last-child {
    margin-bottom: 0;
  }
  .info-box strong {
    color: #333;
  }
  .download-link {
    display: block;
    margin-top: 1.8rem;
    padding: 14px 28px;
    font-weight: 700;
    font-size: 1.05rem;
    color: #fff;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50px;
    text-decoration: none;
    text-align: center;
    box-shadow: 0 8px 20px rgba(102,126,234,0.4);
    transition: all 0.3s ease;
  }
  .download-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(102,126,234,0.5);
  }
  .error {
    background: #ffebee;
    color: #c62828;
    padding: 1.2rem;
    border-radius: 12px;
    border-left: 4px solid #c62828;
    font-weight: 600;
    margin-top: 1rem;
  }
  .success-icon {
    font-size: 3rem;
    text-align: center;
    margin-bottom: 1rem;
  }
  .spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 1.5rem auto;
  }
  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>

<script>
function updateProgress(percent, message) {
  const barInner = document.querySelector('.progress-bar-inner');
  const msg = document.querySelector('.message');
  barInner.style.width = percent + '%';
  barInner.textContent = Math.round(percent) + '%';
  msg.textContent = message;
}
function updateStats(time, size) {
  document.getElementById('timeValue').textContent = time;
  document.getElementById('sizeValue').textContent = size;
}
</script>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🗄️ ระบบสำรองฐานข้อมูล</h1>
    <p>Database Backup System</p>
  </div>

<?php
function flushProgress($percent, $message) {
    echo "<script>updateProgress($percent, " . json_encode($message) . ");</script>\n";
    @ob_flush(); flush();
}

// ====== สร้างโครงสร้างโฟลเดอร์ ======
$baseDir = __DIR__ . '/backups';
$monthFolder = date('Y-m');
$backupDir = "$baseDir/$monthFolder";
$logFile = "$baseDir/backup.log";

if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        echo "<div class='error'>❌ ไม่สามารถสร้างโฟลเดอร์ backups/ ได้</div></div></body></html>";
        exit;
    }
}

if (!is_writable($baseDir)) {
    echo "<div class='error'>⚠️ โฟลเดอร์ backups/ ไม่มีสิทธิ์เขียน<br>กรุณาตั้งสิทธิ์: <code>chmod 755 backups/</code></div></div></body></html>";
    exit;
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// ====== เริ่มต้นการทำงาน ======
$startTime = microtime(true);
$date = date('Y-m-d_H-i-s');
$sqlFile = "backup_{$date}.sql";
$zipFile = "backup_{$date}.zip";
$sqlPath = "$backupDir/$sqlFile";
$zipPath = "$backupDir/$zipFile";

writeLog("เริ่มการสำรองข้อมูล", $logFile);

echo <<<HTML
<div class="progress-section">
  <div class="progress-bar">
    <div class="progress-bar-inner">0%</div>
  </div>
  <div class="message">เตรียมการ...</div>
  <div class="spinner"></div>
</div>

<div class="stats">
  <div class="stat-card">
    <div class="stat-label">⏱️ เวลาที่ใช้</div>
    <div class="stat-value" id="timeValue">0s</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">📦 ขนาดไฟล์</div>
    <div class="stat-value" id="sizeValue">-</div>
  </div>
</div>
HTML;

$totalSteps = 6;
$step = 0;

// ====== ขั้นตอนที่ 1: ลบไฟล์เก่า ======
$step++;
flushProgress(($step/$totalSteps)*100, "ลบไฟล์สำรองเก่า (เก็บไว้ 7 วัน)");

$zipFiles = glob("$backupDir/*.zip");
$deletedCount = 0;
foreach ($zipFiles as $file) {
    if (filemtime($file) < time() - (86400 * 7)) {
        unlink($file);
        $deletedCount++;
    }
}
if ($deletedCount > 0) {
    writeLog("ลบไฟล์เก่า $deletedCount ไฟล์", $logFile);
}

// ====== ขั้นตอนที่ 2: สร้าง MySQL config file (ปลอดภัยกว่า) ======
$step++;
flushProgress(($step/$totalSteps)*100, "เตรียมการเชื่อมต่อฐานข้อมูล");

$cnfFile = sys_get_temp_dir() . '/mysqldump_' . uniqid() . '.cnf';
$cnfContent = <<<CNF
[client]
user={$config['db_user']}
password={$config['db_pass']}
host={$config['db_host']}
port={$config['db_port']}
CNF;
file_put_contents($cnfFile, $cnfContent);
chmod($cnfFile, 0600);

// ====== ขั้นตอนที่ 3: mysqldump ======
$step++;
flushProgress(($step/$totalSteps)*100, "กำลังสำรองฐานข้อมูล (mysqldump)");

$mysqldump = '"C:\\Program Files\\JHCIS\\MySQL\\bin\\mysqldump.exe"';

$command = sprintf(
    '%s --defaults-extra-file="%s" --single-transaction --routines --triggers --events --quick --lock-tables=false %s > "%s" 2>&1',
    $mysqldump,
    $cnfFile,
    escapeshellarg($config['db_name']),
    $sqlPath
);

exec($command, $output, $result);
unlink($cnfFile); // ลบไฟล์ config ทันที

if ($result !== 0 || !file_exists($sqlPath)) {
    $errorMsg = "mysqldump failed (code: $result)";
    writeLog("ERROR: $errorMsg - " . implode(" ", $output), $logFile);
    echo "<div class='error'>❌ สำรองฐานข้อมูลล้มเหลว<br><small>Error code: $result</small></div></div></body></html>";
    exit;
}

// ตรวจสอบขนาดไฟล์
$sqlSize = filesize($sqlPath);
if ($sqlSize < 1024) {
    writeLog("WARNING: ไฟล์ backup มีขนาดเล็กผิดปกติ ($sqlSize bytes)", $logFile);
    echo "<div class='error'>⚠️ ไฟล์ backup มีขนาดเล็กผิดปกติ (อาจสำรองไม่สมบูรณ์)</div></div></body></html>";
    exit;
}

$step++;
flushProgress(($step/$totalSteps)*100, "สำรองฐานข้อมูลสำเร็จ");
writeLog("Backup SQL สำเร็จ - ขนาด: " . formatBytes($sqlSize), $logFile);

// ====== ขั้นตอนที่ 5: บีบอัด ZIP ======
$step++;
flushProgress(($step/$totalSteps)*100, "กำลังบีบอัดไฟล์ (.zip)");

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    writeLog("ERROR: ไม่สามารถสร้างไฟล์ ZIP", $logFile);
    echo "<div class='error'>❌ ไม่สามารถสร้างไฟล์ ZIP ได้</div></div></body></html>";
    exit;
}

$zip->addFile($sqlPath, $sqlFile);
$zip->setCompressionName($sqlFile, ZipArchive::CM_DEFLATE, 9);
$zip->close();

$zipSize = filesize($zipPath);
writeLog("บีบอัดสำเร็จ - ขนาด: " . formatBytes($zipSize), $logFile);

unlink($sqlPath);

// ====== ขั้นตอนที่ 6: เสร็จสิ้น ======
$step++;
$endTime = microtime(true);
$duration = $endTime - $startTime;

flushProgress(100, "✅ สำรองฐานข้อมูลสำเร็จ");
writeLog("สำรองข้อมูลสำเร็จ - ใช้เวลา: " . number_format($duration, 2) . " วินาที", $logFile);

echo "<script>
  updateStats('" . number_format($duration, 2) . "s', '" . formatBytes($zipSize) . "');
  document.querySelector('.spinner').remove();
</script>";

echo "<div class='success-icon'>✅</div>";

echo "<a class='download-link' href='backups/$monthFolder/$zipFile' download>📥 ดาวน์โหลดไฟล์ Backup</a>";

echo "<div class='info-box'>";
echo "<p><strong>📅 วันที่-เวลา:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p><strong>📊 ขนาดไฟล์:</strong> " . formatBytes($zipSize) . "</p>";
echo "<p><strong>⏱️ เวลาที่ใช้:</strong> " . number_format($duration, 2) . " วินาที</p>";
echo "<p><strong>🗂️ ที่เก็บ:</strong> backups/$monthFolder/</p>";
echo "<p><strong>🗄️ ฐานข้อมูล:</strong> {$config['db_name']}</p>";
echo "</div>";

?>
</div>
</body>
</html>
