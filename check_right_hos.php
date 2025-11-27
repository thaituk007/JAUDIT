<?php
// โหลด autoload ก่อน
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// โหลด config
$config = include('config.php');

// เชื่อมต่อฐานข้อมูล
$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
if ($mysqli->connect_errno) {
    die("<div style='color:red; font-weight:bold;'>Connect failed: " . htmlspecialchars($mysqli->connect_error) . "</div>");
}

// ฟังก์ชันช่วยรัน query และเช็ค error
function runQuery($mysqli, $sql, $label = '') {
    $result = $mysqli->query($sql);
    if (!$result) {
        echo "<div style='color:red; font-weight:bold; margin-bottom:20px;'>Query error ($label): " . htmlspecialchars($mysqli->error) . "</div>";
        return false;
    }
    return $result;
}

// ================== จำนวน CID ==================
$countSql = "SELECT COUNT(*) AS total FROM person_checkright";
$countResult = runQuery($mysqli, $countSql, 'person_checkright');
$totalCid = 0;
if ($countResult) {
    $countData = $countResult->fetch_assoc();
    $totalCid = $countData['total'] ?? 0;
}

// ================== ดาวน์โหลด Excel ==================
if (isset($_POST['download_excel'])) {
    $sql = "SELECT cid AS pid
            FROM person
            WHERE nationality='99'
            AND cid NOT IN (SELECT cid FROM person_checkright)
            LIMIT 2000";
    $result = runQuery($mysqli, $sql, 'person template download');

    if ($result) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'PID');

        $row = 2;
        while ($data = $result->fetch_assoc()) {
            $sheet->setCellValueExplicit(
                'A' . $row,
                $data['pid'],
                DataType::TYPE_STRING
            );
            $row++;
        }

        $filename = "template_check_right_current.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// ================== ดึงข้อมูล PID ==================
$sql = "SELECT cid AS pid
        FROM person
        WHERE nationality='99'
        AND cid NOT IN (SELECT cid FROM person_checkright)
        LIMIT 2000";
$result = runQuery($mysqli, $sql, 'person list');

$dataList = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dataList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายชื่อ PID</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Prompt', sans-serif; background-color: #f5f7fa; padding: 40px; }
h2 { color: #333; margin-bottom: 10px; }
.count-display { font-size: 18px; color: #555; margin-bottom: 20px; text-align: center; }
table { border-collapse: collapse; width: 60%; max-width: 600px; margin-bottom: 20px; background-color: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
th, td { border-bottom: 1px solid #e0e0e0; padding: 12px 16px; text-align: left; }
th { background-color: #4a90e2; color: white; font-weight: 500; }
tr:last-child td { border-bottom: none; }
button { background-color: #4a90e2; color: white; border: none; padding: 12px 24px; font-size: 16px; font-weight: 500; border-radius: 6px; cursor: pointer; transition: background-color 0.3s; margin-bottom: 10px; }
button:hover { background-color: #357ABD; }
button.secondary { background-color: #777; }
button.secondary:hover { background-color: #555; }
.container { display: flex; flex-direction: column; align-items: center; }
input[type="file"] { margin-bottom: 10px; }
.error { color: red; font-weight: bold; margin-bottom: 10px; }
</style>
</head>
<body>

<div class="container">
    <h2>รายชื่อ PID</h2>
    <div class="count-display">
        จำนวน CID ใน person_checkright: <?php echo number_format($totalCid); ?>
    </div>

    <!-- ปุ่มดาวน์โหลด Excel -->
    <form method="post">
        <button type="submit" name="download_excel">ดาวน์โหลด template_check_right_current.xlsx</button>
    </form>

    <!-- ปุ่มนำเข้า SRM Excel -->
    <form action="check_right_current_update_hos.php" method="get">
        <button type="submit">นำเข้า SRM Excel</button>
    </form>

    <!-- ปุ่มกลับหน้าแรก -->
    <form action="index.php" method="get">
        <button type="submit" class="secondary">กลับหน้าแรก</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>PID</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($dataList)): ?>
                <?php foreach($dataList as $data): ?>
                <tr>
                    <td><?php echo htmlspecialchars($data['pid']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="1" style="text-align:center;">ไม่มีข้อมูล</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
