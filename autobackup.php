<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

$config = require __DIR__ . '/config.php';

// ================= CONFIG ==================
$backupDir = __DIR__ . '/backup';
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);
$backupPass = "admin123";
$lockFile = sys_get_temp_dir() . "/backup_lock.txt";
// ===========================================

// ตรวจสอบล็อกอิน
if (!isset($_SESSION['auth'])) {
  if (isset($_POST['password'])) {
    if ($_POST['password'] === $backupPass) {
      $_SESSION['auth'] = true;
      header("Location: ?");
      exit;
    } else $error = "รหัสผ่านไม่ถูกต้อง!";
  }
  echo <<<HTML
  <!DOCTYPE html><html lang="th"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>เข้าสู่ระบบสำรองข้อมูล</title>
  <style>
  body{font-family:sans-serif;background:#0f172a;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;}
  form{background:#1e293b;padding:2em;border-radius:10px;box-shadow:0 0 10px #0003;}
  input{padding:10px;border:none;border-radius:5px;width:200px;}
  button{padding:10px 20px;border:none;border-radius:5px;background:#22c55e;color:#fff;margin-top:10px;cursor:pointer;}
  </style></head><body>
  <form method="post">
    <h2>🔐 เข้าสู่ระบบสำรองข้อมูล</h2>
    <input type="password" name="password" placeholder="รหัสผ่าน"><br>
    <button>เข้าสู่ระบบ</button>
    <div style="color:#f87171;"><?php echo isset($error) ? $error : ''; ?></div>
  </form></body></html>
  HTML;
  exit;
}

// === ACTION HANDLER ===
if (isset($_GET['action'])) {
  $action = $_GET['action'];

  // Run backup
  if ($action === 'run') {
    if (file_exists($lockFile)) {
      echo json_encode(['success' => false, 'message' => 'มีการสำรองข้อมูลอยู่แล้ว']);
      exit;
    }
    file_put_contents($lockFile, time());
    $filename = "jhcisdbbackup_" . date("Ymd_His") . ".sql";
    $zipfile  = str_replace(".sql", ".zip", $filename);
    $fullpath = "$backupDir/$filename";
    $zipath   = "$backupDir/$zipfile";

    $cmd = sprintf(
      'mysqldump -h%s -P%s -u%s -p%s %s > "%s"',
      escapeshellarg($config['db_host']),
      escapeshellarg($config['db_port']),
      escapeshellarg($config['db_user']),
      escapeshellarg($config['db_pass']),
      escapeshellarg($config['db_name']),
      $fullpath
    );

    $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (is_resource($process)) {
      fclose($pipes[0]);
      while (!feof($pipes[1])) {
        echo "data:" . json_encode(array('progress' => 10)) . "\n\n";
        ob_flush(); flush();
        usleep(300000);
      }
      fclose($pipes[1]);
      proc_close($process);
      // zip
      $zip = new ZipArchive();
      if ($zip->open($zipath, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($fullpath, basename($fullpath));
        $zip->close();
        unlink($fullpath);
      }
    }
    unlink($lockFile);
    echo json_encode(['success' => true, 'message' => 'สำรองข้อมูลเรียบร้อยแล้ว', 'file' => basename($zipath)]);
    exit;
  }

  // Download
  if ($action === 'download') {
    $files = glob("$backupDir/*.zip");
    if (!$files) {
      http_response_code(404);
      echo "ไม่พบไฟล์สำรอง";
      exit;
    }
    $latest = end($files);
    header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=" . basename($latest));
    readfile($latest);
    exit;
  }

  // Progress Check
  if ($action === 'progress') {
    if (file_exists($lockFile)) {
      echo json_encode(['running' => true]);
    } else {
      echo json_encode(['running' => false]);
    }
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($config['app_name']) ?> - Auto Backup</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f172a;--card:#1e293b;--green:#22c55e;--red:#ef4444;}
body{margin:0;font-family:'Prompt',sans-serif;background:var(--bg);color:#fff;text-align:center;}
.card{background:var(--card);margin:2em auto;padding:2em;border-radius:12px;max-width:600px;box-shadow:0 0 15px #0005;}
button{background:var(--green);border:none;border-radius:8px;padding:10px 20px;font-size:1em;color:#fff;cursor:pointer;}
button:disabled{background:#555;cursor:not-allowed;}
.progress{height:20px;background:#334155;border-radius:10px;overflow:hidden;margin-top:15px;}
.progress-bar{height:100%;width:0%;background:var(--green);transition:width .4s;}
footer{margin-top:20px;font-size:.8em;color:#9ca3af;}
</style>
</head>
<body>
<div class="card">
  <h2>🗄️ <?= htmlspecialchars($config['app_name']) ?> Backup System</h2>
  <p>ฐานข้อมูล: <b><?= htmlspecialchars($config['db_name']) ?></b></p>
  <button id="runBackupBtn">🚀 สำรองข้อมูลทันที</button>
  <button id="downloadLatestBtn">⬇️ ดาวน์โหลดล่าสุด</button>
  <div class="progress"><div id="progressBar" class="progress-bar"></div></div>
  <div id="statusText"></div>
</div>
<footer>v<?= htmlspecialchars($config['version']) ?> | <?= htmlspecialchars($config['dateversion']) ?></footer>

<script>
let isBackupRunning = false;

document.getElementById('runBackupBtn').addEventListener('click', async function() {
  if (isBackupRunning) return alert("กำลังสำรองข้อมูลอยู่...");
  this.disabled = true;
  document.getElementById('statusText').innerText = "เริ่มการสำรองข้อมูล...";
  const res = await fetch('?action=run');
  try {
    const data = await res.json();
    if (data.success) {
      document.getElementById('statusText').innerText = "✅ " + data.message;
      document.getElementById('progressBar').style.width = "100%";
    } else {
      document.getElementById('statusText').innerText = "❌ " + data.message;
    }
  } catch(e) {
    document.getElementById('statusText').innerText = "❌ เกิดข้อผิดพลาด: " + e.message;
  }
  this.disabled = false;
  isBackupRunning = false;
});

document.getElementById('downloadLatestBtn').addEventListener('click', async function() {
  const res = await fetch('?action=download');
  if (res.ok) {
    const blob = await res.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'latest_backup.zip';
    document.body.appendChild(a);
    a.click();
    a.remove();
  } else {
    alert("❌ ไม่พบไฟล์สำรองล่าสุด");
  }
});
</script>
</body>
</html>
