<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// สมมติรับ $dataList, $reportType, $selectedYear, $selectedMonth จาก service_summary_day.php

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('รายงานบริการ');

// หัวตาราง
$headers = [
    $reportType === 'day' ? 'วันที่' : ($reportType === 'month' ? 'เดือน' : 'ปี'),
    'Person',
    'Visit',
    'แผลเรื้อรัง',
    'ทั่วไป',
    'แพทย์แผนไทย',
    'ทันตกรรม'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

$rowNum = 2;
$sum = ['person'=>0,'visit'=>0,'wound'=>0,'general'=>0,'thai'=>0,'dental'=>0];

foreach ($dataList as $row) {
    if ($reportType === 'day') {
        $displayDate = thaiDateFull($row['visitdate']);
    } elseif ($reportType === 'month') {
        list($y, $m) = explode('-', $row['visit_month']);
        $displayDate = thaiMonthName((int)$m) . ' ' . toBuddhistYear((int)$y);
    } else {
        $displayDate = toBuddhistYear((int)$row['visit_year']);
    }

    $sheet->setCellValue('A' . $rowNum, $displayDate);
    $sheet->setCellValue('B' . $rowNum, $row['person_count']);
    $sheet->setCellValue('C' . $rowNum, $row['visit_count']);
    $sheet->setCellValue('D' . $rowNum, $row['wound']);
    $sheet->setCellValue('E' . $rowNum, $row['general']);
    $sheet->setCellValue('F' . $rowNum, $row['thai_tradition']);
    $sheet->setCellValue('G' . $rowNum, $row['dental']);

    $sum['person'] += $row['person_count'];
    $sum['visit'] += $row['visit_count'];
    $sum['wound'] += $row['wound'];
    $sum['general'] += $row['general'];
    $sum['thai'] += $row['thai_tradition'];
    $sum['dental'] += $row['dental'];

    $rowNum++;
}

// รวมทั้งหมด
$sheet->setCellValue('A' . $rowNum, 'รวมทั้งหมด');
$sheet->setCellValue('B' . $rowNum, $sum['person']);
$sheet->setCellValue('C' . $rowNum, $sum['visit']);
$sheet->setCellValue('D' . $rowNum, $sum['wound']);
$sheet->setCellValue('E' . $rowNum, $sum['general']);
$sheet->setCellValue('F' . $rowNum, $sum['thai']);
$sheet->setCellValue('G' . $rowNum, $sum['dental']);

// ปรับความกว้างคอลัมน์
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'รายงานบริการราย' . $reportType . '_' . date('Ym') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
