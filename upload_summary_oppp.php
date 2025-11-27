<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$config = include 'config.php';
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function convertMonthThaiToEN($monthThai) {
    $months = [
        'ม.ค.' => '01', 'ก.พ.' => '02', 'มี.ค.' => '03',
        'เม.ย.' => '04', 'พ.ค.' => '05', 'มิ.ย.' => '06',
        'ก.ค.' => '07', 'ส.ค.' => '08', 'ก.ย.' => '09',
        'ต.ค.' => '10', 'พ.ย.' => '11', 'ธ.ค.' => '12'
    ];
    foreach ($months as $th => $num) {
        if (mb_strpos($monthThai, $th) === 0) {
            $parts = explode('-', $monthThai);
            if (count($parts) == 2) {
                $year = (int)$parts[1] - 543;
                return $year . '-' . $num;
            }
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $headerRow = 4;
    $header = $rows[$headerRow];
    $dataRows = array_slice($rows, $headerRow);

    $monthCols = [];
    foreach ($header as $key => $value) {
        if (preg_match('/\d{4}/u', $value) && mb_substr($value, 0, 3) !== 'รวม') {
            $monthCols[$key] = convertMonthThaiToEN($value);
        }
    }

    $uploadDate = date('Y-m-d H:i:s');
    $summaryData = [];

    foreach ($dataRows as $rowIndex => $row) {
        if ($rowIndex < $headerRow + 1 || empty($row['A'])) continue;
        $hospname = trim($row['A']);
        $hospcode = isset($row['B']) ? $row['B'] : null;

        foreach ($monthCols as $colKey => $reportMonth) {
            $qty = isset($row[$colKey]) ? (int) $row[$colKey] : 0;
            if ($reportMonth && $hospcode) {
                $stmt = $pdo->prepare("INSERT INTO oppp_summary (hospcode, hospname, report_month, qty, upload_date)
                    VALUES (:hospcode, :hospname, :report_month, :qty, :upload_date)
                ");
                $stmt->execute([
                    ':hospcode' => $hospcode,
                    ':hospname' => $hospname,
                    ':report_month' => $reportMonth,
                    ':qty' => $qty,
                    ':upload_date' => $uploadDate
                ]);

                if (!isset($summaryData[$reportMonth])) $summaryData[$reportMonth] = 0;
                if ($qty > 0) $summaryData[$reportMonth]++;
            }
        }
    }

    // Export Excel
    $export = new Spreadsheet();
    $exportSheet = $export->getActiveSheet();
    $exportSheet->setCellValue('A1', 'เดือน');
    $exportSheet->setCellValue('B1', 'จำนวนหน่วยบริการที่ส่ง');
    $rowNum = 2;
    foreach ($summaryData as $month => $count) {
        $exportSheet->setCellValue("A{$rowNum}", $month);
        $exportSheet->setCellValue("B{$rowNum}", $count);
        $rowNum++;
    }

    $writer = new Xlsx($export);
    $exportFilename = "summary_" . date("Ymd_His") . ".xlsx";
    $writer->save($exportFilename);

    // แสดงผล HTML + Chart.js
    echo "<h2>สรุปจำนวนหน่วยบริการที่ส่งข้อมูล (OPPP)</h2>";
    echo "<table border='1' cellpadding='6'>";
    echo "<tr><th>เดือน (yyyy-mm)</th><th>จำนวนหน่วยบริการ</th></tr>";
    foreach ($summaryData as $month => $count) {
        echo "<tr><td>$month</td><td>$count</td></tr>";
    }
    echo "</table><br>";

    echo '<canvas id="chart" height="120"></canvas>';
    echo "<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>";
    echo "<script>
        const ctx = document.getElementById('chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: " . json_encode(array_keys($summaryData)) . ",
                datasets: [{
                    label: 'จำนวนหน่วยบริการที่ส่ง',
                    data: " . json_encode(array_values($summaryData)) . ",
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }]
            }
        });
    </script>";

    echo "<br><a href='$exportFilename' download>📥 ดาวน์โหลดสรุป Excel</a>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>อัปโหลดข้อมูลสรุป OPPP</title>
</head>
<body>
    <h1>อัปโหลดไฟล์ Pivot Summary OPPP (.xls/.xlsx)</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="excel_file" required>
        <button type="submit">อัปโหลด</button>
    </form>
</body>
</html>
