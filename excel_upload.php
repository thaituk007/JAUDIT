<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$config = require 'config.php';
$pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8", $config['db_user'], $config['db_pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // ลบ header 3 แถวแรก (ตามไฟล์ตัวอย่าง)
        $dataRows = array_slice($rows, 3);

        $stmt = $pdo->prepare("INSERT INTO rpt_excel_import (no, province, amphur, tambon, village, cid, fullname, sex, age, dob, typearea, upload_by, upload_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $count = 0;
        foreach ($dataRows as $row) {
            if (!isset($row[5]) || empty($row[5])) continue; // cid ว่าง ข้าม

            $stmt->execute([
                $row[0], $row[1], $row[2], $row[3], $row[4],
                $row[5], $row[6], $row[7], $row[8], $row[9],
                $row[10],
                'admin', date('Y-m-d H:i:s')
            ]);
            $count++;
        }

        echo "<p>✅ อัปโหลดเรียบร้อยแล้ว: $count รายการ</p>";
        echo '<p><a href="excel_display.php">ดูรายงาน</a></p>';

    } catch (Exception $e) {
        echo "<p>❌ ผิดพลาด: " . $e->getMessage() . "</p>";
    }
}
?>

<h3>📥 อัปโหลดไฟล์ Excel</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="excel_file" accept=".xls,.xlsx" required>
    <button type="submit">อัปโหลด</button>
</form>
