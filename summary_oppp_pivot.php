<?php
// summary_oppp_pivot.php

$config = include 'config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$uploadError = '';
$uploadSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $fileName = $_FILES['excel_file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['xls', 'xlsx'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        $uploadError = 'ไฟล์ต้องเป็น .xls หรือ .xlsx เท่านั้น';
    } else {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // ลบข้อมูลเก่า
            $pdo->exec("DELETE FROM oppp_summary");

            $insertSql = "INSERT INTO oppp_summary (hospcode, hospname, report_month, qty, upload_date)
                          VALUES (:hospcode, :hospname, :report_month, :qty, NOW())";
            $stmtInsert = $pdo->prepare($insertSql);

            $countInserted = 0;

            for ($i = 4; $i < count($rows); $i++) {
                $row = $rows[$i];
                $hospcode = trim($row[0]);
                $hospname = trim($row[1]);
                $report_month = trim($row[2]);
                $qty = (int)$row[3];

                if ($hospcode && $report_month) {
                    $stmtInsert->execute([
                        ':hospcode' => $hospcode,
                        ':hospname' => $hospname,
                        ':report_month' => $report_month,
                        ':qty' => $qty,
                    ]);
                    $countInserted++;
                }
            }
            $uploadSuccess = "นำเข้าไฟล์สำเร็จ จำนวน $countInserted แถว";
        } catch (Exception $e) {
            $uploadError = 'เกิดข้อผิดพลาดในการอ่านไฟล์ Excel: ' . $e->getMessage();
        }
    }
}

// ตัวแปรค้นหา
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// ตัวแปร pagination
$limit = 20; // แถวต่อหน้า
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// นับจำนวนแถวทั้งหมด (ตาม filter)
$countSql = "SELECT COUNT(DISTINCT hospcode, hospname, report_month) AS total FROM oppp_summary";
$countParams = [];
if ($search !== '') {
    $countSql .= " WHERE hospcode LIKE :search OR hospname LIKE :search OR report_month LIKE :search";
    $countParams[':search'] = "%$search%";
}
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($countParams);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query ข้อมูลพร้อม filter และ pagination
$sql = "SELECT hospcode, hospname, report_month, SUM(qty) AS total_qty
        FROM oppp_summary";
$params = [];

if ($search !== '') {
    $sql .= " WHERE hospcode LIKE :search OR hospname LIKE :search OR report_month LIKE :search";
    $params[':search'] = "%$search%";
}

$sql .= " GROUP BY hospcode, hospname, report_month
          ORDER BY report_month DESC, hospcode ASC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// bind param ต้อง bind limit และ offset เป็น integer
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$results = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>สรุปรายงาน OPPP Summary & Upload Excel</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet" />

<style>
  body {
    font-family: 'Prompt', sans-serif;
    background-color: #f5f7fa;
    margin: 0; padding: 20px;
  }
  .container {
    max-width: 1000px;
    margin: 30px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    padding: 25px 40px;
    text-align: center;
  }
  h1 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
  }
  .datetime {
    font-weight: 300;
    font-size: 0.95rem;
    color: #666;
    margin-bottom: 25px;
  }
  form.upload-form {
    margin-bottom: 25px;
    text-align: left;
  }
  label {
    font-weight: 500;
  }
  input[type="file"] {
    margin-top: 8px;
    margin-bottom: 12px;
  }
  input[type="submit"] {
    background-color: #4CAF50;
    border: none;
    color: white;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background-color 0.3s ease;
  }
  input[type="submit"]:hover {
    background-color: #45a049;
  }
  .message {
    font-weight: 500;
    margin-bottom: 20px;
  }
  .error {
    color: #d9534f;
  }
  .success {
    color: #5cb85c;
  }
  .search-box {
    margin-bottom: 15px;
    text-align: right;
  }
  .search-box input[type="text"] {
    padding: 7px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1rem;
  }
  .search-box button {
    padding: 7px 14px;
    border-radius: 6px;
    border: none;
    background-color: #4CAF50;
    color: white;
    font-weight: 600;
    cursor: pointer;
    margin-left: 6px;
  }
  .search-box button:hover {
    background-color: #45a049;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
  }
  th, td {
    border: 1px solid #ddd;
    padding: 8px 12px;
    text-align: center;
    font-weight: 400;
    color: #444;
  }
  th {
    background-color: #4CAF50;
    color: white;
  }
  tr:nth-child(even) {
    background-color: #f9f9f9;
  }
  .pagination {
    margin-top: 20px;
    text-align: center;
  }
  .pagination a, .pagination span {
    display: inline-block;
    margin: 0 6px;
    padding: 6px 12px;
    text-decoration: none;
    color: #4CAF50;
    border: 1px solid #4CAF50;
    border-radius: 4px;
    font-weight: 600;
  }
  .pagination a:hover {
    background-color: #4CAF50;
    color: white;
  }
  .pagination .current-page {
    background-color: #4CAF50;
    color: white;
    pointer-events: none;
  }
</style>
</head>
<body>

<div class="container">
  <h1>สรุปรายงาน OPPP Summary & Upload Excel</h1>
  <div class="datetime" id="datetime"></div>

  <form class="upload-form" method="post" enctype="multipart/form-data">
    <label for="excel_file">เลือกไฟล์ Excel (.xls หรือ .xlsx):</label><br />
    <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx" required><br />
    <input type="submit" value="อัปโหลดและนำเข้า">
  </form>

  <?php if ($uploadError): ?>
    <div class="message error"><?php echo htmlspecialchars($uploadError); ?></div>
  <?php endif; ?>

  <?php if ($uploadSuccess): ?>
    <div class="message success"><?php echo htmlspecialchars($uploadSuccess); ?></div>
  <?php endif; ?>

  <div class="search-box">
    <form method="get" action="">
      <input type="text" name="search" placeholder="ค้นหา Hospcode, Hospname หรือเดือน" value="<?php echo htmlspecialchars($search); ?>" />
      <button type="submit">ค้นหา</button>
    </form>
  </div>

  <?php if (count($results) > 0): ?>
  <table>
    <thead>
      <tr>
        <th>รหัสหน่วยบริการ (Hospcode)</th>
        <th>ชื่อหน่วยบริการ (Hospname)</th>
        <th>เดือนรายงาน</th>
        <th>รวมจำนวน (Qty)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $row): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['hospcode']); ?></td>
        <td><?php echo htmlspecialchars($row['hospname']); ?></td>
        <td><?php echo htmlspecialchars($row['report_month']); ?></td>
        <td><?php echo number_format($row['total_qty']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?search=<?php echo urlencode($search); ?>&page=1">&laquo; หน้าแรก</a>
      <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page -1; ?>">&lt; ก่อนหน้า</a>
    <?php endif; ?>

    <?php
    // แสดงเลขหน้าไม่เกิน 5 หน้า (แบบย่อ)
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    for ($p = $startPage; $p <= $endPage; $p++):
    ?>
      <?php if ($p == $page): ?>
        <span class="current-page"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page +1; ?>">ถัดไป &gt;</a>
      <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $totalPages; ?>">หน้าสุดท้าย &raquo;</a>
    <?php endif; ?>
  </div>

  <?php else: ?>
    <p>ไม่พบข้อมูลที่ตรงกับการค้นหา</p>
  <?php endif; ?>
</div>

<script>
function showDateTime() {
  const now = new Date();
  const options = {
    year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: false,
    timeZone: 'Asia/Bangkok'
  };
  const dateTimeString = now.toLocaleString('th-TH', options);
  document.getElementById('datetime').textContent = dateTimeString;
}
showDateTime();
setInterval(showDateTime, 1000);
</script>

</body>
</html>
