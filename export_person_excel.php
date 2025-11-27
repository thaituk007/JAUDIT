<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// โหลด config
$config = include 'config.php';

// เชื่อมต่อฐานข้อมูล
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// ฟังก์ชันถอดรหัส
function decryptData($encrypted) {
    $key = 'mysecretkey1234567890abcd'; // คีย์ความยาว 32 ตัว
    $iv = substr($key, 0, 16);
    $decoded = base64_decode($encrypted);
    return openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
}

// แปลงวันที่
function parseDate($str) {
    return (preg_match('/^\d{8}$/', $str)) ?
        substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) : '';
}
function parseDateTime($str) {
    return (preg_match('/^\d{14}$/', $str)) ?
        substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) . ' ' .
        substr($str, 8, 2) . ':' . substr($str, 10, 2) . ':' . substr($str, 12, 2) : '';
}

// สร้าง Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Person Data');

// ตั้งหัวตาราง
$headers = ['CID', 'ชื่อ', 'นามสกุล', 'วันเกิด', 'ย้ายเข้า', 'อัปเดตล่าสุด'];
$sheet->fromArray($headers, null, 'A1');

// ดึงข้อมูลจากฐาน
$sql = "SELECT cid, name, lname, birth, movein, d_update FROM person LIMIT 1000";
$stmt = $pdo->query($sql);

// เริ่มบรรทัดที่ 2
$rowIndex = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sheet->setCellValue("A$rowIndex", decryptData($row['cid']));
    $sheet->setCellValue("B$rowIndex", decryptData($row['name']));
    $sheet->setCellValue("C$rowIndex", decryptData($row['lname']));
    $sheet->setCellValue("D$rowIndex", parseDate($row['birth']));
    $sheet->setCellValue("E$rowIndex", parseDate($row['movein']));
    $sheet->setCellValue("F$rowIndex", parseDateTime($row['d_update']));
    $rowIndex++;
}

// ตั้งค่า Header ส่งออกเป็นไฟล์ Excel
$filename = 'person_export_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
