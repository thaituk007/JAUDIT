<?php
require 'vendor/autoload.php'; // โหลด PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$config = include __DIR__ . '/config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage());
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// กรอง search เป็นเลข 5 หลัก หรือว่าง
if ($search !== '' && !preg_match('/^\d{5}$/', $search)) {
    die('กรุณากรอกรหัสหน่วยบริการ 5 หลัก เช่น 12345');
}

$sql = "
SELECT
    person_data.hospcode,
    chospital.hosname,
    COUNT(cid) AS total,
    SUM(CASE WHEN typearea='1' THEN 1 ELSE 0 END) AS ta1,
    SUM(CASE WHEN typearea='2' THEN 1 ELSE 0 END) AS ta2,
    SUM(CASE WHEN typearea='3' THEN 1 ELSE 0 END) AS ta3,
    SUM(CASE WHEN typearea='4' THEN 1 ELSE 0 END) AS ta4,
    SUM(CASE WHEN typearea='5' THEN 1 ELSE 0 END) AS ta5
FROM person_data
LEFT JOIN chospital ON person_data.hospcode = chospital.hoscode
";

if ($search !== '') {
    $sql .= " WHERE person_data.hospcode LIKE :search ";
}

$sql .= "
GROUP BY person_data.hospcode, chospital.hosname
ORDER BY person_data.hospcode ASC
";

$stmt = $pdo->prepare($sql);
if ($search !== '') {
    $stmt->execute(['search' => $search . '%']);
} else {
    $stmt->execute();
}

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สร้าง Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('รายงาน TypeArea');

// ตั้งหัวตาราง
$sheet->setCellValue('A1', 'รหัสหน่วยบริการ');
$sheet->setCellValue('B1', 'ชื่อหน่วยบริการ');
$sheet->setCellValue('C1', 'รวม');
$sheet->setCellValue('D1', 'TypeArea 1');
$sheet->setCellValue('E1', 'TypeArea 2');
$sheet->setCellValue('F1', 'TypeArea 3');
$sheet->setCellValue('G1', 'TypeArea 4');
$sheet->setCellValue('H1', 'TypeArea 5');

// ใส่ข้อมูลทีละแถว
$rowNum = 2;
foreach ($data as $row) {
    $sheet->setCellValue("A{$rowNum}", $row['hospcode']);
    $sheet->setCellValue("B{$rowNum}", $row['hosname']);
    $sheet->setCellValue("C{$rowNum}", $row['total']);
    $sheet->setCellValue("D{$rowNum}", $row['ta1']);
    $sheet->setCellValue("E{$rowNum}", $row['ta2']);
    $sheet->setCellValue("F{$rowNum}", $row['ta3']);
    $sheet->setCellValue("G{$rowNum}", $row['ta4']);
    $sheet->setCellValue("H{$rowNum}", $row['ta5']);
    $rowNum++;
}

// ตั้งความกว้างคอลัมน์ให้อ่านง่าย
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ส่ง header ให้เบราว์เซอร์ดาวน์โหลดไฟล์ Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="report_typearea.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
