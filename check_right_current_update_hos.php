<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$config = include('config.php');

// ฟังก์ชันตรวจสอบเลข CID 13 หลัก
function isValidCID($cid) {
    $cid = preg_replace('/\D/', '', $cid);
    if(strlen($cid) !== 13) return false;
    $sum = 0;
    for($i=0;$i<12;$i++){
        $sum += (int)$cid[$i] * (13-$i);
    }
    $checkDigit = (11 - ($sum % 11)) % 10;
    return $checkDigit == (int)$cid[12];
}

// เชื่อมต่อฐานข้อมูล
$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
if ($mysqli->connect_errno) die("Connect failed: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
    $uploadFile = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($uploadFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();
    array_shift($rows); // ข้ามหัวตาราง

    $sql = "INSERT IGNORE INTO person_checkright
    (cid,name,lname,gender,age,main_inscl,main_inscl_name,sub_inscl,sub_inscl_name,check_date,
    death_status,reg_hmain_op,reg_hmain_op_name,reg_hsub,reg_hsub_name,referral_hmain,referral_hmain_name,
    province_code,province_name,paid_model,remark)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) die("Prepare failed: " . $mysqli->error);

    $count = 0;
    $invalid = [];

    foreach ($rows as $row) {
        $cid = trim($row[0]);
        if (!isValidCID($cid)) {
            $invalid[] = $cid;
            continue;
        }

        $name = trim($row[1]);
        $lname = trim($row[2]);
        $gender = empty($row[3]) ? null : ($row[3]==='หญิง'?'F':'M');
        preg_match('/(\d+)/', $row[4], $matches);
        $age = isset($matches[1]) ? (int)$matches[1] : null;
        $main_inscl = trim($row[5]);
        $main_inscl_name = trim($row[6]);
        $sub_inscl = trim($row[7]);
        $sub_inscl_name = trim($row[8]);
        if (!empty($row[9])) {
            list($d,$m,$y) = explode('/',$row[9]);
            $y = $y-543;
            $check_date = sprintf("%04d-%02d-%02d",$y,$m,$d);
        } else $check_date = null;

        // แก้ death_status ตรวจสอบคำว่า "เสียชีวิต"
        $death_status = (trim($row[10]) === 'เสียชีวิต') ? 1 : 0;

        $reg_hmain_op = trim($row[11]);
        $reg_hmain_op_name = trim($row[12]);
        $reg_hsub = trim($row[13]);
        $reg_hsub_name = trim($row[14]);
        $referral_hmain = trim($row[15]);
        $referral_hmain_name = trim($row[16]);
        $province_code = trim($row[17]);
        $province_name = trim($row[18]);
        $paid_model = trim($row[19]);
        $remark = trim($row[20]);

        $stmt->bind_param(
            "ssssissssssssssssssss",
            $cid, $name, $lname, $gender, $age, $main_inscl, $main_inscl_name,
            $sub_inscl, $sub_inscl_name, $check_date, $death_status,
            $reg_hmain_op, $reg_hmain_op_name, $reg_hsub, $reg_hsub_name,
            $referral_hmain, $referral_hmain_name, $province_code,
            $province_name, $paid_model, $remark
        );
        $stmt->execute();
        $count++;
    }

    $stmt->close();
    $mysqli->close();

    $successMessage = htmlspecialchars("นำเข้า Excel สำเร็จ! จำนวน $count แถว");
    $warningMessage = count($invalid) > 0 ? htmlspecialchars("เลข CID ไม่ถูกต้อง: ".implode(', ',$invalid)) : '';

    echo <<<HTML
    <html>
    <head>
        <meta charset="UTF-8">
        <title>นำเข้า Excel เสร็จสิ้น</title>
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Prompt', sans-serif; background:#f0f2f5; margin:0; padding:0; }
            .alert { padding:18px 25px; margin:30px auto; max-width:700px; border-radius:12px; font-size:16px; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); transition:0.3s; }
            .success { background:#d4edda; color:#155724; border-left:6px solid #28a745;}
            .warning { background:#fff3cd; color:#856404; border-left:6px solid #ffc107;}
        </style>
        <script>
            setTimeout(function(){
                window.location.href = 'check_right_hos.php';
            }, 5000);
            setTimeout(function(){
                document.querySelectorAll('.alert').forEach(a => a.style.display='none');
            }, 5000);
        </script>
    </head>
    <body>
HTML;

    if($successMessage) echo "<div class='alert success'>{$successMessage}</div>";
    if($warningMessage) echo "<div class='alert warning'>{$warningMessage}</div>";

    echo "</body></html>";

} else {
    echo <<<HTML
    <html>
    <head>
        <meta charset="UTF-8">
        <title>นำเข้า Excel</title>
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Prompt', sans-serif; background:#f0f2f5; margin:0; padding:30px;}
            .container { max-width:600px; margin:auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.15); text-align:center;}
            h2 { color:#333; margin-bottom:30px; font-weight:500;}
            input[type=file] { width:100%; padding:14px; margin:15px 0 25px 0; border-radius:8px; border:1px solid #ccc; font-size:16px;}
            button { background:#4CAF50; color:#fff; border:none; padding:14px 30px; border-radius:8px; cursor:pointer; font-size:16px; transition:0.3s; }
            button:hover { background:#45a049; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>นำเข้าไฟล์ Excel</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                <button type="submit">อัปโหลดและนำเข้า</button>
            </form>
            <!-- ปุ่มกลับหน้าแรก -->
            <form action="index.php" method="get">
                <button type="submit" class="secondary">กลับหน้าแรก</button>
            </form>
            <!-- ปุ่มกลับหน้าแรก -->
            <form action="check_right_hos.php" method="get">
                <button type="submit">กลับส่งออก Excel สำหรับตรวจสอบแบบกลุ่ม SRM</button>
            </form>
        </div>
    </body>
    </html>
HTML;
}
?>
