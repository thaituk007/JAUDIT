<?php
// กำหนด timezone และเปิด error reporting
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

// ฟังก์ชันถอดรหัส (สมมุติใช้ AES-256-CBC)
function decryptData($encrypted) {
    $key = 'mysecretkey1234567890abcd'; // ต้องยาว 32 bytes สำหรับ AES-256
    $iv = substr($key, 0, 16); // IV ยาว 16 bytes
    $decoded = base64_decode($encrypted);
    return openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
}

// ฟังก์ชันแปลงวันที่ (จาก YYYYMMDD → YYYY-MM-DD)
function parseDate($str) {
    return (preg_match('/^\d{8}$/', $str)) ?
        substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) : null;
}

// แปลง DATETIME เช่น 20250725140606 → 2025-07-25 14:06:06
function parseDateTime($str) {
    return (preg_match('/^\d{14}$/', $str)) ?
        substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) . ' ' .
        substr($str, 8, 2) . ':' . substr($str, 10, 2) . ':' . substr($str, 12, 2) : null;
}

// ดึงข้อมูลจากตาราง person
$sql = "SELECT cid, name, lname, birth, movein, d_update FROM person LIMIT 100";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แสดงข้อมูล person</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: sans-serif; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>รายชื่อบุคคล (ถอดรหัสและแปลงวันที่)</h2>
    <table>
        <thead>
            <tr>
                <th>CID</th>
                <th>ชื่อ</th>
                <th>นามสกุล</th>
                <th>วันเกิด</th>
                <th>ย้ายเข้า</th>
                <th>อัปเดตล่าสุด</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars(decryptData($row['cid'])) ?></td>
                <td><?= htmlspecialchars(decryptData($row['name'])) ?></td>
                <td><?= htmlspecialchars(decryptData($row['lname'])) ?></td>
                <td><?= parseDate($row['birth']) ?></td>
                <td><?= parseDate($row['movein']) ?></td>
                <td><?= parseDateTime($row['d_update']) ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</body>
</html>
