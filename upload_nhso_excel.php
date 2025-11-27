<?php
session_start();
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$config = include 'config.php';
date_default_timezone_set('Asia/Bangkok');

function convertThaiDateToYMD($dateText) {
    $months = [
        'ม.ค.' => '01', 'ก.พ.' => '02', 'มี.ค.' => '03',
        'เม.ย.' => '04', 'พ.ค.' => '05', 'มิ.ย.' => '06',
        'ก.ค.' => '07', 'ส.ค.' => '08', 'ก.ย.' => '09',
        'ต.ค.' => '10', 'พ.ย.' => '11', 'ธ.ค.' => '12'
    ];
    $parts = preg_split('/\s+/', trim($dateText));
    if (count($parts) === 3) {
        $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $month = $months[$parts[1]] ?? '00';
        $year = ((int)$parts[2]) - 543;
        return "$year-$month-$day";
    }
    return null;
}

function outputHeader() {
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>นำเข้าไฟล์ Excel NHSO</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet">';
    echo '<style>
        body { font-family: "Prompt", sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        .container { max-width: 700px; margin: auto; background:#fff; padding:25px; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.1);}
        h1,h2 { color:#0b63ce; }
        label { font-weight:600; display:block; margin-bottom:8px; }
        input[type=file] { width: 100%; padding:10px; border:1px solid #ccc; border-radius:8px; }
        button { background:#0b63ce; color:#fff; border:none; padding:12px 20px; border-radius:10px; font-size:16px; cursor:pointer; }
        button:hover { background:#094a9e; }
        .progress { background:#e0e0e0; border-radius:20px; overflow:hidden; margin-top:20px; }
        .progress-bar { height: 26px; width: 0; background:#0b63ce; text-align:center; color:#fff; line-height:26px; transition: width 0.3s; }
        .summary { margin-top:20px; padding:15px; background:#eef7ff; border-radius:10px; color:#0b63ce; font-weight:600; }
        .error { color:#cc0000; font-weight:700; margin-top:15px; }
        .btn-group { margin-top: 30px; text-align: center; }
        .btn-group a button { margin: 5px; }
        .back-btn { background: #6c757d; }
        .back-btn:hover { background: #5a6268; }
        .home-btn { background: #28a745; }
        .home-btn:hover { background: #218838; }
    </style></head><body><div class="container">';
}

function outputFooter() {
    echo '<div class="btn-group">
            <a href="' . $_SERVER['PHP_SELF'] . '">
                <button class="back-btn">⬅ กลับไปหน้าอัปโหลดไฟล์</button>
            </a>
            <a href="index.php">
                <button class="home-btn">🏠 กลับไปหน้าแรก</button>
            </a>
          </div>';
    echo '</div></body></html>';
}

outputHeader();

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("<p class='error'>เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . htmlspecialchars($e->getMessage()) . "</p>");
}

$inserted = 0;
$skipped = 0;
$errors = [];
$hospcode = $hospname = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();

    $a3 = trim($sheet->getCell('A3')->getValue());
    if (!preg_match('/(\d{5})\s+(.+)/u', $a3, $matches)) {
        throw new Exception("ไม่พบรหัสหรือชื่อหน่วยบริการใน Cell A3");
    }
    $hospcode = $matches[1];
    $hospname = trim($matches[2]);

    $upload_date = date('Y-m-d');

    $pdo->prepare("DELETE FROM nhso_excel WHERE hospcode = ?")->execute([$hospcode]);

    echo "<h1>นำเข้าไฟล์ NHSO จากระบบ Smart Money Transfer</h1>";
    echo "<h2>หน่วยบริการ: $hospcode - $hospname</h2>";
    echo '<div class="progress"><div class="progress-bar" id="progressBar">0%</div></div>';

    $startRow = 6;
    $highestRow = $sheet->getHighestDataRow();

    $insertStmt = $pdo->prepare("INSERT INTO nhso_excel (
        hospcode, hospname, upload_date, batch_no, fund, amount, transfer_date,
        pay_no, sub_fund, sub_fund_detail, hold_transfer, contract_guarantee,
        tax, remaining, waiting_deduction, transferred_amount
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    for ($row = $startRow; $row <= $highestRow; $row++) {
        $transfer_val = trim($sheet->getCell("B$row")->getValue());
        $batch_no = trim($sheet->getCell("C$row")->getValue());
        $pay_no = trim($sheet->getCell("D$row")->getValue());
        $sub_fund = trim($sheet->getCell("E$row")->getValue());
        $fund = trim($sheet->getCell("F$row")->getValue());
        $sub_fund_detail = trim($sheet->getCell("G$row")->getValue());
        $amountRaw = $sheet->getCell("H$row")->getValue();
        $hold_transfer = trim($sheet->getCell("I$row")->getValue());
        $waiting_deduction_1 = trim($sheet->getCell("J$row")->getValue());
        $contract_guarantee = trim($sheet->getCell("K$row")->getValue());
        $tax = trim($sheet->getCell("L$row")->getValue());
        $remaining = trim($sheet->getCell("M$row")->getValue());
        $waiting_deduction_2 = trim($sheet->getCell("N$row")->getValue());
        $transferred_amount = trim($sheet->getCell("O$row")->getValue());

        $amount = is_numeric($amountRaw) ? floatval($amountRaw) : 0;

        $transfer_date = null;
        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($sheet->getCell("B$row"))) {
            $transfer_date = date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($transfer_val));
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $transfer_val)) {
            $transfer_date = $transfer_val;
        } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $transfer_val)) {
            $parts = explode('/', $transfer_val);
            $transfer_date = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        } elseif (preg_match('/\d{1,2}\s\D+\s\d{4}/', $transfer_val)) {
            $transfer_date = convertThaiDateToYMD($transfer_val);
        }

        if (empty($batch_no)) {
            $skipped++;
            continue;
        }

        if ($transfer_date !== null) {
            $pdo->prepare("DELETE FROM nhso_excel WHERE hospcode=? AND batch_no=? AND fund=? AND transfer_date=?")
                ->execute([$hospcode, $batch_no, $fund, $transfer_date]);
        } else {
            $pdo->prepare("DELETE FROM nhso_excel WHERE hospcode=? AND batch_no=? AND fund=? AND transfer_date IS NULL")
                ->execute([$hospcode, $batch_no, $fund]);
        }

        try {
            $insertStmt->execute([
                $hospcode,
                $hospname,
                $upload_date,
                $batch_no,
                $fund,
                $amount,
                $transfer_date,
                $pay_no,
                $sub_fund,
                $sub_fund_detail,
                $hold_transfer,
                $contract_guarantee,
                $tax,
                $remaining,
                $waiting_deduction_2,
                $transferred_amount
            ]);
            $inserted++;

            $percent = intval(($inserted / ($highestRow - $startRow + 1)) * 100);
            echo "<script>
                var bar = document.getElementById('progressBar');
                bar.style.width = '{$percent}%';
                bar.textContent = '{$percent}%';
            </script>";
            flush();
            ob_flush();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    echo "<div class='summary'>นำเข้าข้อมูลสำเร็จ $inserted รายการ<br>ข้าม $skipped รายการ (batch_no ว่าง)</div>";
    if (!empty($errors)) {
        echo "<div class='error'><strong>ข้อผิดพลาด:</strong><ul>";
        foreach ($errors as $err) {
            echo "<li>" . htmlspecialchars($err) . "</li>";
        }
        echo "</ul></div>";
    }

    $totalTransferred = 0;
    try {
        $stmtSum = $pdo->prepare("SELECT SUM(CAST(REPLACE(transferred_amount, ',', '') AS DECIMAL(15,2))) as total FROM nhso_excel WHERE hospcode = ?");
        $stmtSum->execute([$hospcode]);
        $rowSum = $stmtSum->fetch(PDO::FETCH_ASSOC);
        $totalTransferred = $rowSum['total'] ?? 0;
    } catch (Exception $e) {
        $totalTransferred = 0;
    }

    echo "<div class='summary' style='margin-top:10px; font-weight:bold; color:#1a7cc9;'>";
    echo "ยอดเงินโอนเข้าบัญชีรวมทั้งหมด: " . number_format($totalTransferred, 2) . " บาท";
    echo "</div>";

} else {
    echo '<h1>นำเข้าไฟล์ Excel NHSO จากระบบ Smart Money Transfer</h1>
        <form method="POST" enctype="multipart/form-data" onsubmit="startProgress()">
            <label for="excel_file">เลือกไฟล์ Excel (.xlsx):</label>
            <input type="file" id="excel_file" name="excel_file" accept=".xlsx" required>
            <br><br>
            <button type="submit">📥 นำเข้า</button>
        </form>';
    echo '<div class="progress"><div class="progress-bar" id="progressBar">0%</div></div>';
}
?>

<script>
function startProgress() {
    const bar = document.getElementById('progressBar');
    bar.style.width = '0%';
    bar.textContent = '0%';
}
</script>

<?php outputFooter(); ?>
<?php include 'footer.php'; ?>
