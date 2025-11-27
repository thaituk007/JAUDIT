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
$config = include 'config.php';
date_default_timezone_set('Asia/Bangkok');

// PDO
$dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    $pdo->exec("SET NAMES utf8mb4");
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ฟังก์ชันแปลงวัน/เดือน/ปีไทย
function thai_date_full($date){
    if(!$date) return "";
    $thai_month_arr = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.",
                       "ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    $d = date_parse($date);
    if(!$d['year'] || !$d['month'] || !$d['day']) return "";
    $month = (int)$d['month'];
    $year = $d['year'] + 543;
    return $d['day']." ".$thai_month_arr[$month]." ".$year;
}

// ฟังก์ชันแปลง ppspecial เป็นข้อความผลลัพธ์
function ppspecial_result($ppspecial){
    switch($ppspecial){
        case '1B200': return ['9'=>'สงสัยล่าช้า ส่งเสริมพัฒนาการใน 1 เดือน','18'=>'ผ่าน','30'=>'ผ่าน','42'=>'ผ่าน','60'=>'ผ่าน'];
        case '1B202': return ['9'=>'ล่าช้า ส่งเพื่อประเมิน/รักษาต่อ','18'=>'ผ่าน','30'=>'ผ่าน','42'=>'ผ่าน','60'=>'ผ่าน'];
        case '1B212': return ['9'=>'ปกติ','18'=>'ปกติ','30'=>'ปกติ','42'=>'ปกติ','60'=>'ปกติ'];
        case '1B222': return ['9'=>'สงสัยล่าช้า ส่งเสริมพัฒนาการใน 1 เดือน','18'=>'ผ่าน','30'=>'ผ่าน','42'=>'ผ่าน','60'=>'ผ่าน'];
        case '1B232': return ['9'=>'ล่าช้า ส่งเพื่อประเมิน/รักษาต่อ','18'=>'ผ่าน','30'=>'ผ่าน','42'=>'ผ่าน','60'=>'ผ่าน'];
        case '1B242': return ['9'=>'ปกติ','18'=>'ปกติ','30'=>'ปกติ','42'=>'ปกติ','60'=>'ปกติ'];
        default: return ['9'=>'ไม่ระบุ','18'=>'ไม่ระบุ','30'=>'ไม่ระบุ','42'=>'ไม่ระบุ','60'=>'ไม่ระบุ'];
    }
}

// SQL Query
$sql = "
SELECT DISTINCT
    f43specialpp.pid,
    f43specialpp.ppspecial,
    CONCAT_WS('-',
        SUBSTRING(person.idcard,1,1),
        SUBSTRING(person.idcard,2,4),
        SUBSTRING(person.idcard,6,5),
        SUBSTRING(person.idcard,11,2),
        SUBSTRING(person.idcard,13,1)
    ) AS idcard,
    CONCAT(ctitle.titlename, person.fname, ' ', person.lname) AS fullname,
    person.birth AS birth_en,
    TIMESTAMPDIFF(MONTH, person.birth, CURDATE()) AS age_month,
    CONCAT(\"'\", house.hno) AS house_no,
    CONCAT('หมู่ที่ ', village.villno, ' ', village.villname) AS village,
    DATE_ADD(person.birth, INTERVAL 9 MONTH) AS month_9_start,
    DATE_ADD(DATE_ADD(person.birth, INTERVAL 9 MONTH), INTERVAL 30 DAY) AS month_9_end,
    DATE_ADD(person.birth, INTERVAL 18 MONTH) AS month_18_start,
    DATE_ADD(DATE_ADD(person.birth, INTERVAL 18 MONTH), INTERVAL 30 DAY) AS month_18_end,
    DATE_ADD(person.birth, INTERVAL 30 MONTH) AS month_30_start,
    DATE_ADD(DATE_ADD(person.birth, INTERVAL 30 MONTH), INTERVAL 30 DAY) AS month_30_end,
    DATE_ADD(person.birth, INTERVAL 42 MONTH) AS month_42_start,
    DATE_ADD(DATE_ADD(person.birth, INTERVAL 42 MONTH), INTERVAL 30 DAY) AS month_42_end,
    DATE_ADD(person.birth, INTERVAL 60 MONTH) AS month_60_start,
    DATE_ADD(DATE_ADD(person.birth, INTERVAL 60 MONTH), INTERVAL 30 DAY) AS month_60_end
FROM f43specialpp
LEFT JOIN person
       ON person.pcucodeperson = f43specialpp.pcucodeperson
      AND person.pid = f43specialpp.pid
LEFT JOIN house
       ON person.hcode = house.hcode
      AND house.pcucode = person.pcucodeperson
LEFT JOIN persondeath
       ON person.pcucodeperson = persondeath.pcucodeperson
      AND person.pid = persondeath.pid
LEFT JOIN ctitle
       ON person.prename = ctitle.titlecode
LEFT JOIN village
       ON house.villcode = village.villcode
      AND village.pcucode = house.pcucode
WHERE person.typelive IN ('1','3')
  AND TIMESTAMPDIFF(MONTH, person.birth, LAST_DAY(CURDATE())) BETWEEN 0 AND 61
  AND persondeath.pid IS NULL
GROUP BY person.pid
ORDER BY village.villcode, person.birth ASC
";

$stmt = $pdo->query($sql);
$results = $stmt->fetchAll();
$filename_excel = "DSPMReport_" . date('m-Y');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DSPM Report Year</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<style>
body { font-family: 'Prompt', sans-serif; background:#fffceb; padding:20px; color:#555; }
h1 { text-align:center; background:#ffd966; color:#444; padding:15px; border-radius:12px; font-weight:600; box-shadow:0 4px 10px rgba(0,0,0,0.08); margin-bottom:20px; }
.table-container { overflow-x:auto; border-radius:12px; background:#ffffff; padding:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:15px; }
th, td { white-space: nowrap; padding:12px; text-align:center; font-size:14px; border-bottom:1px solid #ffe6b3; transition: all 0.3s ease; }
th { background:#ffb84d; color:#333; font-weight:600; }
tr { background:#fff6e6; transition: transform 0.2s, box-shadow 0.2s, background 0.2s; }
tr:hover { transform:translateY(-2px); box-shadow:0 4px 10px rgba(0,0,0,0.08); background:#ffe0b3; }
tr:hover td { background:transparent; }
.dt-button.buttons-excel { background: #4fc3f7; color: white !important; font-weight: 500; border: none; border-radius: 6px; padding: 6px 18px; cursor: pointer; }
.dt-button.buttons-excel:hover { background: #29b6f6; }

/* ปุ่มกลับหน้าแรก */
.btn-home { background: #ffb84d; color: #fff; text-decoration: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; transition: all 0.3s ease; display:inline-block; margin-bottom:15px; }
.btn-home:hover { background: #ffa500; box-shadow: 0 4px 8px rgba(0,0,0,0.2); transform: translateY(-2px); }

@media(max-width:768px){
    table, thead, tbody, th, td, tr {display:block;}
    thead tr {display:none;}
    tr {margin-bottom:15px;}
    td {text-align:right; padding-left:50%; position:relative; background:#fff6e6; margin-bottom:5px; border-radius:6px;}
    td::before {content: attr(data-label); position:absolute; left:15px; font-weight:500; text-align:left;}
}
</style>
</head>
<body>
<h1>เป้าหมายพัฒนาการเด็ก อายุ 9,18,30,42,60 เดือน : DSPM Report</h1>

<!-- ปุ่มกลับหน้าแรก -->
<div style="text-align:center;">
    <a href="index.php" class="btn-home">🏠 กลับหน้าแรก</a>
</div>

<div class="table-container">
<table id="reportTable" class="display nowrap" style="width:100%">
<thead>
<tr>
<th>PID</th>
<th>ID Card</th>
<th>ชื่อ-นามสกุล</th>
<th>วันเกิด</th>
<th>อายุ(เดือน)</th>
<th>บ้านเลขที่</th>
<th>หมู่บ้าน</th>
<th>9 เดือน</th>
<th>ผล 9 เดือน</th>
<th>18 เดือน</th>
<th>ผล 18 เดือน</th>
<th>30 เดือน</th>
<th>ผล 30 เดือน</th>
<th>42 เดือน</th>
<th>ผล 42 เดือน</th>
<th>60 เดือน</th>
<th>ผล 60 เดือน</th>
</tr>
</thead>
<tbody>
<?php foreach($results as $row):
    $birth_th = thai_date_full($row['birth_en']);
    $month_9 = thai_date_full($row['month_9_start'])." ถึง ".thai_date_full($row['month_9_end']);
    $month_18 = thai_date_full($row['month_18_start'])." ถึง ".thai_date_full($row['month_18_end']);
    $month_30 = thai_date_full($row['month_30_start'])." ถึง ".thai_date_full($row['month_30_end']);
    $month_42 = thai_date_full($row['month_42_start'])." ถึง ".thai_date_full($row['month_42_end']);
    $month_60 = thai_date_full($row['month_60_start'])." ถึง ".thai_date_full($row['month_60_end']);
    $result = ppspecial_result($row['ppspecial']);
?>
<tr>
<td data-label="PID"><?= $row['pid'] ?></td>
<td data-label="ID Card"><?= $row['idcard'] ?></td>
<td data-label="ชื่อ-นามสกุล"><?= $row['fullname'] ?></td>
<td data-label="วันเกิด"><?= $birth_th ?></td>
<td data-label="อายุ(เดือน)"><?= $row['age_month'] ?></td>
<td data-label="บ้านเลขที่"><?= $row['house_no'] ?></td>
<td data-label="หมู่บ้าน"><?= $row['village'] ?></td>
<td data-label="9 เดือน"><?= $month_9 ?></td>
<td data-label="ผล 9 เดือน"><?= $result['9'] ?></td>
<td data-label="18 เดือน"><?= $month_18 ?></td>
<td data-label="ผล 18 เดือน"><?= $result['18'] ?></td>
<td data-label="30 เดือน"><?= $month_30 ?></td>
<td data-label="ผล 30 เดือน"><?= $result['30'] ?></td>
<td data-label="42 เดือน"><?= $month_42 ?></td>
<td data-label="ผล 42 เดือน"><?= $result['42'] ?></td>
<td data-label="60 เดือน"><?= $month_60 ?></td>
<td data-label="ผล 60 เดือน"><?= $result['60'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<script>
$(document).ready(function() {
    $('#reportTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: '<?= $filename_excel ?>',
                text: 'Export Excel',
                className: 'buttons-excel',
                exportOptions: { columns: ':visible', format: { body: function (data) { return data.replace(/<.*?>/g, ''); } } }
            }
        ],
        lengthMenu: [ [10, 20, 50, 100], [10, 20, 50, 100] ],
        scrollX: true,
        paging: true,
        responsive: true,
        fixedHeader: true
    });
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
