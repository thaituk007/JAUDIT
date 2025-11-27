<?php
$config = include __DIR__ . '/config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage());
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// กรอง search เป็นเลข 5 หลัก หรือว่าง
if ($search !== '' && !preg_match('/^\d{5}$/', $search)) {
    die('กรุณากรอกรหัสหน่วยบริการ 5 หลัก เช่น 12345');
}

$sql = "
SELECT
    person_data.hospcode,
    chospital.hosname,
    COUNT(cid) AS total,
    SUM(CASE WHEN typearea='1' THEN 1 ELSE 0 END) AS ta1,
    SUM(CASE WHEN typearea='2' THEN 1 ELSE 0 END) AS ta2,
    SUM(CASE WHEN typearea='3' THEN 1 ELSE 0 END) AS ta3,
    SUM(CASE WHEN typearea='4' THEN 1 ELSE 0 END) AS ta4,
    SUM(CASE WHEN typearea='5' THEN 1 ELSE 0 END) AS ta5
FROM person_data
LEFT JOIN chospital ON person_data.hospcode = chospital.hoscode
";

if ($search !== '') {
    $sql .= " WHERE person_data.hospcode LIKE :search ";
}

$sql .= "
GROUP BY person_data.hospcode, chospital.hosname
ORDER BY person_data.hospcode ASC
";

$stmt = $pdo->prepare($sql);
if ($search !== '') {
    $stmt->execute(['search' => $search . '%']);
} else {
    $stmt->execute();
}

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// กำหนดชื่อไฟล์ CSV
$filename = 'report_typearea.csv';

// ส่ง header ให้เบราว์เซอร์ดาวน์โหลดไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// เปิด output stream เพื่อเขียนข้อมูล CSV
$output = fopen('php://output', 'w');

// เขียนหัวตาราง (header)
fputcsv($output, ['รหัสหน่วยบริการ', 'ชื่อหน่วยบริการ', 'รวม', 'TypeArea 1', 'TypeArea 2', 'TypeArea 3', 'TypeArea 4', 'TypeArea 5']);

// เขียนข้อมูลแถวละรายการ
foreach ($data as $row) {
    fputcsv($output, [
        $row['hospcode'],
        $row['hosname'],
        $row['total'],
        $row['ta1'],
        $row['ta2'],
        $row['ta3'],
        $row['ta4'],
        $row['ta5'],
    ]);
}

fclose($output);
exit;
