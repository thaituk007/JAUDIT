<?php
// ตั้งค่าพื้นฐาน
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=person_export_' . date('Ymd_His') . '.csv');

// โหลด config
$config = include 'config.php';

// เชื่อมต่อ PDO
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

// ฟังก์ชันถอดรหัส (เปลี่ยน key ตามจริง)
function decryptData($encrypted) {
    $key = 'mysecretkey1234567890abcd'; // 32 ตัวอักษร สำหรับ AES-256
    $iv = substr($key, 0, 16);
    $decoded = base64_decode($encrypted);
    return openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
}

// ฟังก์ชันแปลงวันที่
function parseDate($str) {
    return (preg_match('/^\d{8}$/', $str)) ?
        substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) : null;
}
function parseDateTime($str) {
    return (preg_match('/^\d{14}$/', $str)) ?
        substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) . ' ' .
        substr($str, 8, 2) . ':' . substr($str, 10, 2) . ':' . substr($str, 12, 2) : null;
}

// สร้าง output CSV
$output = fopen('php://output', 'w');

// เขียนหัวตาราง
fputcsv($output, ['CID', 'ชื่อ', 'นามสกุล', 'วันเกิด', 'ย้ายเข้า', 'อัปเดตล่าสุด']);

// ดึงข้อมูล
$sql = "SELECT cid, name, lname, birth, movein, d_update FROM person LIMIT 1000";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        decryptData($row['cid']),
        decryptData($row['name']),
        decryptData($row['lname']),
        parseDate($row['birth']),
        parseDate($row['movein']),
        parseDateTime($row['d_update']),
    ]);
}

fclose($output);
exit;
