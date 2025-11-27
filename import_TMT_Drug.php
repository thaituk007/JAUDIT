<?php
session_save_path(sys_get_temp_dir());
session_start();
ini_set('memory_limit', '512M'); // เพิ่ม memory สำหรับไฟล์ใหญ่
set_time_limit(0);
date_default_timezone_set("Asia/Bangkok");

// โหลด config
$config = require __DIR__ . '/config.php';

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=tis620";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// ไฟล์ progress
$progressFile = __DIR__ . "/progress_TMT_Drug.json";

// API สำหรับ progress
if (isset($_GET['progress'])) {
    if (file_exists($progressFile)) {
        header('Content-Type: application/json; charset=utf-8');
        echo file_get_contents($progressFile);
        exit;
    } else {
        echo json_encode(["percent" => 0, "current" => 0, "total" => 0]);
        exit;
    }
}

// ถ้ามีการอัปโหลดไฟล์ Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    require 'vendor/autoload.php'; // PhpSpreadsheet

    $file = $_FILES['excelFile']['tmp_name'];

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true); // ลด memory
        $spreadsheet = $reader->load($file);

        $sheet = $spreadsheet->getSheetByName('Sheet0');
        if (!$sheet) die("❌ ไม่พบ Sheet ชื่อ 'Sheet0'");

        $rows = $sheet->toArray();
        $total = count($rows) - 1; // ลบ header
        $current = 0;

        // reset progress
        file_put_contents($progressFile, json_encode([
            "percent" => 0,
            "current" => 0,
            "total"   => $total
        ]));

        $stmt = $pdo->prepare("INSERT IGNORE INTO tmt_drug (TPUCODE, TYPE, FSN, ActiveIngredient, Strength, dosageform, contvalue, contunit, DispUnit, TradeName, Manufacturer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $pdo->beginTransaction();
        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // ข้าม header

            $stmt->execute([
                $row[0] ?? null, $row[1] ?? null, $row[2] ?? null,
                $row[3] ?? null, $row[4] ?? null, $row[5] ?? null,
                $row[6] ?? null, $row[7] ?? null, $row[8] ?? null,
                $row[9] ?? null, $row[10] ?? null
            ]);

            $current++;

            // อัปเดต progress ทุก 50 แถว
            if ($i % 50 === 0 || $current === $total) {
                $percent = intval(($current / $total) * 100);
                file_put_contents($progressFile, json_encode([
                    "percent" => $percent,
                    "current" => $current,
                    "total"   => $total
                ]));
                $pdo->commit(); // commit every batch
                $pdo->beginTransaction();
            }
        }
        $pdo->commit();

        echo "<script>alert('✅ นำเข้าข้อมูลเสร็จสิ้นทั้งหมด {$total} แถว'); window.location='import_TMT_Drug.php';</script>";
        exit;

    } catch (Exception $e) {
        die("❌ Error reading Excel: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Import TMT_Drug</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Prompt', sans-serif; background: #f9fafb; }
</style>
</head>
<body class="p-8">

<div class="max-w-xl mx-auto bg-white rounded-2xl shadow-lg p-6">
    <h1 class="text-2xl font-bold text-center text-indigo-600 mb-6">📥 Import ข้อมูล TMT_Drug</h1>

    <form action="" method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="file" name="excelFile" accept=".xls,.xlsx"
               class="block w-full text-sm text-gray-700 border rounded-lg cursor-pointer bg-gray-50 focus:outline-none" required>
        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium shadow">
            🚀 เริ่ม Import
        </button>
    </form>

    <!-- Progress -->
    <div class="mt-6">
        <div class="w-full bg-gray-200 rounded-full h-6">
            <div id="progress-bar" class="bg-green-500 h-6 rounded-full text-white text-center text-sm leading-6" style="width:0%">0%</div>
        </div>
        <p id="status-text" class="mt-2 text-gray-700 text-center">รอเริ่ม Import...</p>
    </div>
</div>

<script>
function updateProgress() {
    fetch('import_TMT_Drug.php?progress=1&time=' + new Date().getTime())
    .then(res => res.json())
    .then(data => {
        let bar = document.getElementById("progress-bar");
        let status = document.getElementById("status-text");

        bar.style.width = data.percent + "%";
        bar.textContent = data.percent + "%";

        if (data.total > 0) {
            status.textContent = "นำเข้าแล้ว: " + data.current + "/" + data.total + " rows (" + data.percent + "%)";
        }

        if (data.percent >= 100 && data.total > 0) {
            status.textContent = "✅ เสร็จสิ้น! รวมทั้งหมด " + data.total + " rows";
            clearInterval(window.progressTimer);
            document.querySelector("button[type=submit]").disabled = false;
        }
    })
    .catch(err => console.error(err));
}

// auto update ทุก 1 วินาที
window.progressTimer = setInterval(updateProgress, 1000);
</script>

</body>
</html>
