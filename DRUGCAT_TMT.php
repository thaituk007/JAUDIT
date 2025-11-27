<?php
/**
 * DRUGCAT_TMT.php
 * HTML ตาราง + Export Excel พร้อม Highlight เงื่อนไขพิเศษ + pcucode
 */

session_save_path(sys_get_temp_dir());
session_start();
set_time_limit(0);
ini_set('memory_limit', '512M');
date_default_timezone_set("Asia/Bangkok");

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$config = require __DIR__ . '/config.php';

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
if ($mysqli->connect_errno) {
    die("Failed to connect MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

// -------------------- PCUCODE --------------------
$pcucode = '00000';
$res_pcucode = $mysqli->query("
    SELECT LEFT(p.pcucodeperson,5) AS pcucode
    FROM person p
    GROUP BY LEFT(p.pcucodeperson,5)
    LIMIT 1
");
if ($row = $res_pcucode->fetch_assoc()) {
    $pcucode = $row['pcucode'];
}
$res_pcucode->free();

// -------------------- SQL Query --------------------
$sql = "
SELECT
    cdrug.drugcode AS HOSPDRUGCODE,        -- รหัสยาใน รพ.
    '1' AS PRODUCTCAT,                     -- ประเภทสินค้า (1 = ยา)
    cdrug.tmtcode AS TMTID,                -- รหัสยา TMT
    '' AS SPECPREP,                        -- ข้อมูลพิเศษ (ยังไม่ใช้)
    cdrug.drugname AS GENERICNAME,         -- ชื่อสามัญทางยา
    t.tradename AS TRADENAME,              -- ชื่อการค้า
    '' AS DSFCODE,                         -- ยังไม่ได้ใช้
    t.dosageform AS DOSAGEFORM,            -- รูปแบบยา เช่น syrup, powder, tablet, capsule, injection
    t.strength AS STRENGTH,                -- ความแรงของยา เช่น 500 mg/tablet
    t.unit AS CONTENT,                     -- ขนาดบรรจุ
    cdrug.sell AS UNITPRICE,               -- ราคาขาย
    t.comp AS DISTRIBUTOR,                 -- บริษัทผู้จัดจำหน่าย
    tmt_drug.Manufacturer AS MANUFACTURER, -- บริษัทผู้ผลิต
    tmt_ed.ISED AS ISED,                   -- สถานะบัญชียาหลัก (E = ED, N = NED, E* = มีเงื่อนไข)
    cdrug.drugcode24 AS NDC24,             -- รหัสยา 24 หลัก
    '' AS PACKSIZE,                        -- ขนาดบรรจุภัณฑ์
    '' AS PACKPRICE,                       -- ราคาต่อ pack
    'A' AS UPDATEFLAG,                     -- A = เพิ่มข้อมูล
    ' ' AS DATECHANGE,                     -- วันที่เปลี่ยนแปลง
    DATE_FORMAT(CURRENT_DATE, '%d/%m/%Y') AS DATEUPDATE,  -- วันที่อัพเดต
    DATE_FORMAT('2024-10-01','%d/%m/%Y') AS DATEEFFECTIVE -- วันที่มีผล
FROM cdrug
LEFT JOIN _tmpdbregister t
       ON cdrug.drugcode24 = t.stdcode
LEFT JOIN cdrugunitsell
       ON cdrug.unitsell = cdrugunitsell.unitsellcode
LEFT JOIN tmt_ed
       ON cdrug.tmtcode = tmt_ed.TMTID
LEFT JOIN tmt_drug
       ON cdrug.tmtcode = tmt_drug.TPUCODE
WHERE cdrug.drugflag = '1'
  AND cdrug.drugtype = '01'                -- เฉพาะยาแผนปัจจุบัน
  AND cdrug.chargeitem IN ('03','04')      -- ยาที่อยู่ในหมวด chargeitem 03,04
GROUP BY cdrug.drugcode
ORDER BY cdrug.drugname ASC
";

// -------------------- ดึงข้อมูล --------------------
$data = [];
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $result->free();
} else {
    die("SQL Error: " . $mysqli->error);
}
$mysqli->close();

// -------------------- Export Excel --------------------
if (isset($_POST['export_excel'])) {
    if (empty($data)) {
        die("ไม่มีข้อมูลสำหรับ Export Excel");
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('DRUGCAT_TTMT');

    $columns = array_keys($data[0]);
    $colCount = count($columns);

    // Header
    foreach ($columns as $i => $col) {
        $cell = Coordinate::stringFromColumnIndex($i + 1) . '1';
        $sheet->setCellValue($cell, $col);
    }

    $headerRange = "A1:" . Coordinate::stringFromColumnIndex($colCount) . "1";
    $sheet->getStyle($headerRange)->applyFromArray([
        'font'      => ['bold' => true, 'name' => 'Prompt', 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_GRADIENT_LINEAR, 'startColor' => ['rgb' => '6366F1'], 'endColor' => ['rgb' => 'A5B4FC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ]);

    // Data
    $rowIndex = 2;
    foreach ($data as $row) {
        foreach ($columns as $i => $col) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . $rowIndex;
            $sheet->setCellValue($cell, $row[$col]);

            // Highlight
            if ($col == 'ISED' && $row[$col] == 'E') {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('A7F3D0');
            }
            if ($col == 'PRODUCTCAT' && $row[$col] == '3') {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FED7AA');
            }

            // Zebra
            if ($rowIndex % 2 == 0 && !($col == 'ISED' && $row[$col] == 'E') && !($col == 'PRODUCTCAT' && $row[$col] == '3')) {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
            }

            // Border
            $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DDDDDD');
        }
        $rowIndex++;
    }

    // Autosize
    foreach (range(1, $colCount) as $i) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    $filename = $pcucode . '_TMTDRUGCAT.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false); // ช่วยลด memory
    $writer->save('php://output');
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>DRUGCAT TMT</title>
<style>
body {font-family:'Prompt',sans-serif;background:#f9fafb;color:#111827;padding:20px;}
table {border-collapse:collapse;width:100%;margin-bottom:20px;}
th,td {border:1px solid #ddd;padding:8px;text-align:center;}
th {background:linear-gradient(90deg,#6366F1,#A5B4FC);color:#fff;}
tr:nth-child(even) {background-color:#f3f4f6;}
tr:hover {background-color:#e0e7ff;}
.ised-e {background-color:#A7F3D0;}
.productcat-3 {background-color:#FED7AA;}
button{background-color:#4F46E5;color:#fff;border:none;padding:10px 20px;font-size:14px;border-radius:5px;cursor:pointer;}
button:hover{background-color:#6366F1;}
</style>
</head>
<body>

<h2>ข้อมูล DRUGCAT TMT เฉพาะยาแผนปัจจุบัน สำหรับส่ง Drug Catalogue ที่ไม่มีไฟล์ยา APPROVED จาก รพ.แม่ข่าย</h2>
<p>จำนวนรายการ: <?= number_format(count($data)) ?></p>

<div style="margin-bottom:10px; display:flex; gap:10px;">
    <form method="post">
        <button type="submit" name="export_excel">Export Excel</button>
    </form>

    <form action="index.php" method="get">
        <button type="submit">กลับหน้าแรก</button>
    </form>
</div>
<table>
<thead>
<tr>
<?php if (!empty($data)): ?>
    <?php foreach (array_keys($data[0]) as $col): ?>
        <th><?= htmlspecialchars($col) ?></th>
    <?php endforeach; ?>
<?php else: ?>
    <th>ไม่มีข้อมูล</th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<?php if (!empty($data)): ?>
    <?php foreach ($data as $row): ?>
    <tr>
        <?php foreach ($row as $colName => $value):
            $class = '';
            if ($colName == 'ISED' && $value == 'E') $class = 'ised-e';
            if ($colName == 'PRODUCTCAT' && $value == '3') $class = 'productcat-3';
        ?>
        <td class="<?= $class ?>"><?= htmlspecialchars($value) ?></td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="20">ไม่มีข้อมูล</td></tr>
<?php endif; ?>
</tbody>
</table>

</body>
</html>
