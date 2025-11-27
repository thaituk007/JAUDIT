<?php
require_once __DIR__ . '/functions.php';  // ฟังก์ชันช่วยเหลือ (thaiMonthName, toBuddhistYear, thaiDateFull)
require_once __DIR__ . '/vendor/autoload.php';

use Mpdf\Mpdf;

// รับตัวแปรจาก service_summary_day.php (ต้องส่งมาให้ไฟล์นี้ เช่น via include หรือ session)
// $dataList = [...]; // ข้อมูลรายงาน
// $reportType = 'day' หรือ 'month' หรือ 'year'
// $selectedYear = 2025 (ปี ค.ศ.)
// $selectedMonth = 7 (ถ้ามี)

// กำหนดโฟลเดอร์ฟอนต์ Prompt (ต้องมีไฟล์ในโฟลเดอร์ fonts/)
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4-L',
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/fonts']),
    'fontdata' => $fontData + [
        'prompt' => [
            'R' => 'Prompt-Regular.ttf',
            'B' => 'Prompt-Bold.ttf',
            'I' => 'Prompt-Italic.ttf',
            'BI' => 'Prompt-BoldItalic.ttf'
        ]
    ],
    'default_font' => 'prompt',
]);

// กำหนดหัวเรื่องรายงานตามประเภท
$title = 'รายงานบริการราย' . ($reportType === 'day' ? 'วัน' : ($reportType === 'month' ? 'เดือน' : 'ปี'));

if ($reportType === 'day' && isset($selectedMonth, $selectedYear)) {
    $headerDate = thaiMonthName((int)$selectedMonth) . ' ' . toBuddhistYear((int)$selectedYear);
} elseif ($reportType === 'month' && isset($selectedYear)) {
    $headerDate = toBuddhistYear((int)$selectedYear);
} else {
    $headerDate = '';
}

// สร้าง HTML สำหรับรายงาน
$html = '<h3 style="font-family: prompt;">' . $title . ': ' . $headerDate . '</h3>';
$html .= '<style>
table { border-collapse: collapse; width: 100%; font-family: prompt; }
th, td { border: 1px solid #000; padding: 6px; font-size: 14px; text-align: center; }
th { background-color: #f0f0f0; }
</style>';

$html .= '<table>';
$html .= '<thead><tr>';
$html .= '<th>' . ($reportType === 'day' ? 'วันที่' : ($reportType === 'month' ? 'เดือน' : 'ปี')) . '</th>';
$html .= '<th>Person</th><th>Visit</th><th>แผลเรื้อรัง</th><th>ทั่วไป</th><th>แพทย์แผนไทย</th><th>ทันตกรรม</th>';
$html .= '</tr></thead><tbody>';

$sum = ['person' => 0, 'visit' => 0, 'wound' => 0, 'general' => 0, 'thai' => 0, 'dental' => 0];

// วนลูปแสดงข้อมูล
foreach ($dataList as $row) {
    if ($reportType === 'day') {
        $displayDate = thaiDateFull($row['visitdate']);
    } elseif ($reportType === 'month') {
        list($y, $m) = explode('-', $row['visit_month']);
        $displayDate = thaiMonthName((int)$m) . ' ' . toBuddhistYear((int)$y);
    } else { // year
        $displayDate = toBuddhistYear((int)$row['visit_year']);
    }

    $html .= '<tr>';
    $html .= '<td>' . $displayDate . '</td>';
    $html .= '<td>' . number_format($row['person_count']) . '</td>';
    $html .= '<td>' . number_format($row['visit_count']) . '</td>';
    $html .= '<td>' . number_format($row['wound']) . '</td>';
    $html .= '<td>' . number_format($row['general']) . '</td>';
    $html .= '<td>' . number_format($row['thai_tradition']) . '</td>';
    $html .= '<td>' . number_format($row['dental']) . '</td>';
    $html .= '</tr>';

    $sum['person'] += $row['person_count'];
    $sum['visit'] += $row['visit_count'];
    $sum['wound'] += $row['wound'];
    $sum['general'] += $row['general'];
    $sum['thai'] += $row['thai_tradition'];
    $sum['dental'] += $row['dental'];
}

// แถวรวมทั้งหมด
$html .= '<tr style="font-weight:bold; background-color:#d4edda;">';
$html .= '<td>รวมทั้งหมด</td>';
$html .= '<td>' . number_format($sum['person']) . '</td>';
$html .= '<td>' . number_format($sum['visit']) . '</td>';
$html .= '<td>' . number_format($sum['wound']) . '</td>';
$html .= '<td>' . number_format($sum['general']) . '</td>';
$html .= '<td>' . number_format($sum['thai']) . '</td>';
$html .= '<td>' . number_format($sum['dental']) . '</td>';
$html .= '</tr>';

$html .= '</tbody></table>';

// เขียน HTML ลง PDF
$mpdf->WriteHTML($html);

// ตั้งชื่อไฟล์โดยใช้ปีและเดือนปัจจุบัน
$filename = 'รายงานบริการราย' . $reportType . '_' . date('Ym') . '.pdf';

// ส่งไฟล์ PDF ให้ดาวน์โหลด
$mpdf->Output($filename, 'D');
exit;
