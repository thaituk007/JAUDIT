<?php
// ========================================
// 🔐 SECURE AUTO BACKUP SYSTEM v2.0
// ========================================

set_time_limit(0);
date_default_timezone_set("Asia/Bangkok");

// --- Security Configuration ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/backup/error.log');

// Session Security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Configuration ---
define('BACKUP_PASSWORD', getenv('BACKUP_PASSWORD') ?: 'Chang3M3!Str0ng#2024'); // เปลี่ยนหรือใช้ ENV
define('MAX_BACKUP_AGE_DAYS', 30);
define('MIN_BACKUP_INTERVAL', 60); // seconds
define('MIN_DISK_SPACE_GB', 1);

$config = require __DIR__ . '/config.php';
$backupDir = __DIR__ . '/backup/';
if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);

$logFile = $backupDir . 'backup_log.txt';
$progressFile = $backupDir . 'progress.json';
$errorLogFile = $backupDir . 'error.log';

// MySQL Config File
$mysqlConfigFile = $backupDir . '.my.cnf';
$mysqldumpPath = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
    ? 'C:\\Program Files\\JHCIS\\MySQL\\bin\\mysqldump.exe'
    : '/usr/bin/mysqldump';

if (!file_exists($progressFile)) {
    file_put_contents($progressFile, json_encode([
        'percent' => 0,
        'message' => 'ยังไม่ได้เริ่ม Backup',
        'status' => 'idle'
    ], JSON_UNESCAPED_UNICODE));
}

// ========================================
// HELPER FUNCTIONS
// ========================================

function logMessage($msg, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [$level] $msg\n";
    file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
}

function logError($msg) {
    global $errorLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] ERROR: $msg\n";
    file_put_contents($errorLogFile, $message, FILE_APPEND | LOCK_EX);
    logMessage($msg, 'ERROR');
}

function logProgress($percent, $msg, $status = 'running') {
    global $progressFile;
    file_put_contents(
        $progressFile,
        json_encode([
            'percent' => $percent,
            'message' => $msg,
            'status' => $status,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        if (!isset($_GET['action']) || $_GET['action'] === 'login') {
            return false; // For POST login
        }
    }
    return true;
}

function checkBackupFolder() {
    global $backupDir;

    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0750, true);
    }

    if (!is_writable($backupDir)) {
        return [
            'status' => false,
            'message' => "❌ ไม่สามารถเขียนไฟล์ได้<br>chmod 750 $backupDir"
        ];
    }

    // Check disk space
    $freeSpace = disk_free_space($backupDir);
    $requiredSpace = MIN_DISK_SPACE_GB * 1073741824;
    if ($freeSpace < $requiredSpace) {
        return [
            'status' => false,
            'message' => "❌ พื้นที่ดิสก์ไม่พอ ต้องการอย่างน้อย " . MIN_DISK_SPACE_GB . " GB"
        ];
    }

    return [
        'status' => true,
        'message' => "✅ โฟลเดอร์ backup/ พร้อมใช้งาน (เหลือ " . round($freeSpace / 1073741824, 2) . " GB)"
    ];
}

function cleanOldBackups($days = MAX_BACKUP_AGE_DAYS) {
    global $backupDir;
    $files = glob($backupDir . 'backup_*.zip');
    $deleted = 0;

    foreach ($files as $f) {
        if (time() - filemtime($f) > $days * 86400) {
            if (@unlink($f)) {
                logMessage("🗑️ ลบไฟล์เก่า: " . basename($f));
                $deleted++;
            }
        }
    }

    return $deleted;
}

function checkBackupRateLimit() {
    if (isset($_SESSION['last_backup_time'])) {
        $elapsed = time() - $_SESSION['last_backup_time'];
        if ($elapsed < MIN_BACKUP_INTERVAL) {
            return [
                'allowed' => false,
                'wait' => MIN_BACKUP_INTERVAL - $elapsed
            ];
        }
    }
    return ['allowed' => true];
}

function validateFileName($fileName) {
    // Only allow backup_YYYYMMDD_HHMMSS.zip format
    return preg_match('/^backup_\d{8}_\d{6}\.zip$/', $fileName);
}

function runBackupImproved() {
    global $config, $mysqldumpPath, $backupDir, $mysqlConfigFile;

    try {
        // Pre-flight checks
        $check = checkBackupFolder();
        if (!$check['status']) {
            throw new Exception($check['message']);
        }

        if (!file_exists($mysqldumpPath)) {
            throw new Exception("ไม่พบ mysqldump: $mysqldumpPath");
        }

        // Create/Update MySQL config file
        if (!file_exists($mysqlConfigFile) || filemtime($mysqlConfigFile) < time() - 3600) {
            $cnfContent = "[client]\n";
            $cnfContent .= "user={$config['db_user']}\n";
            $cnfContent .= "password={$config['db_pass']}\n";
            $cnfContent .= "host={$config['db_host']}\n";
            $cnfContent .= "port={$config['db_port']}\n";

            if (file_put_contents($mysqlConfigFile, $cnfContent) === false) {
                throw new Exception("ไม่สามารถสร้างไฟล์ MySQL config ได้");
            }
            chmod($mysqlConfigFile, 0600);
        }

        $timestamp = date('Ymd_His');
        $fileSQL = $backupDir . "backup_$timestamp.sql";
        $fileZIP = $backupDir . "backup_$timestamp.zip";

        logProgress(10, "กำลังเชื่อมต่อ Database...", 'running');

        // Test connection with timeout
        $conn = @new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name'],
            $config['db_port']
        );

        if ($conn->connect_error) {
            throw new Exception("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
        }

        // Get table count
        $result = $conn->query("SHOW TABLES");
        if (!$result) {
            throw new Exception("ไม่สามารถดึงรายการ tables ได้");
        }

        $tableCount = $result->num_rows;
        $conn->close();

        if ($tableCount === 0) {
            throw new Exception("ไม่พบ tables ใน database");
        }

        logProgress(20, "พบ $tableCount Tables, กำลัง Backup...", 'running');

        // Execute mysqldump
        $cmd = sprintf(
            '"%s" --defaults-extra-file="%s" --single-transaction --quick --lock-tables=false --routines --triggers %s',
            $mysqldumpPath,
            $mysqlConfigFile,
            escapeshellarg($config['db_name'])
        );

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd .= ' > ' . escapeshellarg($fileSQL) . ' 2>&1';
        } else {
            $cmd .= ' > ' . escapeshellarg($fileSQL) . ' 2>&1';
        }

        exec($cmd, $output, $returnCode);

        // Validate backup
        if ($returnCode !== 0) {
            throw new Exception("mysqldump error (code $returnCode): " . implode("\n", $output));
        }

        if (!file_exists($fileSQL)) {
            throw new Exception("ไม่พบไฟล์ SQL ที่ backup");
        }

        $sqlSize = filesize($fileSQL);
        if ($sqlSize < 100) {
            $errorContent = file_get_contents($fileSQL);
            throw new Exception("ไฟล์ backup มีขนาดเล็กเกินไป (${sqlSize} bytes): $errorContent");
        }

        logProgress(60, "Backup สำเร็จ (" . round($sqlSize / 1048576, 2) . " MB), กำลังบีบอัด...", 'running');

        // Create ZIP
        if (!class_exists('ZipArchive')) {
            throw new Exception("ZipArchive extension ไม่พร้อมใช้งาน");
        }

        $zip = new ZipArchive();
        $zipResult = $zip->open($fileZIP, ZipArchive::CREATE);

        if ($zipResult !== TRUE) {
            throw new Exception("ไม่สามารถสร้าง ZIP ได้ (error code: $zipResult)");
        }

        if (!$zip->addFile($fileSQL, basename($fileSQL))) {
            throw new Exception("ไม่สามารถเพิ่มไฟล์ลง ZIP ได้");
        }

        $zip->close();

        // Verify ZIP
        if (!file_exists($fileZIP) || filesize($fileZIP) < 100) {
            throw new Exception("ZIP file มีปัญหา");
        }

        // Clean up
        @unlink($fileSQL);

        logProgress(85, "กำลังล้างไฟล์เก่า...", 'running');
        $deleted = cleanOldBackups(MAX_BACKUP_AGE_DAYS);

        $fileSize = round(filesize($fileZIP) / 1048576, 2);
        $successMsg = "✅ Backup สำเร็จ: " . basename($fileZIP) . " ($fileSize MB)";
        if ($deleted > 0) {
            $successMsg .= " | ลบไฟล์เก่า $deleted ไฟล์";
        }

        logMessage($successMsg);
        logProgress(100, $successMsg, 'success');

        $_SESSION['last_backup_time'] = time();

        return ['success' => true, 'message' => $successMsg, 'file' => basename($fileZIP)];

    } catch (Exception $e) {
        $errorMsg = "❌ Error: " . $e->getMessage();
        logError($errorMsg);
        logProgress(0, $errorMsg, 'error');

        // Cleanup failed backup
        if (isset($fileSQL) && file_exists($fileSQL)) {
            @unlink($fileSQL);
        }
        if (isset($fileZIP) && file_exists($fileZIP)) {
            @unlink($fileZIP);
        }

        return ['success' => false, 'message' => $errorMsg];
    }
}

// ========================================
// AJAX HANDLERS
// ========================================

if (isset($_GET['action']) && $_GET['action'] !== 'downloadFile') {

    // Check authentication
    if (!isset($_SESSION['backup_authenticated'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'กรุณา Login ใหม่'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $res = ['success' => true, 'message' => ''];

    try {
        switch ($_GET['action']) {
            case 'run':
                // Rate limiting
                $rateCheck = checkBackupRateLimit();
                if (!$rateCheck['allowed']) {
                    $res['success'] = false;
                    $res['message'] = "⚠️ กรุณารออีก {$rateCheck['wait']} วินาที";
                    break;
                }

                $phpBinary = PHP_BINARY;
                $script = escapeshellarg(__FILE__);

                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    pclose(popen("start /B \"\" \"$phpBinary\" $script runBackupInBackground", "r"));
                } else {
                    exec("nohup $phpBinary $script runBackupInBackground > /dev/null 2>&1 &");
                }

                logProgress(5, "กำลังเริ่มต้น Backup...", 'running');
                logMessage("Backup เริ่มต้นโดย IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

                $res['message'] = "🚀 Backup กำลังทำงานใน Background...";
                break;

            case 'progress':
                if (file_exists($progressFile)) {
                    $data = json_decode(file_get_contents($progressFile), true);
                    if ($data) {
                        $res = array_merge($res, $data);
                    } else {
                        $res['percent'] = 0;
                        $res['message'] = 'ข้อมูล progress ผิดพลาด';
                        $res['status'] = 'error';
                    }
                } else {
                    $res['percent'] = 0;
                    $res['message'] = 'ยังไม่ได้เริ่ม';
                    $res['status'] = 'idle';
                }
                break;

            case 'lastRun':
                if (file_exists($logFile)) {
                    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if (!empty($lines)) {
                        $lastLine = end($lines);
                        preg_match('/\[(.*?)\]/', $lastLine, $matches);
                        $time = $matches[1] ?? 'N/A';
                        $status = strpos($lastLine, '✅') !== false ? '✅ สำเร็จ' : '❌ ล้มเหลว';
                        $res['time'] = $time;
                        $res['status'] = $status;
                    } else {
                        $res['time'] = 'N/A';
                        $res['status'] = '❌ ยังไม่เคยรัน';
                    }
                } else {
                    $res['time'] = 'N/A';
                    $res['status'] = '❌ ยังไม่เคยรัน';
                }
                break;

            case 'listFiles':
                $files = glob($backupDir . 'backup_*.zip');
                rsort($files); // Newest first
                $fileList = [];

                foreach($files as $f) {
                    if (validateFileName(basename($f))) {
                        $fileList[] = [
                            'name' => basename($f),
                            'size' => round(filesize($f) / 1048576, 2),
                            'date' => date('Y-m-d H:i:s', filemtime($f))
                        ];
                    }
                }

                $res['files'] = $fileList;
                break;

            case 'download':
                $files = glob($backupDir . 'backup_*.zip');
                if (empty($files)) {
                    http_response_code(404);
                    $res['success'] = false;
                    $res['message'] = 'ไม่พบไฟล์ Backup';
                } else {
                    $latestFile = array_reduce($files, function($a, $b) {
                        return filemtime($a) > filemtime($b) ? $a : $b;
                    });
                    $res['downloadUrl'] = '?action=downloadFile&file=' . urlencode(basename($latestFile));
                    $res['fileName'] = basename($latestFile);
                }
                break;

            case 'delete':
                if (!isset($_GET['file'])) {
                    throw new Exception('ไม่ระบุชื่อไฟล์');
                }

                $fileName = basename($_GET['file']);
                if (!validateFileName($fileName)) {
                    throw new Exception('ชื่อไฟล์ไม่ถูกต้อง');
                }

                $filePath = $backupDir . $fileName;

                if (!file_exists($filePath)) {
                    throw new Exception('ไม่พบไฟล์');
                }

                if (strpos(realpath($filePath), realpath($backupDir)) !== 0) {
                    throw new Exception('Path traversal detected');
                }

                if (@unlink($filePath)) {
                    logMessage("🗑️ ลบไฟล์: $fileName โดย IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    $res['message'] = "ลบไฟล์ $fileName สำเร็จ";
                } else {
                    throw new Exception('ไม่สามารถลบไฟล์ได้');
                }
                break;

            case 'logout':
                session_destroy();
                $res['message'] = 'ออกจากระบบสำเร็จ';
                break;

            default:
                http_response_code(400);
                $res['success'] = false;
                $res['message'] = 'Unknown action';
        }
    } catch (Exception $e) {
        http_response_code(500);
        $res['success'] = false;
        $res['message'] = 'Error: ' . $e->getMessage();
        logError($e->getMessage());
    }

    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// Download Handler
if (isset($_GET['action']) && $_GET['action'] == 'downloadFile') {
    if (!isset($_SESSION['backup_authenticated'])) {
        http_response_code(401);
        exit('Unauthorized');
    }

    if (!isset($_GET['file'])) {
        http_response_code(400);
        exit('No file specified');
    }

    $fileName = basename($_GET['file']);

    if (!validateFileName($fileName)) {
        http_response_code(400);
        exit('Invalid filename');
    }

    $file = $backupDir . $fileName;

    if (!file_exists($file)) {
        http_response_code(404);
        exit('File not found');
    }

    if (strpos(realpath($file), realpath($backupDir)) !== 0) {
        http_response_code(403);
        exit('Access denied');
    }

    logMessage("📥 ดาวน์โหลด: $fileName โดย IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    readfile($file);
    exit;
}

// CLI Background Execution
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] == 'runBackupInBackground') {
    runBackupImproved();
    exit;
}

// ========================================
// LOGIN HANDLER
// ========================================
if (!isset($_SESSION['backup_authenticated'])) {
    if (isset($_POST['password'])) {
        if (!verifyCsrfToken()) {
            $loginError = '❌ CSRF token ไม่ถูกต้อง';
        } elseif ($_POST['password'] === BACKUP_PASSWORD) {
            $_SESSION['backup_authenticated'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            logMessage("✅ Login สำเร็จจาก IP: " . $_SESSION['login_ip']);

            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = '❌ รหัสผ่านไม่ถูกต้อง';
            logMessage("⚠️ Login ล้มเหลวจาก IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            sleep(2); // Prevent brute force
        }
    }

    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Login - Auto Backup</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
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
    .login-box {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        max-width: 400px;
        width: 100%;
    }
    .login-box h2 {
        text-align: center;
        color: #667eea;
        margin-bottom: 10px;
    }
    .login-box p {
        text-align: center;
        color: #666;
        margin-bottom: 30px;
        font-size: 14px;
    }
    input[type="password"] {
        width: 100%;
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-family: 'Prompt', sans-serif;
        font-size: 16px;
        margin-bottom: 15px;
        transition: border 0.3s;
    }
    input[type="password"]:focus {
        outline: none;
        border-color: #667eea;
    }
    button {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 8px;
        font-family: 'Prompt', sans-serif;
        font-size: 16px;
        cursor: pointer;
        transition: transform 0.2s;
    }
    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .error {
        background: #ffebee;
        color: #c62828;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
        font-size: 14px;
    }
    .version {
        text-align: center;
        margin-top: 20px;
        color: #999;
        font-size: 12px;
    }
    </style>
    </head>
    <body>
    <div class="login-box">
        <h2>🔐 Auto Backup</h2>
        <p>ระบบสำรองข้อมูลอัตโนมัติ<br><strong>Security Enhanced v2.0</strong></p>

        <?php if (isset($loginError)): ?>
            <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="password" name="password" placeholder="รหัสผ่าน" required autofocus>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>

        <div class="version">
            ⚡ Rate Limit: <?php echo MIN_BACKUP_INTERVAL; ?>s |
            🗑️ Auto Clean: <?php echo MAX_BACKUP_AGE_DAYS; ?> วัน
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Session timeout check (24 hours)
if (isset($_SESSION['login_time']) && time() - $_SESSION['login_time'] > 86400) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ========================================
// DASHBOARD
// ========================================
$backupCheck = checkBackupFolder();
$files = glob($backupDir . 'backup_*.zip');
rsort($files);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🔐 Auto Backup Dashboard v2.0</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Prompt', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 1200px;
    margin: 0 auto;
}
.card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.header {
    background: linear-gradient(135deg, #0288d1, #26c6da);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}
.header h1 {
    margin-bottom: 5px;
    font-size: 28px;
}
.header p {
    opacity: 0.9;
    font-size: 14px;
}
.header .session-info {
    margin-top: 10px;
    font-size: 12px;
    opacity: 0.8;
}
button {
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #0288d1, #26c6da);
    color: #fff;
    cursor: pointer;
    margin: 5px;
    font-family: 'Prompt', sans-serif;
    font-size: 14px;
    transition: transform 0.2s;
}
button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
button:active {
    transform: translateY(0);
}
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-danger {
    background: linear-gradient(135deg, #f44336, #e91e63);
}
.btn-success {
    background: linear-gradient(135deg, #4caf50, #8bc34a);
}
.btn-small {
    padding: 6px 12px;
    font-size: 12px;
}
.progress-container {
    background: #f0f0f0;
    border-radius: 10px;
    height: 30px;
    margin-top: 10px;
    overflow: hidden;
    position: relative;
}
.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0288d1, #26c6da);
    border-radius: 10px;
    width: 0%;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}
.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
}
.status-success { background: #4caf50; animation: pulse 2s infinite; }
.status-error { background: #f44336; animation: pulse 2s infinite; }
.status-idle { background: #9e9e9e; }
.status-running { background: #ff9800; animation: pulse 1s infinite; }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}
.stat-box {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}
.stat-box h3 {
    font-size: 32px;
    margin-bottom: 5px;
}
.stat-box p {
    font-size: 14px;
    opacity: 0.9;
}
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}
.alert-success {
    background: #e8f5e9;
    color: #2e7d32;
}
.alert-error {
    background: #ffebee;
    color: #c62828;
}
.alert-warning {
    background: #fff3e0;
    color: #e65100;
}
.file-list {
    max-height: 400px;
    overflow-y: auto;
}
.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
}
.file-item:hover {
    background: #f5f5f5;
}
.file-item:last-child {
    border-bottom: none;
}
.file-info {
    flex: 1;
}
.file-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 3px;
}
.file-meta {
    font-size: 12px;
    color: #666;
}
.file-actions {
    display: flex;
    gap: 5px;
}
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 5px;
}
.badge-new {
    background: #4caf50;
    color: white;
}
#backupChart {
    max-height: 250px;
}
.security-badge {
    display: inline-block;
    background: #4caf50;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 10px;
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔐 Auto Backup Dashboard</h1>
        <p>ระบบสำรองข้อมูลอัตโนมัติ <span class="security-badge">🛡️ Security Enhanced v2.0</span></p>
        <div class="session-info">
            👤 เข้าสู่ระบบจาก: <?php echo htmlspecialchars($_SESSION['login_ip'] ?? 'N/A'); ?> |
            ⏰ Login: <?php echo isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'N/A'; ?>
        </div>
    </div>

    <?php if (!$backupCheck['status']): ?>
        <div class="alert alert-error">
            <?php echo $backupCheck['message']; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <?php echo $backupCheck['message']; ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-box">
            <h3><?php echo count($files); ?></h3>
            <p>📦 Backup Files</p>
        </div>
        <div class="stat-box">
            <h3><?php
                $totalSize = 0;
                foreach($files as $f) $totalSize += filesize($f);
                echo round($totalSize / 1048576, 1);
            ?> MB</h3>
            <p>💾 Total Size</p>
        </div>
        <div class="stat-box">
            <h3><?php echo MAX_BACKUP_AGE_DAYS; ?></h3>
            <p>🗑️ วันก่อนลบอัตโนมัติ</p>
        </div>
        <div class="stat-box">
            <h3><?php echo MIN_BACKUP_INTERVAL; ?>s</h3>
            <p>⏱️ Rate Limit</p>
        </div>
    </div>

    <div class="card">
        <h3>⚡ การดำเนินการ</h3>
        <div style="margin-top: 15px;">
            <button id="runNowBtn">🚀 Backup ทันที</button>
            <button id="downloadLatestBtn">📥 ดาวน์โหลดล่าสุด</button>
            <button id="refreshBtn" class="btn-success">🔄 รีเฟรชข้อมูล</button>
            <button class="btn-danger" id="logoutBtn">🚪 ออกจากระบบ</button>
        </div>
    </div>

    <div class="card">
        <h3>📈 ความคืบหน้า</h3>
        <div id="progressStatus" style="margin: 10px 0;">
            <span class="status-indicator status-idle"></span>
            <span>ยังไม่ได้เริ่ม Backup</span>
        </div>
        <div class="progress-container">
            <div id="progressBar" class="progress-bar">0%</div>
        </div>
    </div>

    <div class="card">
        <h3>⏱️ รอบล่าสุด</h3>
        <div id="lastRunStatus" style="padding: 10px 0;">กำลังโหลด...</div>
    </div>

    <div class="card">
        <h3>📁 รายการไฟล์ Backup (<?php echo count($files); ?> ไฟล์)</h3>
        <div id="fileList" class="file-list" style="margin-top: 15px;">
            กำลังโหลด...
        </div>
    </div>

    <div class="card">
        <h3>📊 สถิติ Backup ต่อเดือน</h3>
        <canvas id="backupChart"></canvas>
    </div>

    <div class="card">
        <h3>🛡️ การปรับปรุงความปลอดภัย</h3>
        <ul style="padding-left: 20px; line-height: 1.8;">
            <li>✅ CSRF Protection</li>
            <li>✅ Session Security (HttpOnly, SameSite)</li>
            <li>✅ Rate Limiting (<?php echo MIN_BACKUP_INTERVAL; ?> วินาที)</li>
            <li>✅ Path Traversal Protection</li>
            <li>✅ Filename Validation</li>
            <li>✅ Disk Space Check</li>
            <li>✅ Error Logging</li>
            <li>✅ Brute Force Protection</li>
            <li>✅ Session Timeout (24 ชั่วโมง)</li>
            <li>✅ IP Logging</li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart
const ctx = document.getElementById('backupChart').getContext('2d');
const backupChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'จำนวน Backup',
            data: [<?php
                $monthly = array_fill(0, 12, 0);
                foreach($files as $f) {
                    $m = (int)date('n', filemtime($f)) - 1;
                    if ($m >= 0 && $m < 12) $monthly[$m]++;
                }
                echo implode(',', $monthly);
            ?>],
            backgroundColor: 'rgba(2, 136, 209, 0.6)',
            borderColor: 'rgba(2, 136, 209, 1)',
            borderWidth: 2,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

// Global state
let isBackupRunning = false;

// Functions
async function refreshLastRun() {
    try {
        const res = await fetch('?action=lastRun&_=' + Date.now());

        if (!res.ok) {
            if (res.status === 401) {
                window.location.reload();
                return;
            }
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();
        let color = data.status.includes('✅') ? 'green' : 'red';
        document.getElementById('lastRunStatus').innerHTML =
            `<strong>${data.time}</strong> - <span style="color:${color}">${data.status}</span>`;
    } catch(e) {
        console.error('Last run error:', e);
        document.getElementById('lastRunStatus').innerHTML = "❌ ไม่สามารถดึงข้อมูลได้";
    }
}

async function fetchProgress() {
    try {
        const res = await fetch('?action=progress&_=' + Date.now());

        if (!res.ok) {
            if (res.status === 401) {
                window.location.reload();
                return;
            }
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();

        const statusSpan = document.querySelector('#progressStatus span:last-child');
        if (statusSpan) {
            statusSpan.textContent = data.message || 'ไม่ทราบสถานะ';
        }

        const indicator = document.querySelector('.status-indicator');
        if (indicator) {
            let statusClass = 'status-idle';
            if (data.status === 'success') statusClass = 'status-success';
            else if (data.status === 'error') statusClass = 'status-error';
            else if (data.status === 'running') statusClass = 'status-running';

            indicator.className = 'status-indicator ' + statusClass;
        }

        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            const percent = data.percent || 0;
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }

        const runBtn = document.getElementById('runNowBtn');
        if (data.status === 'running' && data.percent < 100) {
            isBackupRunning = true;
            if (runBtn) runBtn.disabled = true;
        } else {
            isBackupRunning = false;
            if (runBtn) runBtn.disabled = false;
        }

        if (data.percent === 100 && data.status === 'success') {
            setTimeout(() => {
                refreshLastRun();
                loadFileList();
            }, 2000);
        }
    } catch(e) {
        console.error('Progress fetch error:', e);
    }
}

async function loadFileList() {
    try {
        const res = await fetch('?action=listFiles&_=' + Date.now());

        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();
        const fileListEl = document.getElementById('fileList');

        if (!data.files || data.files.length === 0) {
            fileListEl.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">ไม่พบไฟล์ Backup</div>';
            return;
        }

        let html = '';
        const now = Date.now();

        data.files.forEach((file, index) => {
            const fileDate = new Date(file.date);
            const isNew = (now - fileDate.getTime()) < 3600000; // 1 hour

            html += `
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-name">
                            📦 ${file.name}
                            ${isNew ? '<span class="badge badge-new">NEW</span>' : ''}
                        </div>
                        <div class="file-meta">
                            💾 ${file.size} MB | 📅 ${file.date}
                        </div>
                    </div>
                    <div class="file-actions">
                        <button class="btn-success btn-small" onclick="downloadFile('${file.name}')">
                            📥 ดาวน์โหลด
                        </button>
                        ${index > 0 ? `<button class="btn-danger btn-small" onclick="deleteFile('${file.name}')">🗑️ ลบ</button>` : ''}
                    </div>
                </div>
            `;
        });

        fileListEl.innerHTML = html;

    } catch(e) {
        console.error('File list error:', e);
        document.getElementById('fileList').innerHTML = '<div style="text-align:center;padding:20px;color:#f44336;">❌ ไม่สามารถโหลดรายการไฟล์ได้</div>';
    }
}

function downloadFile(fileName) {
    window.location.href = '?action=downloadFile&file=' + encodeURIComponent(fileName);
}

async function deleteFile(fileName) {
    if (!confirm(`ต้องการลบไฟล์ "${fileName}" ใช่หรือไม่?`)) return;

    try {
        const res = await fetch('?action=delete&file=' + encodeURIComponent(fileName));
        const data = await res.json();

        if (data.success) {
            alert('✅ ' + data.message);
            loadFileList();
            window.location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    } catch(e) {
        alert('❌ เกิดข้อผิดพลาด: ' + e.message);
    }
}

// Event Listeners
document.getElementById('runNowBtn').addEventListener('click', async function() {
    if (isBackupRunning) {
        alert('⚠️ Backup กำลังทำงานอยู่ กรุณารอสักครู่');
        return;
    }

    if (!confirm('เริ่มต้น Backup ทันทีใช่หรือไม่?')) return;

    this.disabled = true;

    try {
        const res = await fetch('?action=run&_=' + Date.now());

        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();

        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ ' + data.message);
            this.disabled = false;
        }
    } catch(e) {
        alert('❌ เกิดข้อผิดพลาด: ' + e.message);
        this.disabled = false;
    }
});

document.getElementById('downloadLatestBtn').addEventListener('click', async function() {
    try {
        const res = await fetch('?action=download&_=' + Date.now());

        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();

        if (data.success && data.downloadUrl) {
            window.location.href = data.downloadUrl;
        } else {
            alert('❌ ' + (data.message || 'ไม่พบไฟล์ Backup'));
        }
    } catch(e) {
        alert('❌ เกิดข้อผิดพลาด: ' + e.message);
    }
});

document.getElementById('refreshBtn').addEventListener('click', function() {
    window.location.reload();
});

document.getElementById('logoutBtn').addEventListener('click', async function() {
    if (!confirm('ออกจากระบบใช่หรือไม่?')) return;

    try {
        await fetch('?action=logout');
        window.location.href = '?';
    } catch(e) {
        window.location.href = '?';
    }
});

// Auto-refresh
refreshLastRun();
loadFileList();
setInterval(refreshLastRun, 15000);
setInterval(fetchProgress, 3000);

// Initial progress check
fetchProgress();
</script>
</body>
</html>
