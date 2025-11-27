<?php
/**
 * DRUGCAT_TTMT.php
 * HTML ตาราง + Export Excel พร้อม Highlight เงื่อนไขพิเศษ + UPDATEFLAG ใหม่
 */

session_save_path(sys_get_temp_dir());
session_start();
set_time_limit(0);
ini_set('memory_limit','512M');
date_default_timezone_set("Asia/Bangkok");

// โหลด autoload PhpSpreadsheet
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// โหลด config
$config = require __DIR__ . '/config.php';

// เชื่อมต่อ MySQL
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

// ดึง pcucode 5 หลัก
$pcucode = '00000';
$res_pcucode = $mysqli->query("SELECT LEFT(p.pcucodeperson,5) AS pcucode FROM person p GROUP BY LEFT(p.pcucodeperson,5) LIMIT 1");
if($row = $res_pcucode->fetch_assoc()){
    $pcucode = $row['pcucode'];
}
$res_pcucode->free();

// SQL ดึงข้อมูล DRUGCAT TTMT
$sql = "SELECT
    cdrug.drugcode AS HOSPDRUGCODE,
    CASE
        WHEN cdrug.drugcode24 LIKE '4%' THEN '3'
        ELSE '1'
    END AS PRODUCTCAT,
    ttmt_master.TMTID AS TMTID,
    '' AS SPECPREP,
    cdrug.drugname AS GENERICNAME,
    ttmt_master.TRADENAME AS TRADENAME,
    '' AS DSFCODE,
    ttmt_master.DOSAGEFORM AS DOSAGEFORM,
    ttmt_master.STRENGTH AS STRENGTH,
    l_ttmt_master_ed.DispUnit AS CONTENT,
    cdrug.sell AS UNITPRICE,
    t.comp AS DISTRIBUTOR,
    ttmt_master.MANUFACTURER AS MANUFACTURER,
    CASE
        WHEN l_ttmt_master_ed.ED = 'ED'  THEN 'E'
        WHEN l_ttmt_master_ed.ED = 'NED' THEN 'N'
        ELSE NULL
    END AS ISED,   -- ✅ ISED จาก l_ttmt_master_ed
    cdrug.drugcode24 AS NDC24,
    '' AS PACKSIZE,
    '' AS PACKPRICE,
    'A' AS UPDATEFLAG,
    ' ' AS DATECHANGE,
    DATE_FORMAT(CURRENT_DATE, '%d/%m/%Y') AS DATEUPDATE,
    CASE
        WHEN cdrug.drugcode24 LIKE '4%'
            THEN DATE_FORMAT('2025-03-01', '%d/%m/%Y')
        ELSE DATE_FORMAT('2024-10-01', '%d/%m/%Y')
    END AS DATEEFFECTIVE
FROM cdrug
LEFT JOIN _tmpdbregister t
       ON cdrug.drugcode24 = t.stdcode
LEFT JOIN cdrugunitsell
       ON cdrug.unitsell = cdrugunitsell.unitsellcode
LEFT JOIN tmt_drug
       ON cdrug.tmtcode = tmt_drug.TPUCODE
LEFT JOIN tmt_ed
       ON cdrug.tmtcode = tmt_ed.TMTID   -- ✅ แก้ ON ซ้ำ
LEFT JOIN ttmt_master ON cdrug.drugcode24 AND ttmt_master.TMTID
LEFT JOIN l_ttmt_master_ed
       ON ttmt_master.TMTID = l_ttmt_master_ed.TTMTCode -- ✅ แล้วค่อย join ED
WHERE cdrug.drugflag = '1'
AND cdrug.chargeitem IN ('04')
AND cdrug.drugcode24 LIKE '4%'
AND cdrug.drugcode24=ttmt_master.NDC24
GROUP BY cdrug.drugcode
ORDER BY cdrug.drugname ASC";

$data = [];
if($result = $mysqli->query($sql)){
    while($row = $result->fetch_assoc()){
        // ปรับ UPDATEFLAG ตาม logic หลัง query (ตัวอย่าง)
        // คุณสามารถแก้เงื่อนไขจริง เช่น ราคาเปลี่ยนเป็น 'U', ยกเลิก 'D', แก้ไขข้อมูลยา 'E'
        if(!isset($row['UPDATEFLAG']) || $row['UPDATEFLAG']=='A'){
            $row['UPDATEFLAG'] = 'A'; // ค่าเริ่มต้น
        }
        $data[] = $row;
    }
    $result->free();
}
$mysqli->close();

// Export Excel
if(isset($_POST['export_excel']) && !empty($data)){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('DRUGCAT_TTMT');

    $columns = array_keys($data[0]);
    foreach($columns as $i => $col){
        $cell = Coordinate::stringFromColumnIndex($i+1).'1';
        $sheet->setCellValue($cell,$col);
        $sheet->getStyle($cell)->applyFromArray([
            'font'=>['bold'=>true,'name'=>'Prompt','color'=>['rgb'=>'FFFFFF']],
            'fill'=>['fillType'=>Fill::FILL_GRADIENT_LINEAR,'startColor'=>['rgb'=>'6366F1'],'endColor'=>['rgb'=>'A5B4FC']],
            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]]
        ]);
    }

    $rowIndex = 2;
    foreach($data as $row){
        foreach($columns as $i => $col){
            $cell = Coordinate::stringFromColumnIndex($i+1).$rowIndex;
            $sheet->setCellValue($cell,$row[$col]);

            // Highlight เงื่อนไข
            if($col == 'ISED' && $row[$col]=='E'){
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setRGB('A7F3D0');
            }
            if($col == 'PRODUCTCAT' && $row[$col]=='3'){
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setRGB('FED7AA');
            }
            if($col=='UPDATEFLAG'){
                switch($row[$col]){
                    case 'A': $color='C7D2FE'; break;
                    case 'D': $color='FCA5A5'; break;
                    case 'E': $color='FCD34D'; break;
                    case 'U': $color='A7F3D0'; break;
                    default: $color='FFFFFF'; break;
                }
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setRGB($color);
            }

            // Zebra Stripe
            if($rowIndex%2==0 && !in_array($row[$col],['E','3','A','D','U'])){
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setRGB('F3F4F6');
            }

            // Border
            $sheet->getStyle($cell)->getBorders()->getAllBorders()
                  ->setBorderStyle(Border::BORDER_THIN)
                  ->getColor()->setRGB('DDDDDD');
        }
        $rowIndex++;
    }

    // Auto width
    foreach(range(0,count($columns)-1) as $i){
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i+1))->setAutoSize(true);
    }

    $filename = $pcucode.'_TTMTDRUGCAT.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>DRUGCAT TTMT</title>
<style>
body{font-family:'Prompt',sans-serif;background:#f9fafb;color:#111827;padding:20px;}
table{border-collapse:collapse;width:100%;margin-bottom:20px;}
th,td{border:1px solid #ddd;padding:8px;text-align:center;}
th{background:linear-gradient(90deg,#6366F1,#A5B4FC);color:#fff;}
tr:nth-child(even){background-color:#f3f4f6;}
tr:hover{background-color:#e0e7ff;}
.ised-e{background-color:#A7F3D0;}
.productcat-3{background-color:#FED7AA;}
.update-a{background-color:#C7D2FE;}
.update-d{background-color:#FCA5A5;}
.update-e{background-color:#FCD34D;}
.update-u{background-color:#A7F3D0;}
button{background-color:#4F46E5;color:#fff;border:none;padding:10px 20px;font-size:14px;border-radius:5px;cursor:pointer;}
button:hover{background-color:#6366F1;}
</style>
</head>
<body>

<h2>ข้อมูล DRUGCAT TTMT จาก Master TTMT Release File ล่าสุด (สำหรับ Drug Catalogue สปสช.)</h2>
<p>จำนวนรายการ: <?= count($data) ?></p>

<div style="margin-bottom:10px; display:flex; gap:10px;">
    <form method="post">
        <button type="submit" name="export_excel">Export Excel</button>
    </form>

    <form action="index.php" method="get">
        <button type="submit">กลับหน้าแรก</button>
    </form>
</div>
<table>
<?php if(!empty($data)): ?>
<thead>
<tr>
<?php foreach(array_keys($data[0]) as $col): ?>
    <th><?= htmlspecialchars($col) ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach($data as $row): ?>
<tr>
<?php foreach($row as $colName => $value):
    $class = '';
    if($colName=='ISED' && $value=='E') $class='ised-e';
    if($colName=='PRODUCTCAT' && $value=='3') $class='productcat-3';
    if($colName=='UPDATEFLAG'){
        switch($value){
            case 'A': $class='update-a'; break;
            case 'D': $class='update-d'; break;
            case 'E': $class='update-e'; break;
            case 'U': $class='update-u'; break;
        }
    }
?>
<td class="<?= $class ?>"><?= htmlspecialchars($value) ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p>ไม่มีข้อมูล</p>
<?php endif; ?>

<div style="clear:both;"></div>
</body>
</html>
