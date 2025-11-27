<?php
set_time_limit(0);
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$successCount = 0;
$failCount = 0;
$skipCount = 0;
$invalidCount = 0;
$log = [];
$startTime = microtime(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cid_file'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    $uploadedFile = $uploadDir . basename($_FILES['cid_file']['name']);
    if (move_uploaded_file($_FILES['cid_file']['tmp_name'], $uploadedFile)) {
        $config = require __DIR__ . '/config.php';
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";

        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            exit("<p class='error'>❌ DB Error: {$e->getMessage()}</p>");
        }

        try {
            $client = new SoapClient("http://ucws.nhso.go.th:80/ucwstokenp1/UCWSTokenP1?wsdl", [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE
            ]);
        } catch (SoapFault $e) {
            exit("<p class='error'>❌ SOAP Error: {$e->getMessage()}</p>");
        }

        function convertResponseToArray($response) {
            if (!isset($response->return)) return [];
            $src = $response->return;
            $map = [
                'person_id'=>'Person_ID','title'=>'Title','fname'=>'Fname','lname'=>'Lname','sex'=>'Sex',
                'birthdate'=>'BirthDate','nation'=>'Nation','status'=>'Status','status_name'=>'StatusName',
                'purchase'=>'Purchase','chat'=>'Chat','province_name'=>'Province_Name','amphur_name'=>'Amphur_name',
                'tumbon_name'=>'Tumbon_name','moo'=>'Moo','mooBan_name'=>'MooBan_Name','pttype'=>'Pttype',
                'masterCupID'=>'MasterCupID','maininscl'=>'MainInscl','maininscl_name'=>'MainInscl_Name',
                'subinscl'=>'SubInscl','subinscl_name'=>'SubInscl_Name','card_id'=>'Card_ID','hmain'=>'HMain',
                'hmain_name'=>'HMain_Name','hmainop'=>'HMainOP','hsub'=>'HSub','hsub_name'=>'HSub_Name',
                'startdate'=>'StartDate','expdate'=>'ExpDate','remark'=>'Remark'
            ];
            $result = [];
            foreach ($map as $k => $v) {
                $result[$v] = isset($src->$k) ? $src->$k : null;
            }
            return $result;
        }

        function updateNHSOData($pdo, $data) {
            $sql = "REPLACE INTO hdc_nhso (" . implode(",", array_keys($data)) . ")
                    VALUES (:" . implode(", :", array_keys($data)) . ")";
            $stmt = $pdo->prepare($sql);
            foreach ($data as $k => $v) $stmt->bindValue(":$k", $v);
            return $stmt->execute();
        }

        $lines = file($uploadedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);

        echo "<h3>📊 กำลังประมวลผล {$total} รายการ</h3>";

        foreach ($lines as $i => $cid) {
            $cid = trim($cid);
            if (!preg_match('/^\d{13}$/', $cid)) {
                $invalidCount++;
                $log[] = ['cid'=>$cid, 'status'=>'⚠️ รูปแบบผิด'];
                continue;
            }

            try {
                $res = $client->__soapCall('searchCurrentByPID', [[
                    'user_person_id' => $config['nhso_cid'],
                    'smctoken' => $config['nhso_token'],
                    'person_id' => $cid
                ]]);

                $data = convertResponseToArray($res);
                if (empty($data['Person_ID'])) {
                    $skipCount++;
                    $log[] = ['cid'=>$cid, 'status'=>'ℹ️ ไม่มีข้อมูลสิทธิ์'];
                } elseif (updateNHSOData($pdo, $data)) {
                    $successCount++;
                    $log[] = ['cid'=>$cid, 'status'=>'✅ สำเร็จ'];
                } else {
                    $failCount++;
                    $log[] = ['cid'=>$cid, 'status'=>'❌ ล้มเหลว'];
                }
            } catch (Exception $e) {
                $failCount++;
                $log[] = ['cid'=>$cid, 'status'=>'❌ SOAP ERROR'];
            }
        }

        // Export
        $filename = 'nhso_result_' . date('Ymd_His');
        file_put_contents($uploadDir . "$filename.json", json_encode($log, JSON_UNESCAPED_UNICODE));
        $fp = fopen($uploadDir . "$filename.csv", 'w');
        fputcsv($fp, ['CID', 'สถานะ']); foreach ($log as $row) fputcsv($fp, [$row['cid'], $row['status']]); fclose($fp);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ลำดับ')->setCellValue('B1', 'CID')->setCellValue('C1', 'สถานะ');
        foreach ($log as $i => $row) {
            $sheet->setCellValue("A" . ($i + 2), $i + 1);
            $sheet->setCellValue("B" . ($i + 2), $row['cid']);
            $sheet->setCellValue("C" . ($i + 2), $row['status']);
        }
        (new Xlsx($spreadsheet))->save($uploadDir . "$filename.xlsx");

        // Summary
        $duration = round(microtime(true) - $startTime, 2);
        echo "<h3>✅ ตรวจสอบเสร็จสิ้นใน {$duration} วินาที</h3>";
        echo "<ul style='text-align:left;display:inline-block'>
            <li>✔️ สำเร็จ: $successCount</li>
            <li>❌ ล้มเหลว: $failCount</li>
            <li>ℹ️ ไม่มีข้อมูลสิทธิ์: $skipCount</li>
            <li>⚠️ รูปแบบผิด: $invalidCount</li>
        </ul>";
        echo "<p>📥 ดาวน์โหลด:
            <a href='uploads/{$filename}.csv' target='_blank'>CSV</a> |
            <a href='uploads/{$filename}.json' target='_blank'>JSON</a> |
            <a href='uploads/{$filename}.xlsx' target='_blank'>Excel</a></p>";

        // Show Table
        echo "<table border='1' cellpadding='6' style='margin:auto;border-collapse:collapse'>
            <tr><th>#</th><th>CID</th><th>สถานะ</th></tr>";
        foreach ($log as $i => $row) {
            echo "<tr><td>" . ($i+1) . "</td><td>{$row['cid']}</td><td>{$row['status']}</td></tr>";
        }
        echo "</table><br><button onclick='location.reload()'>🔄 เริ่มใหม่</button>";
    } else {
        echo "<p class='error'>❌ ไม่สามารถอัปโหลดไฟล์ได้</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบสิทธิ์ สปสช.</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; text-align: center; background: #f9f9f9; padding: 2rem; }
        form {
            display: inline-block;
            padding: 1rem 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px #ccc;
        }
        table { margin-top: 1rem; background: #fff; }
        th { background: #1976d2; color: white; }
        td, th { padding: 6px 12px; }
        .error { color: red; }
        input[type=file], button {
            padding: 0.5rem;
            margin: 0.5rem;
        }
    </style>
</head>
<body>
    <h2>✔️ ตรวจสอบสิทธิ์ สปสช. ด้วยไฟล์ .txt</h2>
    <form method="POST" enctype="multipart/form-data">
        <p>เลือกไฟล์ .txt ที่มีเลขบัตรประชาชน (13 หลัก)</p>
        <input type="file" name="cid_file" accept=".txt" required><br>
        <button type="submit">🔍 ตรวจสอบสิทธิ์</button>
    </form>
</body>
</html>
