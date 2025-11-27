<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// โหลด config
$config = include 'config.php';

// แปลงชื่อเดือนภาษาไทยใน header Excel เป็นรูปแบบ "YYYY-MM"
function thaiMonthToYm($text) {
    $months = [
        'ม.ค.' => '01', 'ก.พ.' => '02', 'มี.ค.' => '03', 'เม.ย.' => '04',
        'พ.ค.' => '05', 'มิ.ย.' => '06', 'ก.ค.' => '07', 'ส.ค.' => '08',
        'ก.ย.' => '09', 'ต.ค.' => '10', 'พ.ย.' => '11', 'ธ.ค.' => '12'
    ];
    foreach ($months as $th => $num) {
        if (strpos($text, $th) !== false) {
            if (preg_match('/(\d{4})/', $text, $matches)) {
                $year = $matches[1]; // พ.ศ.
                return $year . '-' . $num;
            }
        }
    }
    return null;
}

// แปลงจาก "YYYY-MM" เป็น "ธ.ค.-2567" (สำหรับแสดงผล)
function ymToThaiMonthYear($ym) {
    $months = [
        '01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.',
        '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.',
        '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'
    ];
    $parts = explode('-', $ym);
    if (count($parts) !== 2) return $ym;
    $year = (int)$parts[0];
    $month = $parts[1];
    $yearBuddhist = $year + 543;
    if (!isset($months[$month])) return $ym;
    return $months[$month] . '-' . $yearBuddhist;
}

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ DB ERROR: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // หน้าอัปโหลดไฟล์
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8" />
        <title>นำเข้าข้อมูล OPPP แบบ Pivot</title>
        <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
        <style>
            body {
                font-family: 'Prompt', sans-serif;
                background: #f0f4f8;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
                color: #2c3e50;
            }
            h2 {
                color: #27ae60;
                margin-bottom: 10px;
                font-weight: 700;
                font-size: 2rem;
            }
            form {
                background: white;
                padding: 30px 40px;
                border-radius: 12px;
                box-shadow: 0 6px 15px rgba(0,0,0,0.1);
                width: 360px;
                text-align: center;
                transition: box-shadow 0.3s ease;
            }
            form:hover {
                box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            }
            input[type=file] {
                margin-top: 15px;
                font-size: 1rem;
                width: 100%;
                padding: 8px;
                border: 1px solid #bdc3c7;
                border-radius: 6px;
                transition: border-color 0.3s ease;
                cursor: pointer;
            }
            input[type=file]:focus {
                border-color: #27ae60;
                outline: none;
            }
            button {
                margin-top: 25px;
                background-color: #27ae60;
                border: none;
                padding: 14px 40px;
                color: white;
                font-size: 1.1rem;
                cursor: pointer;
                border-radius: 8px;
                font-weight: 600;
                transition: background-color 0.3s ease;
                box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
            }
            button:hover {
                background-color: #219150;
                box-shadow: 0 6px 18px rgba(33, 145, 80, 0.5);
            }
            #currentTime {
                font-weight: 600;
                font-size: 1.1rem;
                margin-bottom: 20px;
                color: #34495e;
            }
        </style>
    </head>
    <body>
        <div id="currentTime"></div>
        <h2>📤 อัปโหลดไฟล์ Excel (.xls / .xlsx)</h2>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="excel_file" accept=".xls,.xlsx" required>
            <button type="submit">▶ ดำเนินการนำเข้า</button>
        </form>

        <script>
            function updateTime() {
                var now = new Date();
                var options = {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: false,
                    timeZone: 'Asia/Bangkok'
                };
                document.getElementById('currentTime').textContent = now.toLocaleString('th-TH', options);
            }
            updateTime();
            setInterval(updateTime, 1000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Process upload file
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    die("❌ กรุณาเลือกไฟล์ Excel (.xls/.xlsx) และส่งด้วย POST เท่านั้น");
}

$inputFile = $_FILES['excel_file']['tmp_name'];

// โหลดไฟล์ Excel
try {
    $spreadsheet = IOFactory::load($inputFile);
} catch (Exception $e) {
    die("❌ ไม่สามารถอ่านไฟล์ Excel ได้: " . $e->getMessage());
}

$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

// header เดือน อยู่แถวที่ 4 (index 4)
$headerIndex = 4;
$header = $rows[$headerIndex];

// หาเดือนและคอลัมน์ที่เป็นข้อมูล (เริ่มจากคอลัมน์ C เป็นต้นไป)
$monthCols = [];
foreach ($header as $col => $val) {
    if ($col < 'C') continue;
    $ym = thaiMonthToYm($val);
    if ($ym) {
        $monthCols[$col] = $ym;
    }
}

$inserted = 0;
$monthSet = [];

foreach ($rows as $rowIndex => $row) {
    if ($rowIndex <= $headerIndex) continue; // ข้าม header ขึ้นไป

    if (!isset($row['B'])) continue;

    $combined = trim($row['B']);
    if (strlen($combined) < 5) continue;

    // แยก hospcode 5 หลัก + hospname
    $hospcode = substr($combined, 0, 5);
    $hospname = trim(substr($combined, 5));

    if ($hospcode === '') continue;

    foreach ($monthCols as $col => $report_month) {
        $value = isset($row[$col]) ? trim($row[$col]) : '';

        if ($value === '') continue;

        // แปลงสถานะส่งข้อมูลเป็น 0/1
        $sent = 0;
        if (is_numeric($value)) {
            $sent = intval($value) > 0 ? 1 : 0;
        } elseif (preg_match('/ส่ง.?แล้ว/u', $value)) {
            $sent = 1;
        } elseif (preg_match('/ยัง.?ไม่.?ส่ง/u', $value)) {
            $sent = 0;
        }

        // ลบข้อมูลเดือนนี้ครั้งเดียว
        if (!in_array($report_month, $monthSet)) {
            $deleteStmt = $pdo->prepare("DELETE FROM oppp_pivot WHERE report_month = ?");
            $deleteStmt->execute([$report_month]);
            $monthSet[] = $report_month;
        }

        // insert
        $insertStmt = $pdo->prepare("INSERT INTO oppp_pivot (hospcode, hospname, report_month, sent) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$hospcode, $hospname, $report_month, $sent]);
        $inserted++;
    }
}

// ดึงข้อมูลสรุปทำกราฟ
$stmt = $pdo->query("
    SELECT report_month, COUNT(DISTINCT hospcode) AS total_sent
    FROM oppp_pivot
    WHERE sent = 1
    GROUP BY report_month
    ORDER BY report_month
");
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// แปลง labels สำหรับกราฟเป็น "ธ.ค.-2567"
$labels = array_map(function($item) {
    return ymToThaiMonthYear($item['report_month']);
}, $summary);

// ดึงรายละเอียดข้อมูลที่นำเข้าแสดงในตาราง
if (count($monthSet) > 0) {
    $placeholders = implode(',', array_fill(0, count($monthSet), '?'));
    $stmt2 = $pdo->prepare("
        SELECT hospname, report_month, sent
        FROM oppp_pivot
        WHERE report_month IN ($placeholders)
        ORDER BY report_month, hospname
    ");
    $stmt2->execute($monthSet);
    $details = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} else {
    $details = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>สรุปการนำเข้า OPPP</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f0f4f8;
            margin: 30px auto;
            max-width: 1000px;
            color: #34495e;
            text-align: center;
            line-height: 1.5;
        }
        #currentTime {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        h2 {
            color: #27ae60;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 2.2rem;
        }
        p {
            font-size: 1.1rem;
            margin: 0 0 25px;
            color: #555;
        }
        table {
            margin: 0 auto 40px;
            border-collapse: collapse;
            width: 100%;
            max-width: 900px;
            background: white;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        thead tr {
            background-color: #2ecc71;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }
        th, td {
            padding: 12px 18px;
            border-bottom: 1px solid #e1e8ed;
            text-align: left;
        }
        tbody tr:hover {
            background-color: #dff0d8;
            transition: background-color 0.3s ease;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafa;
        }
        td.center {
            text-align: center;
            font-size: 1.2rem;
        }
        a.button {
            display: inline-block;
            margin: 30px auto 0;
            background-color: #27ae60;
            color: white;
            text-decoration: none;
            padding: 14px 38px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 6px 12px rgba(39, 174, 96, 0.4);
            transition: background-color 0.3s ease;
        }
        a.button:hover {
            background-color: #1e874b;
            box-shadow: 0 8px 20px rgba(30, 135, 75, 0.6);
        }
        canvas#barChart {
            max-width: 900px;
            margin: 20px auto 40px;
            background: white;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div id="currentTime"></div>

<h2>✅ ดำเนินการนำเข้าสำเร็จ</h2>
<p>บันทึกข้อมูลแล้วทั้งหมด: <strong><?php echo number_format($inserted); ?></strong> รายการ</p>

<h3>รายละเอียดข้อมูลที่นำเข้า</h3>
<table>
    <thead>
        <tr>
            <th>ชื่อหน่วยบริการ (hospname)</th>
            <th>เดือนรายงาน (report_month)</th>
            <th style="text-align:center;">สถานะส่งข้อมูล</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($details as $d): ?>
        <tr>
            <td><?php echo htmlspecialchars($d['hospname']); ?></td>
            <td><?php echo htmlspecialchars($d['report_month']); ?></td>
            <td class="center"><?php echo $d['sent'] == 0 ? 'ไม่ส่งข้อมูล' : 'ส่งข้อมูล'; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<a href="" class="button">⬅ กลับหน้าอัปโหลด</a>

<script>
    function updateTime() {
        var now = new Date();
        var options = {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Bangkok'
        };
        document.getElementById('currentTime').textContent = now.toLocaleString('th-TH', options);
    }
    updateTime();
    setInterval(updateTime, 1000);

    var ctx = document.createElement('canvas');
    ctx.id = 'barChart';
    document.body.insertBefore(ctx, document.querySelector('a.button'));

    var chartCtx = ctx.getContext('2d');
    new Chart(chartCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'จำนวนแห่งที่ส่งข้อมูล',
                data: <?php echo json_encode(array_column($summary, 'total_sent')); ?>,
                backgroundColor: '#27ae60'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero:true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>

</body>
</html>
