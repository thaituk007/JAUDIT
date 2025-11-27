<?php
// export_person_excel_mysql.php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$config = include 'config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

$stmt = $pdo->prepare("SELECT * FROM pcperson");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if (count($data) > 0) {
    // เขียน header คอลัมน์
    $sheet->fromArray(array_keys($data[0]), NULL, 'A1');
    // เขียนข้อมูลเริ่มแถวที่ 2
    $sheet->fromArray($data, NULL, 'A2');
} else {
    $sheet->setCellValue('A1', 'ไม่มีข้อมูล');
}

$filename = "pcperson_export_" . date('Ymd') . ".xlsx";

// ส่ง header ให้เบราว์เซอร์ดาวน์โหลดไฟล์
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
