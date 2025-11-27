<?php
require 'vendor/autoload.php'; // ต้องติดตั้งด้วย composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

date_default_timezone_set('Asia/Bangkok');

$config = require 'config.php';

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}

// SQL Query
$sql = "
SELECT
    village.villcode AS รหัสหมู่บ้าน,
    village.villname AS ชื่อหมู่บ้าน,
    p.typelive AS TypeArea,
    SUM(CASE WHEN p.typelive IN (1,2,3,4,5) THEN 1 ELSE 0 END) AS ทั้งหมดทุก_TypeArea,
    SUM(CASE WHEN p.typelive IN (1,3) THEN 1 ELSE 0 END) AS `1+3`,
    SUM(CASE WHEN p.typelive IN (1,2) THEN 1 ELSE 0 END) AS `1+2_ตามทะเบียนราษฎร์`,
    SUM(CASE WHEN p.typelive IN (1,2,3) THEN 1 ELSE 0 END) AS `1,2,3_ตามพื้นที่รับผิดชอบ`,
    SUM(CASE WHEN p.typelive = '1' THEN 1 ELSE 0 END) AS TypeArea_1,
    SUM(CASE WHEN p.typelive = '2' THEN 1 ELSE 0 END) AS TypeArea_2,
    SUM(CASE WHEN p.typelive = '3' THEN 1 ELSE 0 END) AS TypeArea_3,
    SUM(CASE WHEN p.typelive = '4' THEN 1 ELSE 0 END) AS TypeArea_4,
    SUM(CASE WHEN p.typelive = '5' THEN 1 ELSE 0 END) AS TypeArea_5
FROM person p
LEFT JOIN house h ON p.hcode = h.hcode AND p.pcucodeperson = h.pcucode
LEFT JOIN village ON h.villcode = village.villcode AND village.pcucode = h.pcucode
LEFT JOIN persondeath pd ON p.pid = pd.pid AND p.pcucodeperson = pd.pcucodeperson
WHERE SUBSTRING(h.villcode,7,2) <> '00' AND pd.pid IS NULL
GROUP BY village.villcode, village.villname, p.typelive

UNION ALL

SELECT
    'รวม', '', NULL,
    SUM(CASE WHEN p.typelive IN (1,2,3,4,5) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive IN (1,3) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive IN (1,2) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive IN (1,2,3) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '1' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '2' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '3' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '4' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '5' THEN 1 ELSE 0 END)
FROM person p
LEFT JOIN house h ON p.hcode = h.hcode AND p.pcucodeperson = h.pcucode
LEFT JOIN persondeath pd ON p.pid = pd.pid AND p.pcucodeperson = pd.pcucodeperson
WHERE SUBSTRING(h.villcode,7,2) <> '00' AND pd.pid IS NULL
";

$stmt = $pdo->query($sql);
$results = $stmt->fetchAll();

// ฟังก์ชันส่งออก .xls
function exportToXLS($results) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['รหัสหมู่บ้าน', 'ชื่อหมู่บ้าน', 'TypeArea', 'ทั้งหมดทุก TypeArea', '1+3',
        '1+2 ตามทะเบียนราษฎร์', '1,2,3 ตามพื้นที่รับผิดชอบ', 'TypeArea 1', 'TypeArea 2',
        'TypeArea 3', 'TypeArea 4', 'TypeArea 5'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col.'1', $header);
        $col++;
    }
    $rowNum = 2;
    foreach ($results as $row) {
        $sheet->fromArray(array_values($row), null, 'A'.$rowNum);
        $rowNum++;
    }
    $filename = 'population_typearea_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    $writer = new Xls($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    exportToXLS($results);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จำนวนประชากรแยกตาม TypeArea รายหมู่บ้าน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8f8f8;
            padding: 20px;
        }
        .header-print {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 10px;
        }
        .header-print img { height: 80px; }
        h2, h3 {
            text-align: center;
            color: #2c3e50;
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 13px;
            text-align: right;
        }
        th {
            background-color: #3498db;
            color: white;
            text-align: center;
        }
        td:first-child, td:nth-child(2), td:nth-child(3) {
            text-align: left;
        }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #e0f3ff; }
        .btn-export, .btn-print {
            display: inline-block;
            margin-bottom: 10px;
            padding: 10px 16px;
            background-color: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .btn-print { background-color: #2980b9; margin-right: 10px; }
        .footer-print {
            margin-top: 60px;
            text-align: center;
            font-size: 14px;
        }
        .footer-print .sig {
            display: inline-block;
            width: 250px;
            margin: 0 40px;
        }
        @media print {
            .btn-export, .btn-print { display: none !important; }
            body { background: white; font-size: 12pt; }
            .header-print img { height: 60px; }
        }
    </style>
</head>
<body>

<div class="header-print">
    <div>
        <h2>รายงานจำนวนประชากรแยกตาม TypeArea รายหมู่บ้าน</h2>
        <h3>โรงพยาบาลส่งเสริมสุขภาพตำบลของคุณ</h3>
    </div>
</div>

<a href="#" onclick="window.print();" class="btn-print">🖨️ พิมพ์เอกสาร</a>
<a href="?export=xls" class="btn-export">📥 ส่งออกเป็น Excel (.xls)</a>

<table>
    <thead>
        <tr>
            <th>รหัสหมู่บ้าน</th>
            <th>ชื่อหมู่บ้าน</th>
            <th>TypeArea</th>
            <th>ทั้งหมด</th>
            <th>1+3</th>
            <th>1+2 ทร.</th>
            <th>1,2,3 พท.</th>
            <th>TA 1</th>
            <th>TA 2</th>
            <th>TA 3</th>
            <th>TA 4</th>
            <th>TA 5</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $row): ?>
        <tr <?= ($row['รหัสหมู่บ้าน'] === 'รวม') ? 'style="font-weight:bold; background:#2ecc71; color:white;"' : '' ?>>
            <td><?= $row['รหัสหมู่บ้าน'] ?></td>
            <td><?= $row['ชื่อหมู่บ้าน'] ?></td>
            <td><?= $row['TypeArea'] ?></td>
            <td><?= number_format($row['ทั้งหมดทุก_TypeArea']) ?></td>
            <td><?= number_format($row['1+3']) ?></td>
            <td><?= number_format($row['1+2_ตามทะเบียนราษฎร์']) ?></td>
            <td><?= number_format($row['1,2,3_ตามพื้นที่รับผิดชอบ']) ?></td>
            <td><?= number_format($row['TypeArea_1']) ?></td>
            <td><?= number_format($row['TypeArea_2']) ?></td>
            <td><?= number_format($row['TypeArea_3']) ?></td>
            <td><?= number_format($row['TypeArea_4']) ?></td>
            <td><?= number_format($row['TypeArea_5']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="footer-print">
    <div class="sig">
        ..............................................<br>
        (ลงชื่อเจ้าหน้าที่ผู้รายงาน)<br>
        ตำแหน่ง .....................................
    </div>
    <div class="sig">
        วันที่พิมพ์: <?= date('d/m/Y') ?><br>
        เวลา: <?= date('H:i') ?> น.
    </div>
</div>

</body>
</html>
