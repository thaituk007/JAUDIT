<?php
// import_txt_to_mysql.php
set_time_limit(0);
date_default_timezone_set('Asia/Bangkok');
session_start();

$config = include 'config.php';

// ฟังก์ชันแปลงวันที่ YYYYMMDD เป็น YYYY-MM-DD
function format_date($str) {
    if (!$str || strlen($str) < 8) return null;
    return substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2);
}

header('Content-Type: application/json; charset=utf-8');

$uploadDir = __DIR__ . '/uploads/';

// สร้างโฟลเดอร์ uploads ถ้าไม่มี และตั้ง permission
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(array('status'=>'error', 'message'=>'ไม่สามารถสร้างโฟลเดอร์ uploads ได้'));
        exit;
    }
    @chmod($uploadDir, 0777);
}

// STEP 1: รับไฟล์อัปโหลด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['txtfile'])) {
    $tmpFile = $_FILES['txtfile']['tmp_name'];
    $dest = $uploadDir . basename($_FILES['txtfile']['name']);
    if (!move_uploaded_file($tmpFile, $dest)) {
        echo json_encode(array('status'=>'error', 'message'=>'อัปโหลดไฟล์ล้มเหลว'));
        exit;
    }

    $lines = file($dest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        echo json_encode(array('status'=>'error', 'message'=>'ไฟล์ว่างหรืออ่านไฟล์ไม่ได้'));
        unlink($dest);
        exit;
    }

    array_shift($lines); // ลบ header

    $_SESSION['import_data'] = $lines;
    $_SESSION['total'] = count($lines);
    $_SESSION['processed'] = 0;
    $_SESSION['file'] = $dest;

    echo json_encode(array('status' => 'ok', 'total' => $_SESSION['total']));
    exit;
}

// STEP 2: ประมวลผลนำเข้าข้อมูลทีละ batch
if (isset($_GET['process']) && $_GET['process'] == 1) {
    if (empty($_SESSION['import_data'])) {
        echo json_encode(array('status'=>'error', 'message'=>'ไม่มีข้อมูลให้ประมวลผล'));
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host=".$config['db_host'].";port=".$config['db_port'].";dbname=".$config['db_name'].";charset=utf8",
            $config['db_user'],
            $config['db_pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode(array('status' => 'error', 'message' => 'เชื่อมต่อฐานข้อมูลล้มเหลว'));
        exit;
    }

    $batchSize = 100;
    $data = &$_SESSION['import_data'];
    $processed = &$_SESSION['processed'];
    $total = $_SESSION['total'];

    $sql = "REPLACE INTO pcperson (
        hospcode, cid, pid, hid, prename, name, lname, hn, sex, birth,
        mstatus, occupation_old, occupation_new, race, nation, religion, education, fstatus,
        father, mother, couple, vstatus, movein, discharge, ddischarge, abogroup, rhgroup,
        labor, passport, typearea, d_update, telephone, mobile
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,
        ?,?,?,?,?,?,?,?,
        ?,?,?,?,?,?,?,?,?,?,
        ?,?,?,?
    )";
    $stmt = $pdo->prepare($sql);

    $count = 0;
    $start = $processed;
    $end = min($processed + $batchSize, $total);

    for ($i = $start; $i < $end; $i++) {
        $line = $data[$i];
        $fields = explode('|', trim($line));
        if (count($fields) < 33) continue;

        $params = array(
            $fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[5], $fields[6],
            $fields[7], $fields[8], format_date($fields[9]),
            $fields[10], $fields[11], $fields[12], $fields[13], $fields[14], $fields[15],
            $fields[16], $fields[17], $fields[18], $fields[19], $fields[20], $fields[21],
            format_date($fields[22]), $fields[23], format_date($fields[24]), $fields[25],
            $fields[26], $fields[27], $fields[28], $fields[29], $fields[30], $fields[31], $fields[32]
        );

        try {
            $stmt->execute($params);
            $count++;
        } catch (PDOException $e) {
            // ข้ามบรรทัดที่มี error
            continue;
        }
    }

    $processed += $count;
    $percent = round(($processed / $total) * 100);

    if ($processed >= $total) {
        unset($_SESSION['import_data'], $_SESSION['processed'], $_SESSION['total']);
        if (isset($_SESSION['file']) && file_exists($_SESSION['file'])) {
            unlink($_SESSION['file']);
            unset($_SESSION['file']);
        }
        echo json_encode(array('status' => 'done', 'message' => "✅ นำเข้าข้อมูลทั้งหมด $total รายการเรียบร้อย"));
        exit;
    } else {
        echo json_encode(array('status' => 'processing', 'percent' => $percent));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>นำเข้าไฟล์ .txt เข้า MySQL พร้อมแสดงสถานะ</title>
<link href="https://fonts.googleapis.com/css?family=Kanit&display=swap" rel="stylesheet" />
<style>
body {
    font-family: 'Kanit', sans-serif;
    background: #f0f4f8;
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.container {
    background: #ffffff;
    padding: 30px 40px;
    max-width: 520px;
    width: 100%;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    border-radius: 12px;
    box-sizing: border-box;
    text-align: center;
}
h2 {
    margin-top: 0;
    color: #0073e6;
}
form label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.1rem;
    color: #333;
}
input[type=file] {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
    font-size: 1rem;
    margin-bottom: 20px;
    cursor: pointer;
}
input[type=submit] {
    background: #0073e6;
    border: none;
    color: white;
    padding: 12px 25px;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    width: 100%;
}
input[type=submit]:hover {
    background: #005bb5;
}
.progress-container {
    background: #e1e7f0;
    border-radius: 12px;
    overflow: hidden;
    height: 28px;
    margin-top: 25px;
}
.progress-bar {
    height: 28px;
    width: 0;
    background: #0073e6;
    color: white;
    font-weight: 700;
    line-height: 28px;
    text-align: center;
    border-radius: 12px 0 0 12px;
    transition: width 0.4s ease;
    user-select: none;
}
.message {
    margin-top: 20px;
    font-weight: 600;
    color: #333;
    min-height: 24px;
    user-select: none;
}
</style>
</head>
<body>

<div class="container">
    <h2>นำเข้าไฟล์ .txt เข้า MySQL พร้อมแสดงสถานะ</h2>
    <form id="uploadForm" method="post" enctype="multipart/form-data">
        <label for="txtfile">เลือกไฟล์ .txt (pipe | คั่น):</label>
        <input type="file" name="txtfile" id="txtfile" accept=".txt" required />
        <input type="submit" value="เริ่มนำเข้า" />
    </form>

    <div class="progress-container" style="display:none;">
        <div id="progressBar" class="progress-bar">0%</div>
    </div>

    <div class="message" id="message"></div>
</div>

<script>
(function(){
    var form = document.getElementById('uploadForm');
    var progressBar = document.getElementById('progressBar');
    var progressContainer = document.querySelector('.progress-container');
    var message = document.getElementById('message');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        message.textContent = '';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressContainer.style.display = 'block';

        var formData = new FormData(form);

        fetch('', {method: 'POST', body: formData})
        .then(function(res){ return res.json(); })
        .then(function(data){
            if (data.status === 'ok') {
                processBatch();
            } else {
                message.textContent = '❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
            }
        }).catch(function(err){
            message.textContent = '❌ เกิดข้อผิดพลาด: ' + err.message;
        });
    });

    function processBatch(){
        fetch('?process=1')
        .then(function(res){ return res.json(); })
        .then(function(data){
            if(data.status === 'processing') {
                progressBar.style.width = data.percent + '%';
                progressBar.textContent = data.percent + '%';
                setTimeout(processBatch, 100);
            } else if (data.status === 'done') {
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                message.textContent = data.message;
            } else {
                message.textContent = '❌ เกิดข้อผิดพลาดในการประมวลผล';
            }
        }).catch(function(err){
            message.textContent = '❌ เกิดข้อผิดพลาด: ' + err.message;
        });
    }
})();
</script>

</body>
</html>
