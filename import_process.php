<?php
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json');

require 'vendor/autoload.php';
$config = require 'config.php';

try {
    $pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8", $config['db_user'], $config['db_pass']);
    $pdo->exec("SET NAMES utf8");
} catch (PDOException $e) {
    echo json_encode(['error' => 'เชื่อมต่อฐานข้อมูลล้มเหลว']);
    exit;
}

function convertDate($birth) {
    if (strlen($birth) === 8) {
        return substr($birth,0,4).'-'.substr($birth,4,2).'-'.substr($birth,6,2);
    }
    return null;
}

$batchSize = 100;

// ไฟล์ชั่วคราวที่เก็บหลังอัปโหลด
$tempDir = sys_get_temp_dir();
$fileName = $_FILES['person_file']['name'] ?? null;
$tmpFile = $tempDir . '/' . ($fileName ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName) : '');

// offset ที่จะอ่าน (จำนวนบรรทัดที่อ่านไปแล้ว)
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

if ($offset === 0) {
    // รอบแรก ให้ย้ายไฟล์อัปโหลดไปเก็บ temp folder
    if (!isset($_FILES['person_file'])) {
        echo json_encode(['error'=>'ไม่พบไฟล์อัปโหลด']);
        exit;
    }
    if (!move_uploaded_file($_FILES['person_file']['tmp_name'], $tmpFile)) {
        echo json_encode(['error'=>'ไม่สามารถบันทึกไฟล์ชั่วคราว']);
        exit;
    }
} else {
    if (!file_exists($tmpFile)) {
        echo json_encode(['error'=>'ไฟล์ชั่วคราวไม่พบ']);
        exit;
    }
}

$handle = fopen($tmpFile, 'r');
if (!$handle) {
    echo json_encode(['error'=>'ไม่สามารถเปิดไฟล์ชั่วคราว']);
    exit;
}

// อ่าน header
$header = fgetcsv($handle, 10000, '|');
if ($header === false) {
    echo json_encode(['error'=>'ไฟล์ไม่มีข้อมูล header']);
    fclose($handle);
    exit;
}

// ข้ามบรรทัดก่อนหน้าตาม offset
for ($i=0; $i < $offset; $i++) {
    if (fgets($handle) === false) break;
}

$inserted = 0;
$skipped = 0;
$linesRead = 0;

while ($linesRead < $batchSize) {
    $line = fgets($handle);
    if ($line === false) break;

    $line = trim($line);
    if ($line === '') continue;

    $data = str_getcsv($line, '|');
    if (count($data) < count($header)) continue;

    $rowData = array_combine($header, $data);
    $rowData['birth'] = convertDate($rowData['birth']);
    $cid = $rowData['cid'];

    // ตรวจสอบซ้ำ
    $stmt = $pdo->prepare("SELECT 1 FROM person_data WHERE cid = ?");
    $stmt->execute([$cid]);
    if ($stmt->fetchColumn()) {
        $skipped++;
    } else {
        $fields = array_keys($rowData);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO person_data (" . implode(',', $fields) . ") VALUES ($placeholders)";
        $insertStmt = $pdo->prepare($sql);
        $insertStmt->execute(array_values($rowData));
        $inserted++;
    }
    $linesRead++;
}
fclose($handle);

$totalLines = count(file($tmpFile)) - 1; // ลบ header ออก
$nextOffset = $offset + $linesRead;
$done = $nextOffset >= $totalLines;

if ($done) {
    unlink($tmpFile);
}

echo json_encode([
    'inserted' => $inserted,
    'skipped' => $skipped,
    'progress' => min(1, $nextOffset / $totalLines),
    'next_offset' => $nextOffset,
    'done' => $done
]);
