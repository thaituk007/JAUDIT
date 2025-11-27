<?php
$savePath = sys_get_temp_dir();

// ตรวจสอบว่าโฟลเดอร์นี้มีจริง และเขียนได้หรือไม่
if (!is_dir($savePath) || !is_writable($savePath)) {
    // fallback ไปที่โฟลเดอร์ที่เราสร้างเอง
    $savePath = "C:/AppServ/tmp";
    if (!is_dir($savePath)) {
        mkdir($savePath, 0777, true);
    }
}

session_save_path($savePath);
session_start();

echo "Session path: " . session_save_path();
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

date_default_timezone_set('Asia/Bangkok');
$config = require 'config.php';
$username = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : 'User';
$today = date('Y-m-d');

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}

// ดึงรายชื่อหมู่บ้านพร้อมเลขหมู่ที่ที่มีข้อมูล (ไม่ซ้ำ)
$sql_villages = "
SELECT DISTINCT village.villcode, village.villno, village.villname
FROM village
INNER JOIN house ON house.villcode = village.villcode
INNER JOIN person ON person.pcucodeperson = house.pcucodeperson AND person.hcode = house.hcode
WHERE village.villno <> '00'
ORDER BY village.villno, village.villname
";
$villages = $pdo->query($sql_villages)->fetchAll(PDO::FETCH_ASSOC);

// รับค่า villcode ที่เลือกจาก URL
$selected_villcode = isset($_GET['villcode']) ? $_GET['villcode'] : '';

// สร้างเงื่อนไข filter ถ้าเลือกหมู่บ้าน
$filter_condition = '';
$params = [];
if ($selected_villcode !== '' && $selected_villcode !== 'all') {
    $filter_condition = " AND village.villcode = :villcode ";
    $params[':villcode'] = $selected_villcode;
}

// กำหนดตัวแปรใน SQL (ถ้า MySQL รองรับ)
$pdo->exec("SET @getagedate = CONCAT(YEAR(CURDATE())-1,'10','01')");
$pdo->exec("SET @now = CURDATE()");
$pdo->exec("SET @a1 = DATE_ADD(DATE_FORMAT(@now, '%Y-%m-01'), INTERVAL -1 YEAR)");
$pdo->exec("SET @a2 = LAST_DAY(DATE_ADD(@now, INTERVAL -1 YEAR))");

// ดึงข้อมูลรายชื่อผู้สูงอายุพร้อม ADL ตามเงื่อนไขหมู่บ้าน (หรือทั้งหมดถ้าไม่เลือก)
$sql_adl = "
SELECT
    person.pid AS 'PID',
    person.idcard AS 'เลขบัตรประชาชน',
    CONCAT(ctitle.titlename, person.fname, ' ', person.lname) AS 'ชื่อ - นามสกุล',
    CONCAT(SUBSTR(person.birth,9,2),'-',SUBSTR(person.birth,6,2),'-',SUBSTR(person.birth,1,4)+543) AS 'วันเกิด',
    getAgeYearNum(person.birth,@getagedate) AS 'อายุ',
    house.hno AS 'บ้านเลขที่',
    village.villno AS 'หมู่ที่',
    village.villname AS 'หมู่บ้าน',
    MAX(f43specialpp.dateserv) AS 'วันที่ SPECIALPP ล่าสุด',
    f43specialpp.ppspecial AS 'รหัส SPECIALPP',
    CASE f43specialpp.ppspecial
        WHEN '1B1281' THEN 'ติดบ้าน'
        WHEN '1B1282' THEN 'ติดเตียง'
        WHEN '1B1280' THEN 'ติดสังคม'
        ELSE 'ไม่ระบุ'
    END AS 'สถานะ ADL'
FROM person
LEFT JOIN ctitle ON person.prename = ctitle.titlecode
INNER JOIN house ON person.pcucodeperson = house.pcucodeperson AND person.hcode = house.hcode
INNER JOIN village ON house.villcode = village.villcode AND village.villno <> '00'
LEFT JOIN f43specialpp ON f43specialpp.pcucodeperson = person.pcucodeperson AND f43specialpp.pid = person.pid
WHERE getAgeYearNum(person.birth,CURDATE()) >= 60
    AND person.nation = 99
    AND f43specialpp.ppspecial IN ('1B1280','1B1281','1B1282')
    AND f43specialpp.dateserv BETWEEN CONCAT(YEAR(CURDATE())-2,'10','01') AND CONCAT(YEAR(CURDATE())-1,'09','30')
    AND person.pid NOT IN (SELECT pid FROM persondeath)
    $filter_condition
GROUP BY person.pid
ORDER BY village.villno, house.hno
";

$stmt = $pdo->prepare($sql_adl);
$stmt->execute($params);
$data_adl = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$data_adl) {
    $data_adl = [];
}

// หา ชื่อหมู่บ้านและเลขหมู่ที่ที่เลือก (ถ้าเลือก)
$selected_village_name = '';
$selected_villno = '';
if ($selected_villcode !== '' && $selected_villcode !== 'all') {
    foreach ($villages as $v) {
        if ($v['villcode'] === $selected_villcode) {
            $selected_village_name = $v['villname'];
            $selected_villno = $v['villno'];
            break;
        }
    }
}

// ส่งออก Excel
if (isset($_GET['export']) && $_GET['export'] == 'xls') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $columns_order = [
        'PID',
        'เลขบัตรประชาชน',
        'ชื่อ - นามสกุล',
        'วันเกิด',
        'อายุ',
        'บ้านเลขที่',
        'หมู่ที่',
        'หมู่บ้าน',
        'วันที่ SPECIALPP ล่าสุด',
        'รหัส SPECIALPP',
        'สถานะ ADL',
    ];

    $col = 'A';
    foreach ($columns_order as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    $row = 2;
    foreach ($data_adl as $rowData) {
        $line = [];
        foreach ($columns_order as $colName) {
            $line[] = isset($rowData[$colName]) ? $rowData[$colName] : '';
        }
        $sheet->fromArray($line, null, 'A' . $row);
        $row++;
    }

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="elderly_adl_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    $writer = new Xls($spreadsheet);
    $writer->save('php://output');
    exit;
}

$columns_order = [
    'PID',
    'เลขบัตรประชาชน',
    'ชื่อ - นามสกุล',
    'วันเกิด',
    'อายุ',
    'บ้านเลขที่',
    'หมู่ที่',
    'หมู่บ้าน',
    'วันที่ SPECIALPP ล่าสุด',
    'รหัส SPECIALPP',
    'สถานะ ADL',
];

?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>รายชื่อผู้สูงอายุพร้อม ADL</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Prompt', sans-serif;
      background: #f0f0f0;
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 20px;
      font-weight: 400;
      padding-top: 50px; /* เว้นที่บนให้ footer ไม่บังเนื้อหา */
    }
    .header {
      width: 100%;
      max-width: 1200px;
      display: flex;
      justify-content: flex-start;
      margin-bottom: 10px;
    }
    .btn-home {
      background: #2980b9;
      color: white;
      padding: 8px 14px;
      text-decoration: none;
      border-radius: 5px;
      font-weight: 600;
      font-size: 14px;
      display: inline-block;
      user-select: none;
      transition: background-color 0.3s ease;
    }
    .btn-home:hover {
      background: #1c5980;
    }
    h2 {
      color: #2c3e50;
      font-weight: 600;
      margin-bottom: 15px;
      text-align: center;
      width: 100%;
      max-width: 1200px;
    }
    .count-info {
      margin-bottom: 15px;
      font-size: 16px;
      font-weight: 600;
      color: #34495e;
      width: 100%;
      max-width: 1200px;
      text-align: center;
    }
    .btn {
      background: #27ae60;
      color: white;
      padding: 10px 16px;
      text-decoration: none;
      border-radius: 5px;
      margin-bottom: 10px;
      font-weight: 600;
      display: inline-block;
    }
    form#filterForm {
      margin-bottom: 15px;
      width: 100%;
      max-width: 1200px;
      text-align: center;
    }
    select#villcode {
      padding: 6px 12px;
      font-size: 14px;
      border-radius: 5px;
      border: 1px solid #ccc;
      width: 300px;
      font-family: 'Prompt', sans-serif;
    }
    table {
      width: 100%;
      max-width: 1200px;
      border-collapse: collapse;
      background: white;
      margin: 0 auto;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px;
      font-size: 13px;
      font-weight: 300;
      text-align: center;
      vertical-align: middle;
    }
    th {
      background-color: #3498db;
      color: white;
      font-weight: 600;
    }
    tr:nth-child(even) {
      background: #f9f9f9;
    }

    footer.fixed-top-right {
      position: fixed;
      top: 0;
      right: 0;
      left: auto;
      min-width: 300px;
      max-width: 70vw;
      background: #1e1e1e;
      color: #bbb;
      font-size: 0.8rem;
      padding: 0.5rem 1rem;
      z-index: 9999;
      border-bottom-left-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.4);
      user-select: none;
      font-family: 'Prompt', sans-serif;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  </style>
</head>
<body>
  <div class="header">
    <a href="index.php" class="btn-home">🏠 Home</a>
  </div>

  <h2>รายชื่อผู้สูงอายุพร้อม ADL</h2>

  <form id="filterForm" method="get" action="">
    <label for="villcode">เลือกหมู่บ้าน (หมู่ที่ - ชื่อหมู่บ้าน):</label>
    <select id="villcode" name="villcode" onchange="document.getElementById('filterForm').submit();">
      <option value="all" <?= $selected_villcode === 'all' || $selected_villcode === '' ? 'selected' : '' ?>>ทั้งหมด</option>
      <?php foreach ($villages as $village): ?>
        <option value="<?= htmlspecialchars($village['villcode']) ?>" <?= $selected_villcode === $village['villcode'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($village['villno'] . ' - ' . $village['villname']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?export=xls' . ($selected_villcode !== '' ? '&villcode=' . urlencode($selected_villcode) : '')) ?>" class="btn">📥 ส่งออก Excel</a>

  <div class="count-info">
    จำนวนรายชื่อผู้สูงอายุพร้อม ADL
    <?= ($selected_villcode !== '' && $selected_villcode !== 'all')
          ? ' หมู่ที่ ' . htmlspecialchars($selected_villno) . ' หมู่บ้าน "' . htmlspecialchars($selected_village_name) . '"'
          : '' ?>:
    <strong><?= count($data_adl) ?></strong> ราย
  </div>

  <table>
    <thead>
      <tr>
        <?php foreach ($columns_order as $colName): ?>
          <th><?= htmlspecialchars($colName) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (count($data_adl) === 0): ?>
        <tr><td colspan="<?= count($columns_order) ?>" style="text-align:center;">ไม่พบข้อมูลตามเงื่อนไข</td></tr>
      <?php else: ?>
        <?php foreach ($data_adl as $row): ?>
          <tr>
            <?php foreach ($columns_order as $colName): ?>
              <?php
                $val = isset($row[$colName]) ? $row[$colName] : '';
                echo '<td>' . htmlspecialchars($val) . '</td>';
              ?>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

<?php include 'footer.php'; ?>

</body>
</html>
