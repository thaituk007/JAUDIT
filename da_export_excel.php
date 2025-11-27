<?php
// da_export_excel.php
// ส่งออกเป็น CSV UTF-8 (มี BOM) เพื่อให้ Excel บน Windows อ่านภาษาไทยได้ถูกต้อง

$config = include 'config.php';

// เชื่อมต่อ PDO (ปลอดภัยกว่า)
$dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ]);
} catch (PDOException $e) {
    die("DB connect error: " . $e->getMessage());
}

// รับพารามิเตอร์ (ถ้ามี) เพื่อให้ export กรองได้
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$vill = isset($_GET['vill']) ? trim($_GET['vill']) : '';

// เตรียม SQL (ใช้ prepared statements)
$sql = "
SELECT
    IF(t.titlecode IS NULL OR titlename IS NULL OR TRIM(titlename) = '', '..', titlename) AS prename,
    p.fname, p.lname, p.birth,
    pg.daterecord AS daterecord,
    cd.drugname,
    h.hno AS hno,
    v.villno AS villno,
    v.villname AS villname,
    p.pid
FROM personalergic pg
JOIN person p ON pg.pcucodeperson = p.pcucodeperson AND pg.pid = p.pid
LEFT JOIN persondeath pd ON p.pcucodeperson = pd.pcucodeperson AND pd.pid = pg.pid
LEFT JOIN ctitle t ON p.prename = t.titlecode
JOIN cdrug cd ON pg.drugcode = cd.drugcode
JOIN house h ON p.pcucodeperson = h.pcucode AND p.hcode = h.hcode
JOIN village v ON h.pcucode = v.pcucode AND h.villcode = v.villcode
WHERE pd.pid IS NULL
  AND LEFT(v.villcode, 2) <> '00'
";

$params = [];
if ($q !== '') {
    $sql .= " AND (p.fname LIKE :q OR p.lname LIKE :q OR p.pid LIKE :q) ";
    $params[':q'] = "%{$q}%";
}
if ($vill !== '') {
    $sql .= " AND (v.villcode = :vill OR v.villno = :vill OR v.villname = :vill) ";
    $params[':vill'] = $vill;
}

$sql .= " ORDER BY pg.pid";

// Execute
$sth = $pdo->prepare($sql);
$sth->execute($params);

// ชื่อไฟล์
$filename = "drug_allergy_" . date('Ymd_His') . ".csv";

// Headers — บอกเป็น CSV และส่ง BOM เพื่อให้ Excel อ่าน UTF-8 ได้ดี
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// ส่ง BOM (EF BB BF)
echo "\xEF\xBB\xBF";

// เขียน CSV
$out = fopen('php://output', 'w');
// แถวหัว (ภาษาไทย)
fputcsv($out, ['คำนำหน้า','ชื่อ','นามสกุล','วันเกิด','วันที่บันทึก','แพ้ยา','บ้านเลขที่','หมู่ที่','หมู่บ้าน','PID']);

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        $row['prename'],
        $row['fname'],
        $row['lname'],
        $row['birth'],
        $row['daterecord'],
        $row['drugname'],
        $row['hno'],
        $row['villno'],
        $row['villname'],
        $row['pid']
    ]);
}

fclose($out);
exit;
