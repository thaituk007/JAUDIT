<?php
// ต้องติดตั้ง PHPSpreadsheet ก่อน: composer require phpoffice/phpspreadsheet
require 'vendor/autoload.php'; // โหลดไลบรารีที่จำเป็น
$config = require 'config.php'; // โหลดการตั้งค่าฐานข้อมูล (สมมติว่ามีไฟล์ config.php)

// ---------------------------------------------------------------------
// 1. การสร้างการเชื่อมต่อ PDO
// ---------------------------------------------------------------------
$db_host = $config['db_host'];
$db_port = $config['db_port'];
$db_name = $config['db_name'];
$db_user = $config['db_user'];
$db_pass = $config['db_pass'];
$charset = 'utf8';

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$charset";
$options = [
    // เปิดโหมด ERRMODE_EXCEPTION ชั่วคราวเพื่อให้ prepare() ที่ล้มเหลว throw Exception
    // ซึ่งช่วยให้เราดักจับข้อผิดพลาดของ SQL Syntax ได้ง่ายขึ้น
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
     die("❌ เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

// ---------------------------------------------------------------------

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$upload_message = "";

if (isset($_POST['importSubmit']) && isset($_FILES['file']['name'])) {

    $uploadedFileName = $_FILES['file']['name']; // เก็บชื่อไฟล์ที่อัปโหลด
    $allowed_file_types = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
    $file_type = mime_content_type($_FILES['file']['tmp_name']);

    if (!in_array($file_type, $allowed_file_types)) {
        $upload_message = "❌ กรุณาอัพโหลดไฟล์ Excel (.xlsx, .xls) หรือ CSV เท่านั้น";
    } else {

        $inputFileName = $_FILES['file']['tmp_name'];

        try {
            // 2. โหลดไฟล์ Excel ด้วย PHPSpreadsheet
            $spreadsheet = IOFactory::load($inputFileName);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();

            // 3. เตรียมคำสั่ง SQL (เพิ่มคอลัมน์ file_source)
            $sql = "INSERT IGNORE INTO `eclaim_hsub_data` (
                `rep_no`, `seq_no`, `tran_id`, `hn`, `an`, `pid`, `fullname`,
                `patient_type`, `admit_date`, `discharge_date`, `reimburse_net`,
                `reimburse_from`, `error_code`, `main_fund`, `sub_fund`,
                `service_type`, `referral`, `entitlement`, `right_used`,
                `ref_hospital`, `total_claim`, `file_source`
            ) VALUES (
                :rep_no, :seq_no, :tran_id, :hn, :an, :pid, :fullname,
                :patient_type, :admit_date, :discharge_date, :reimburse_net,
                :reimburse_from, :error_code, :main_fund, :sub_fund,
                :service_type, :referral, :entitlement, :right_used,
                :ref_hospital, :total_claim, :file_source
            )";

            // การเรียก prepare() จะอยู่ภายใน block try
            $stmt = $pdo->prepare($sql);

            $pdo->beginTransaction();
            $imported_count = 0;
            $skipped_count = 0;

            // 4. วนลูปอ่านข้อมูลใน Excel
            $startRow = 10;

            for ($row = $startRow; $row <= $highestRow; ++$row) {

                // อ่านค่าจากคอลัมน์ REP (A), ลำดับที่ (B) และ TRAN_ID (C)
                $rep_no_value = trim($worksheet->getCell('A' . $row)->getFormattedValue());
                $seq_no_value = trim($worksheet->getCell('B' . $row)->getFormattedValue());
                $tran_id_value = trim($worksheet->getCell('C' . $row)->getFormattedValue());

                // เงื่อนไขหยุด Loop:
                if (empty($rep_no_value) && empty($seq_no_value)) {
                    break;
                }

                // หากพบข้อความ "ข้อมูลอุทธรณ์" ในคอลัมน์ A ให้ข้ามแถวหัวข้อนี้ไป
                if (mb_strpos($rep_no_value, 'ข้อมูลอุทธรณ์', 0, 'UTF-8') !== false) {
                    continue;
                }

                // หากเป็นแถวข้อมูล แต่ TRAN_ID ว่าง ให้ข้ามแถวนี้
                if (!empty($rep_no_value) && empty($tran_id_value)) {
                    continue;
                }

                // ดึงข้อมูลแต่ละคอลัมน์
                $data = [];
                $data['rep_no']        = (string)$rep_no_value;
                $data['seq_no']        = (string)$seq_no_value;
                $data['tran_id']       = (string)$tran_id_value;
                $data['hn']            = (string)$worksheet->getCell('D' . $row)->getFormattedValue();
                $data['an']            = (string)$worksheet->getCell('E' . $row)->getFormattedValue();
                $data['pid']           = (string)$worksheet->getCell('F' . $row)->getFormattedValue();
                $data['fullname']      = $worksheet->getCell('G' . $row)->getValue();
                $data['patient_type']  = $worksheet->getCell('H' . $row)->getValue();

                // แปลงวันที่ Excel
                $admit_cell = $worksheet->getCell('I' . $row);
                $data['admit_date']    = Date::isDateTime($admit_cell) ? Date::excelToDateTimeObject($admit_cell->getValue())->format('Y-m-d') : NULL;

                $discharge_cell = $worksheet->getCell('J' . $row);
                $data['discharge_date'] = Date::isDateTime($discharge_cell) ? Date::excelToDateTimeObject($discharge_cell->getValue())->format('Y-m-d') : NULL;

                // แปลงตัวเลข (DECIMAL)
                $data['reimburse_net'] = (float)$worksheet->getCell('K' . $row)->getValue();

                $data['reimburse_from'] = $worksheet->getCell('L' . $row)->getValue();
                $data['error_code']    = $worksheet->getCell('M' . $row)->getValue();
                $data['main_fund']     = $worksheet->getCell('N' . $row)->getValue();
                $data['sub_fund']      = $worksheet->getCell('O' . $row)->getValue();
                $data['service_type']  = $worksheet->getCell('P' . $row)->getValue();
                $data['referral']      = $worksheet->getCell('Q' . $row)->getValue();
                $data['entitlement']   = $worksheet->getCell('R' . $row)->getValue();
                $data['right_used']    = $worksheet->getCell('S' . $row)->getValue();
                $data['ref_hospital']  = $worksheet->getCell('T' . $row)->getValue();
                $data['total_claim']   = (float)$worksheet->getCell('AQ' . $row)->getValue();

                // 5. ผูกค่าและ Execute Prepared Statement
                $stmt->execute([
                    ':rep_no' => $data['rep_no'],
                    ':seq_no' => $data['seq_no'],
                    ':tran_id' => $data['tran_id'],
                    ':hn' => $data['hn'],
                    ':an' => $data['an'],
                    ':pid' => $data['pid'],
                    ':fullname' => $data['fullname'],
                    ':patient_type' => $data['patient_type'],
                    ':admit_date' => $data['admit_date'],
                    ':discharge_date' => $data['discharge_date'],
                    ':reimburse_net' => $data['reimburse_net'],
                    ':reimburse_from' => $data['reimburse_from'],
                    ':error_code' => $data['error_code'],
                    ':main_fund' => $data['main_fund'],
                    ':sub_fund' => $data['sub_fund'],
                    ':service_type' => $data['service_type'],
                    ':referral' => $data['referral'],
                    ':entitlement' => $data['entitlement'],
                    ':right_used' => $data['right_used'],
                    ':ref_hospital' => $data['ref_hospital'],
                    ':total_claim' => $data['total_claim'],
                    ':file_source' => $uploadedFileName,
                ]);

                // ตรวจสอบว่ามีแถวถูกเพิ่มจริงหรือไม่
                if ($stmt->rowCount() > 0) {
                    $imported_count++;
                } else {
                    $skipped_count++;
                }
            }

            // 6. Commit transaction เมื่อนำเข้าสำเร็จ
            $pdo->commit();
            $upload_message = "✅ นำเข้าข้อมูลสำเร็จ: **{$imported_count}** แถว (จากไฟล์ **{$uploadedFileName}**, ข้ามเนื่องจากซ้ำ: {$skipped_count} แถว)";

        } catch (\PDOException $e) {
            // ดักจับข้อผิดพลาด SQL และ Rollback
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // แสดง Error SQL ที่ชัดเจน
            $upload_message = "❌ เกิดข้อผิดพลาด SQL: " . $e->getMessage() . " (โปรดตรวจสอบว่าคอลัมน์ 'file_source' มีอยู่ในตารางหรือไม่)";
        }
        catch (\Exception $e) {
            // ดักจับข้อผิดพลาดทั่วไป
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $upload_message = "❌ เกิดข้อผิดพลาดในการนำเข้า: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้าข้อมูล eClaim Hsub</title>
    <style>
        /* Import Font Prompt from Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap');

        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-top: 5px solid #ffc107; /* Primary color line (เหลือง) */
        }
        h1 {
            color: #343a40;
            text-align: center;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 400;
            border: 1px solid transparent;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        label {
            font-weight: 600;
            color: #495057;
        }
        input[type="file"] {
            border: 1px solid #ced4da;
            padding: 10px;
            border-radius: 6px;
            background-color: #f8f9fa;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💾 นำเข้าข้อมูล eClaim Hsub (Excel/CSV)</h1>

        <?php
        if (isset($upload_message) && $upload_message):
            $message_class = (strpos($upload_message, '✅') !== false) ? 'success' : 'error';
        ?>
            <div class="message-box <?php echo $message_class; ?>">
                <?php echo $upload_message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            <label for="fileInput">เลือกไฟล์ Excel (.xlsx, .xls) หรือ CSV:</label>
            <input type="file" name="file" id="fileInput" required accept=".xlsx, .xls, .csv">
            <input type="submit" name="importSubmit" value="🚀 เริ่มนำเข้าข้อมูล">
        </form>

        <div class="message-box warning" style="margin-top: 20px; text-align: center;">
            <p><strong>⚠️ การแก้ไขข้อผิดพลาด:</strong></p>
            <p>
                โปรด **รันคำสั่ง SQL เพื่อเพิ่มคอลัมน์ `file_source`** ในตาราง `eclaim_hsub_data` ก่อนใช้งาน:
                <code style="display: block; margin: 5px 0; background-color: #fce4e4; padding: 5px; border-radius: 3px;">ALTER TABLE `eclaim_hsub_data` ADD COLUMN `file_source` VARCHAR(255) NULL AFTER `total_claim`;</code>
            </p>
        </div>
    </div>
</body>
</html>
