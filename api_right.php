<?php
// ====================================================================================
// โหลด config
// ====================================================================================
$config = include('config.php');
$log_file = 'nhso_log.txt';
$base_url = 'https://srm.nhso.go.th/api/ucws/v1/right-search';

// ====================================================================================
// ฟังก์ชันช่วยเหลือ
// ====================================================================================
function write_log($msg){
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$date] $msg\n", FILE_APPEND);
}

function find_token_file_recursive() {
    $userprofile = getenv('USERPROFILE');
    if($userprofile && is_dir($userprofile)){
        try {
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($userprofile, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                if (!$file->isDir() && strtolower($file->getFilename()) === 'token.txt') {
                    return $file->getPathname();
                }
            }
        } catch (Exception $e){
            write_log("Token search error: " . $e->getMessage());
        }
    }
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'token.txt';
    if(file_exists($path)) return $path;
    return null;
}

function read_access_token($file_path) {
    if(!file_exists($file_path)) return '';
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($lines as $line){
        $line = trim($line);
        if(strpos($line, 'access-token=') === 0){
            return trim(substr($line, strlen('access-token=')));
        }
    }
    return '';
}

// แปลงวันเกิดไทย → YYYY-mm-dd
function thaiDateToYM($thaiDate){
    $thai_months = [
        "มกราคม"=>1,"กุมภาพันธ์"=>2,"มีนาคม"=>3,"เมษายน"=>4,
        "พฤษภาคม"=>5,"มิถุนายน"=>6,"กรกฎาคม"=>7,"สิงหาคม"=>8,
        "กันยายน"=>9,"ตุลาคม"=>10,"พฤศจิกายน"=>11,"ธันวาคม"=>12
    ];

    if(preg_match("/(\d{1,2})?\s*(\S+)\s*(\d{4})/u", $thaiDate, $m)){
        $day = isset($m[1]) && $m[1] !== '' ? (int)$m[1] : 1;
        $month = $thai_months[$m[2]] ?? 0;
        $year = ((int)$m[3]) - 543;

        if($month > 0){
            return sprintf("%04d-%02d-%02d", $year, $month, $day);
        }
    }
    return null;
}

// ====================================================================================
// ฟังก์ชันเรียก NHSO API ผ่าน cURL (Backend)
// ====================================================================================
function callNHSOAPI($pid, $access_token) {
    global $base_url;

    $url = $base_url . '?pid=' . urlencode($pid);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if($error){
        write_log("cURL Error for PID {$pid}: {$error}");
        return [
            'status' => 'error',
            'http_code' => 0,
            'error' => $error,
            'data' => null
        ];
    }

    if($http_code === 200){
        $data = json_decode($response, true);
        return [
            'status' => 'success',
            'http_code' => $http_code,
            'data' => $data,
            'error' => null
        ];
    } else if($http_code === 404){
        return [
            'status' => 'not_found',
            'http_code' => $http_code,
            'data' => null,
            'error' => 'ไม่พบข้อมูลในระบบ NHSO'
        ];
    } else if($http_code === 401 || $http_code === 403){
        write_log("Auth error HTTP {$http_code} for PID {$pid}: {$response}");
        return [
            'status' => 'error',
            'http_code' => $http_code,
            'data' => null,
            'error' => 'Token หมดอายุหรือไม่มีสิทธิ์เข้าถึง'
        ];
    } else if($http_code === 429){
        return [
            'status' => 'rate_limited',
            'http_code' => $http_code,
            'data' => null,
            'error' => 'ถูกจำกัดจำนวนการเรียก API (Rate Limited)'
        ];
    } else {
        write_log("HTTP {$http_code} for PID {$pid}: {$response}");
        return [
            'status' => 'error',
            'http_code' => $http_code,
            'data' => null,
            'error' => "HTTP Error {$http_code}"
        ];
    }
}

// ====================================================================================
// ฟังก์ชัน insert ลงตาราง personfunddetail
// ====================================================================================
function insertPersonFundDetail($mysqli, $pid, $apiResponse) {
    // ดึงข้อมูลล่วงหน้าจาก API response - ข้ามฟิลด์ที่ไม่มีค่า
    $tname = !empty($apiResponse['tname']) ? $apiResponse['tname'] : null;
    $fname = !empty($apiResponse['fname']) ? $apiResponse['fname'] : null;
    $lname = !empty($apiResponse['lname']) ? $apiResponse['lname'] : null;
    $nation = !empty($apiResponse['nation']['id']) ? $apiResponse['nation']['id'] : null;
    $birthDate = !empty($apiResponse['birthDate']) ? thaiDateToYM($apiResponse['birthDate']) : null;
    $sex = !empty($apiResponse['sex']['id']) ? $apiResponse['sex']['id'] : null;
    $deathDate = !empty($apiResponse['deathDate']) ? $apiResponse['deathDate'] : null;
    $checkDate = date('Y-m-d');

    // ตรวจสอบว่ามีวันตายหรือไม่
    if(!empty($deathDate)){
        // ถ้ามีวันตาย ให้บันทึกข้อมูลพื้นฐานเฉพาะ deathDate (ไม่มี funds)
        write_log("PID {$pid} has death date: {$deathDate} - Saving without funds");

        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO personfunddetail
            (pid, checkDate, tname, fname, lname, nation_id, birthDate, sex_id, deathDate,
             transDate, fundType, mainInscl_id, mainInscl_name, subInscl_id, subInscl_name,
             startDateTime, expireDateTime, paidModel, hospMainOp_hcode, hospMainOp_hname,
             hospSub_hcode, hospSub_hname, hospMain_hcode, hospMain_hname,
             purchaseProvince_id, purchaseProvince_name, relation, cardId)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        if (!$stmt) {
            write_log("Prepare failed for PID {$pid}: " . $mysqli->error);
            return false;
        }

        // บันทึก 1 record พร้อม deathDate (funds = null)
        $null_val = null;
        $stmt->bind_param(
            "ssssssssssssssssssssssssssss",
            $pid,
            $checkDate,
            $tname,
            $fname,
            $lname,
            $nation,
            $birthDate,
            $sex,
            $deathDate,
            $null_val, // transDate
            $null_val, // fundType
            $null_val, // mainInscl_id
            $null_val, // mainInscl_name
            $null_val, // subInscl_id
            $null_val, // subInscl_name
            $null_val, // startDateTime
            $null_val, // expireDateTime
            $null_val, // paidModel
            $null_val, // hospMainOp_hcode
            $null_val, // hospMainOp_hname
            $null_val, // hospSub_hcode
            $null_val, // hospSub_hname
            $null_val, // hospMain_hcode
            $null_val, // hospMain_hname
            $null_val, // purchaseProvince_id
            $null_val, // purchaseProvince_name
            $null_val, // relation
            $null_val  // cardId
        );

        if(!$stmt->execute()){
            write_log("Insert failed PID {$pid} with death date: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    // ถ้าไม่มีวันตาย ต้องมี funds
    if(!isset($apiResponse['funds']) || !is_array($apiResponse['funds']) || empty($apiResponse['funds'])){
        write_log("No funds data and no death date for PID: {$pid}");
        return true;
    }

    $stmt = $mysqli->prepare("
        INSERT IGNORE INTO personfunddetail
        (pid, checkDate, tname, fname, lname, nation_id, birthDate, sex_id, deathDate,
         transDate, fundType, mainInscl_id, mainInscl_name, subInscl_id, subInscl_name,
         startDateTime, expireDateTime, paidModel, hospMainOp_hcode, hospMainOp_hname,
         hospSub_hcode, hospSub_hname, hospMain_hcode, hospMain_hname,
         purchaseProvince_id, purchaseProvince_name, relation, cardId)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    if (!$stmt) {
        write_log("Prepare failed for PID {$pid}: " . $mysqli->error);
        return false;
    }

    $success = true;
    foreach($apiResponse['funds'] as $fund){
        // ดึงค่าจาก fund array - ใช้ empty() เพื่อข้ามฟิลด์ที่ว่าง
        $transDate = !empty($fund['transDate']) ? $fund['transDate'] : null;
        $fundType = !empty($fund['fundType']) ? $fund['fundType'] : null;
        $mainInscl_id = !empty($fund['mainInscl']['id']) ? $fund['mainInscl']['id'] : null;
        $mainInscl_name = !empty($fund['mainInscl']['name']) ? $fund['mainInscl']['name'] : null;
        $subInscl_id = !empty($fund['subInscl']['id']) ? $fund['subInscl']['id'] : null;
        $subInscl_name = !empty($fund['subInscl']['name']) ? $fund['subInscl']['name'] : null;
        $startDateTime = !empty($fund['startDateTime']) ? $fund['startDateTime'] : null;
        $expireDateTime = !empty($fund['expireDateTime']) ? $fund['expireDateTime'] : null;
        $paidModel = !empty($fund['paidModel']) ? $fund['paidModel'] : null;
        $hospMainOp_hcode = !empty($fund['hospMainOp']['hcode']) ? $fund['hospMainOp']['hcode'] : null;
        $hospMainOp_hname = !empty($fund['hospMainOp']['hname']) ? $fund['hospMainOp']['hname'] : null;
        $hospSub_hcode = !empty($fund['hospSub']['hcode']) ? $fund['hospSub']['hcode'] : null;
        $hospSub_hname = !empty($fund['hospSub']['hname']) ? $fund['hospSub']['hname'] : null;
        $hospMain_hcode = !empty($fund['hospMain']['hcode']) ? $fund['hospMain']['hcode'] : null;
        $hospMain_hname = !empty($fund['hospMain']['hname']) ? $fund['hospMain']['hname'] : null;
        $purchaseProvince_id = !empty($fund['purchaseProvince']['id']) ? $fund['purchaseProvince']['id'] : null;
        $purchaseProvince_name = !empty($fund['purchaseProvince']['name']) ? $fund['purchaseProvince']['name'] : null;
        $relation = !empty($fund['relation']) ? $fund['relation'] : null;
        $cardId = !empty($fund['cardId']) ? $fund['cardId'] : null;

        // Bind parameters
        $stmt->bind_param(
            "ssssssssssssssssssssssssssss",
            $pid,
            $checkDate,
            $tname,
            $fname,
            $lname,
            $nation,
            $birthDate,
            $sex,
            $deathDate,
            $transDate,
            $fundType,
            $mainInscl_id,
            $mainInscl_name,
            $subInscl_id,
            $subInscl_name,
            $startDateTime,
            $expireDateTime,
            $paidModel,
            $hospMainOp_hcode,
            $hospMainOp_hname,
            $hospSub_hcode,
            $hospSub_hname,
            $hospMain_hcode,
            $hospMain_hname,
            $purchaseProvince_id,
            $purchaseProvince_name,
            $relation,
            $cardId
        );

        if(!$stmt->execute()){
            write_log("Insert failed PID {$pid}: " . $stmt->error);
            $success = false;
        }
    }

    $stmt->close();
    return $success;
}

// ====================================================================================
// AJAX: Process single PID request
// ====================================================================================
if(isset($_POST['action']) && $_POST['action'] === 'check_pid'){
    $pid = $_POST['pid'] ?? '';
    $token = $_POST['token'] ?? '';

    if(empty($pid) || empty($token)){
        echo json_encode(['status'=>'error', 'error'=>'Missing PID or token', 'db_inserted'=>0]);
        exit;
    }

    // เรียก API
    $result = callNHSOAPI($pid, $token);

    // ถ้าสำเร็จ บันทึกลง DB
    $db_inserted = 0;
    $db_error = null;

    if($result['status'] === 'success' && $result['data']){
        $mysqli = new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name'],
            $config['db_port']
        );

        if(!$mysqli->connect_errno){
            $insert_success = insertPersonFundDetail($mysqli, $pid, $result['data']);

            // นับจำนวน records ที่ insert สำเร็จ
            if($insert_success && isset($result['data']['funds'])){
                $db_inserted = count($result['data']['funds']);
            }

            // ตรวจสอบว่า insert จริงหรือไม่
            $check_stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM personfunddetail WHERE pid = ? AND checkDate = ?");
            $check_date = date('Y-m-d');
            $check_stmt->bind_param("ss", $pid, $check_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $row = $check_result->fetch_assoc();
            $db_inserted = (int)$row['cnt'];
            $check_stmt->close();

            $mysqli->close();
        } else {
            $db_error = $mysqli->connect_error;
        }
    }

    $result['db_inserted'] = $db_inserted;
    $result['db_error'] = $db_error;

    echo json_encode($result);
    exit;
}

// ====================================================================================
// นับจำนวน PID ที่มีอยู่ใน personfunddetail
// ====================================================================================
function getExistingPIDCount($config) {
    $mysqli = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name'],
        $config['db_port']
    );

    if($mysqli->connect_errno) return 0;

    $result = $mysqli->query("SELECT COUNT(DISTINCT pid) as cnt FROM personfunddetail");
    $count = 0;
    if($result){
        $row = $result->fetch_assoc();
        $count = (int)$row['cnt'];
        $result->free();
    }
    $mysqli->close();
    return $count;
}

// ====================================================================================
// HANDLE token
// ====================================================================================
$access_token = '';
$token_found = false;

if(isset($_FILES['token_file']) && $_FILES['token_file']['error'] === UPLOAD_ERR_OK){
    $tmp_name = $_FILES['token_file']['tmp_name'];
    $access_token = read_access_token($tmp_name);
    $token_found = !empty($access_token);
} else {
    $token_file = find_token_file_recursive();
    if($token_file){
        $access_token = read_access_token($token_file);
        $token_found = !empty($access_token);
    }
}

if(!$token_found){
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo '<title>NHSO Token Required</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">';
    echo '<style>
    body { font-family:"Prompt", sans-serif; background:#f4f7fa; padding:40px; text-align:center;}
    .container { max-width:500px; margin:0 auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
    h2 { color:#e53e3e; margin-bottom:20px;}
    p { color:#666; margin-bottom:30px; line-height:1.6;}
    input[type="file"] { padding:10px; border:2px dashed #ddd; border-radius:8px; width:100%; margin-bottom:20px; cursor:pointer;}
    button { padding:12px 24px; background:#556ee6; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:16px; font-weight:500; transition:0.3s;}
    button:hover { background:#4458d4;}
    </style></head><body>';
    echo '<div class="container">';
    echo '<h2>❌ ไม่พบไฟล์ token.txt</h2>';
    echo '<p>โปรดเลือกไฟล์ <strong>token.txt</strong> ที่มี access token สำหรับเรียก API NHSO</p>';
    echo '<form method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="token_file" accept=".txt" required>';
    echo '<button type="submit">อัปโหลดและเริ่มตรวจสอบ</button>';
    echo '</form>';
    echo '</div></body></html>';
    exit;
}

// ====================================================================================
// HTML + CSS + JS
// ====================================================================================
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHSO Batch Checker</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Prompt", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .info {
            color: #666;
            margin-bottom: 25px;
            padding: 15px;
            background: #f0f4f8;
            border-radius: 8px;
            border-left: 4px solid #556ee6;
        }
        .progress-wrapper {
            margin-bottom: 25px;
        }
        #progress_container {
            width: 100%;
            background: #e0e7ef;
            border-radius: 10px;
            overflow: hidden;
            height: 30px;
            position: relative;
        }
        #progress_bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }
        #progress_text {
            font-weight: 600;
            display: block;
            margin-top: 10px;
            color: #333;
            font-size: 16px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-card.error {
            background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
        }
        .stat-card.database {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        .stat-card h3 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        .stat-card p {
            font-size: 14px;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
        }
        th {
            background-color: #556ee6;
            color: #fff;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) { background-color: #f8f9fc; }
        tr:nth-child(odd) { background-color: #ffffff; }
        tr:hover { background-color: #e8eaf6; }
        pre {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
            color: #333;
            border: 1px solid #e0e0e0;
            max-height: 300px;
            overflow-y: auto;
        }
        .status-success {
            color: #28a745;
            font-weight: 600;
            padding: 4px 10px;
            background: #d4edda;
            border-radius: 4px;
            display: inline-block;
        }
        .status-warning {
            color: #ff9800;
            font-weight: 600;
            padding: 4px 10px;
            background: #fff3cd;
            border-radius: 4px;
            display: inline-block;
        }
        .status-error {
            color: #e53e3e;
            font-weight: 600;
            padding: 4px 10px;
            background: #f8d7da;
            border-radius: 4px;
            display: inline-block;
        }
        .status-notfound {
            color: #6c757d;
            font-weight: 600;
            padding: 4px 10px;
            background: #e9ecef;
            border-radius: 4px;
            display: inline-block;
        }
        .table-wrapper {
            max-height: 600px;
            overflow-y: auto;
            border-radius: 10px;
        }
        #completion_message {
            display: none;
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            border: 2px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #e53e3e;
        }
        .auth-error-banner {
            background: #ff6b6b;
            color: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255,107,107,0.3);
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .timer {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #556ee6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🏥 ระบบตรวจสอบสิทธิ NHSO จาก Single Register Management (SRM-API)</h2>

        <div id="auth_error_banner" style="display:none;" class="auth-error-banner">
            🚫 Token หมดอายุหรือไม่มีสิทธิ์เข้าถึง API - โปรดตรวจสอบ Token และลองใหม่อีกครั้ง
        </div>

        <div class="info">
            <strong>✓ Token:</strong> โหลดสำเร็จและพร้อมใช้งาน<br>
            <strong>📡 API:</strong> <?php echo htmlspecialchars($base_url); ?><br>
            <strong>🔒 Mode:</strong> เรียก API ผ่าน PHP Backend (ปลอดภัย)<br>
            <strong>💾 Database:</strong> มี <span style="color:#28a745;font-weight:600;"><?php echo number_format(getExistingPIDCount($config)); ?></span> PID ในตาราง personfunddetail<br>
            <strong>⏱️ เวลาที่ใช้:</strong> <span class="timer" id="elapsed_time">00:00:00</span>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3 id="stat_total">0</h3>
                <p>รายการทั้งหมด</p>
            </div>
            <div class="stat-card success">
                <h3 id="stat_success">0</h3>
                <p>เรียก API สำเร็จ</p>
            </div>
            <div class="stat-card error">
                <h3 id="stat_error">0</h3>
                <p>ผิดพลาด</p>
            </div>
            <div class="stat-card database">
                <h3 id="stat_db_inserted">0</h3>
                <p>บันทึกลงฐานข้อมูล</p>
            </div>
        </div>

        <div class="progress-wrapper">
            <div id="progress_container">
                <div id="progress_bar">0%</div>
            </div>
            <span id="progress_text">0 / 0</span>
        </div>

        <div id="completion_message">
            ✅ ตรวจสอบสิทธิและบันทึกลงฐานข้อมูลเสร็จสมบูรณ์!
        </div>

        <div class="table-wrapper">
            <table id="result_table">
                <thead>
                    <tr>
                        <th>PID</th>
                        <th>Fund</th>
                        <th>HTTP</th>
                        <th>Status</th>
                        <th>DB Saved</th>
                        <th>Detail / Response</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

<?php
// ====================================================================================
// ดึง PID จากฐานข้อมูล
// ====================================================================================
$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);

if ($mysqli->connect_errno) {
    die("❌ MySQL Connection Error: " . htmlspecialchars($mysqli->connect_error));
}

$pids = [];
$limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 2000)) : 2000;

// ลองใช้ SQL แบบง่ายก่อน ถ้า cid13Chk() ไม่มี
$sql = "SELECT idcard AS cid FROM person WHERE nation='99' AND LENGTH(idcard) = 13 AND idcard NOT IN (SELECT pid FROM personfunddetail) LIMIT ?";
$stmt = $mysqli->prepare($sql);

if(!$stmt){
    // ถ้า prepare ไม่ได้ ให้ลอง query ตรงๆ
    write_log("Prepare failed: " . $mysqli->error . " - Trying direct query");
    $sql_direct = "SELECT idcard AS cid FROM person WHERE nation='99' AND LENGTH(idcard) = 13 LIMIT " . (int)$limit;
    $result = $mysqli->query($sql_direct);

    if($result){
        while($row = $result->fetch_assoc()) {
            $pids[] = $row['cid'];
        }
        $result->free();
    } else {
        die("❌ SQL Query Error: " . htmlspecialchars($mysqli->error) .
            "<br><br><strong>กรุณาตรวจสอบ:</strong><br>" .
            "1. ตาราง 'person' มีอยู่จริงหรือไม่<br>" .
            "2. คอลัมน์ 'idcard' และ 'nation' มีอยู่หรือไม่<br>" .
            "3. ฟังก์ชัน cid13Chk() ถูก define หรือไม่");
    }
} else {
    $stmt->bind_param("i", $limit);

    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $pids[] = $row['cid'];
        }
        $result->free();
    } else {
        die("❌ SQL Execute Error: " . htmlspecialchars($stmt->error));
    }

    $stmt->close();
}

$mysqli->close();

// ถ้าไม่มี PID เลย ให้แจ้งเตือน
if(empty($pids)){
    echo '<div class="container" style="margin-top:50px;text-align:center;">';
    echo '<h2 style="color:#ff9800;">⚠️ ไม่พบข้อมูล PID</h2>';
    echo '<p>ไม่พบข้อมูลในตาราง person ที่ตรงตามเงื่อนไข</p>';
    echo '<p><strong>กรุณาตรวจสอบ:</strong></p>';
    echo '<ul style="text-align:left;max-width:500px;margin:20px auto;">';
    echo '<li>ตาราง <code>person</code> มีข้อมูลหรือไม่</li>';
    echo '<li>คอลัมน์ <code>idcard</code> และ <code>nation</code> ถูกต้องหรือไม่</li>';
    echo '<li>มีข้อมูลที่ nation=\'99\' หรือไม่</li>';
    echo '</ul></div>';
    exit;
}
?>

<script>
const pids = <?php echo json_encode($pids); ?>;
const access_token = <?php echo json_encode($access_token); ?>;

let totalChecked = 0;
let successCount = 0;
let errorCount = 0;
let dbInsertedTotal = 0;
let startTime = Date.now();
let timerInterval = null;
let hasAuthError = false;

document.getElementById("progress_text").innerText = "0 / " + pids.length;
document.getElementById("stat_total").innerText = pids.length;

// ฟังก์ชันอัปเดตเวลา
function updateTimer() {
    const elapsed = Date.now() - startTime;
    const hours = Math.floor(elapsed / 3600000);
    const minutes = Math.floor((elapsed % 3600000) / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);

    const timeStr = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    document.getElementById("elapsed_time").innerText = timeStr;
}

// เริ่มนับเวลา
timerInterval = setInterval(updateTimer, 1000);

// เรียก API ผ่าน PHP Backend
async function checkPID(pid){
    const formData = new FormData();
    formData.append('action', 'check_pid');
    formData.append('pid', pid);
    formData.append('token', access_token);

    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });

        if(!response.ok){
            throw new Error('HTTP ' + response.status);
        }

        const result = await response.json();
        return {pid, ...result};

    } catch(e) {
        return {
            pid,
            status: 'error',
            http_code: 0,
            error: e.message,
            data: null
        };
    }
}

async function runBatchPID(allPids){
    const tbody = document.querySelector("#result_table tbody");

    // ประมวลผลทีละ PID เพื่อป้องกัน rate limit
    for(let i = 0; i < allPids.length; i++){
        const pid = allPids[i];
        const result = await checkPID(pid);

        totalChecked++;
        const percent = Math.round(totalChecked / allPids.length * 100);
        document.getElementById("progress_bar").style.width = percent + "%";
        document.getElementById("progress_bar").innerText = percent + "%";
        document.getElementById("progress_text").innerText = totalChecked + " / " + allPids.length;

        // ตรวจสอบ Auth Error
        if(result.status === 'error' && (result.http_code === 401 || result.http_code === 403)){
            hasAuthError = true;
            document.getElementById("auth_error_banner").style.display = "block";
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // แสดงผลในตาราง
        if(result.status === 'success' && result.data){
            // ตรวจสอบว่ามีวันตายหรือไม่
            const hasDeathDate = result.data.deathDate && result.data.deathDate !== null;
            const hasFunds = result.data.funds && result.data.funds.length > 0;

            if(hasDeathDate && !hasFunds){
                // กรณีมีวันตาย ไม่มี funds
                successCount++;
                dbInsertedTotal += (result.db_inserted || 0);

                const tr = document.createElement("tr");
                const dbStatus = result.db_inserted > 0 ?
                    `<span class="status-success">✓ ${result.db_inserted} records</span>` :
                    `<span class="status-error">✗ ไม่สำเร็จ</span>`;

                tr.innerHTML = `
                    <td>${result.pid}</td>
                    <td>-</td>
                    <td>${result.http_code}</td>
                    <td><span class="status-warning">มีวันตาย</span></td>
                    <td>${dbStatus}</td>
                    <td style="color:#e53e3e;font-weight:600;">
                        ☠️ วันตาย: ${result.data.deathDate}<br>
                        <small style="color:#666;">บันทึกข้อมูลพื้นฐานโดยไม่มี funds</small>
                    </td>
                `;
                tbody.appendChild(tr);

            } else if(hasFunds){
                // กรณีมี funds ปกติ
                successCount++;
                dbInsertedTotal += (result.db_inserted || 0);

                result.data.funds.forEach((fund, idx) => {
                    const tr = document.createElement("tr");
                    const dbStatus = result.db_inserted > 0 ?
                        `<span class="status-success">✓ ${result.db_inserted} records</span>` :
                        `<span class="status-error">✗ ไม่สำเร็จ</span>`;

                    tr.innerHTML = `
                        <td>${result.pid}</td>
                        <td>${idx + 1}</td>
                        <td>${result.http_code}</td>
                        <td><span class="status-success">สำเร็จ</span></td>
                        <td>${dbStatus}</td>
                        <td><pre>${JSON.stringify(fund, null, 2)}</pre></td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                // มี response แต่ไม่มีทั้ง funds และ deathDate
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${result.pid}</td>
                    <td>-</td>
                    <td>${result.http_code}</td>
                    <td><span class="status-notfound">ไม่มีข้อมูล</span></td>
                    <td>-</td>
                    <td>API คืนค่ามาแต่ไม่มีข้อมูล funds และไม่มีวันตาย</td>
                `;
                tbody.appendChild(tr);
            }
        } else if(result.status === 'not_found') {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${result.pid}</td>
                <td>-</td>
                <td>${result.http_code}</td>
                <td><span class="status-notfound">ไม่พบข้อมูล</span></td>
                <td>-</td>
                <td>${result.error || "ไม่พบข้อมูลในระบบ NHSO"}</td>
            `;
            tbody.appendChild(tr);
        } else if(result.status === 'rate_limited') {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${result.pid}</td>
                <td>-</td>
                <td>${result.http_code}</td>
                <td><span class="status-warning">Rate Limited</span></td>
                <td>-</td>
                <td class="error-message">${result.error}<br><small>รอ 2 วินาทีแล้วลองใหม่...</small></td>
            `;
            tbody.appendChild(tr);
            // รอ 2 วินาที
            await new Promise(r => setTimeout(r, 2000));
            i--; // ลองใหม่
            totalChecked--; // ลด counter
            continue;
        } else {
            errorCount++;
            const tr = document.createElement("tr");
            const dbError = result.db_error ? `<br><small>DB Error: ${result.db_error}</small>` : '';
            const isAuthError = result.http_code === 401 || result.http_code === 403;
            const errorClass = isAuthError ? 'auth-error-banner' : 'error-message';

            tr.innerHTML = `
                <td>${result.pid}</td>
                <td>-</td>
                <td>${result.http_code}</td>
                <td><span class="status-error">ผิดพลาด</span></td>
                <td><span class="status-error">✗</span></td>
                <td><div class="${errorClass}">${result.error || "Unknown error"}${dbError}</div></td>
            `;
            tbody.appendChild(tr);
        }

        // อัปเดต stats
        document.getElementById("stat_success").innerText = successCount;
        document.getElementById("stat_error").innerText = errorCount;
        document.getElementById("stat_db_inserted").innerText = dbInsertedTotal;

        // หน่วงเวลา 500ms ระหว่าง request เพื่อป้องกัน rate limit
        if(i < allPids.length - 1){
            await new Promise(r => setTimeout(r, 500));
        }
    }

    // หยุดนับเวลา
    clearInterval(timerInterval);
    updateTimer(); // อัปเดตครั้งสุดท้าย

    // แสดงข้อความเสร็จสิ้น
    const completionMsg = document.getElementById("completion_message");
    completionMsg.style.display = "block";

    const elapsed = Date.now() - startTime;
    const minutes = Math.floor(elapsed / 60000);
    const seconds = Math.floor((elapsed % 60000) / 1000);
    const timeText = minutes > 0 ? `${minutes} นาที ${seconds} วินาที` : `${seconds} วินาที`;

    if(hasAuthError){
        completionMsg.style.background = "#ff6b6b";
        completionMsg.style.borderColor = "#ff6b6b";
        completionMsg.innerHTML = `
            ⚠️ ตรวจสอบเสร็จสิ้น แต่พบปัญหา Token!<br>
            <small style="font-size: 16px; margin-top: 10px; display: block;">
            เวลาที่ใช้: <strong>${timeText}</strong> |
            บันทึกลงฐานข้อมูล <strong>${dbInsertedTotal}</strong> records จาก <strong>${successCount}</strong> รายการที่สำเร็จ<br>
            พบ Token Error <strong>${errorCount}</strong> รายการ - กรุณาตรวจสอบ Token
            </small>
        `;
    } else {
        completionMsg.innerHTML = `
            ✅ ตรวจสอบสิทธิเสร็จสมบูรณ์!<br>
            <small style="font-size: 16px; margin-top: 10px; display: block;">
            เวลาที่ใช้: <strong>${timeText}</strong> |
            บันทึกลงฐานข้อมูล <strong>${dbInsertedTotal}</strong> records จาก <strong>${successCount}</strong> รายการที่สำเร็จ
            </small>
        `;
    }
}

runBatchPID(pids);
</script>

</body>
</html>
