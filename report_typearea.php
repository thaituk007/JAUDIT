<?php
session_start();
$config = include __DIR__ . '/config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage());
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ถ้าป้อนค่า ค้นหา ต้องเป็นเลข 5 หลัก หรือว่าง
if ($search !== '' && !preg_match('/^\d{5}$/', $search)) {
    die('กรุณากรอกรหัสหน่วยบริการให้ถูกต้อง 5 หลัก เช่น 12345');
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

// คำนวณรวม TypeArea ทั้งหมด สำหรับกราฟ Pie Chart
$total_ta1 = 0;
$total_ta2 = 0;
$total_ta3 = 0;
$total_ta4 = 0;
$total_ta5 = 0;
foreach ($data as $row) {
    $total_ta1 += $row['ta1'];
    $total_ta2 += $row['ta2'];
    $total_ta3 += $row['ta3'];
    $total_ta4 += $row['ta4'];
    $total_ta5 += $row['ta5'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>รายงาน TypeArea ตามหน่วยบริการ</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background: #f9f9f9;
    margin: 20px;
  }
  .container {
    max-width: 1100px;
    margin: auto;
    background: #fff;
    border-radius: 14px;
    padding: 25px 30px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    position: relative;
  }
  h1 {
    text-align: center;
    margin-bottom: 30px;
    font-weight: 600;
    color: #333;
  }
  /* ปุ่มกลับหน้าแรก */
  .btn-home {
    position: absolute;
    top: 20px;
    left: 30px;
    background-color: #339af0;
    color: white;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-family: 'Prompt', sans-serif;
    transition: background-color 0.3s ease;
  }
  .btn-home:hover {
    background-color: #1c7ed6;
  }
  form {
    margin-bottom: 15px;
    text-align: center;
  }
  input[type="text"] {
    width: 320px;
    padding: 10px 14px;
    font-size: 16px;
    border-radius: 8px;
    border: 1.5px solid #ccc;
    outline: none;
    transition: border-color 0.3s ease;
    font-family: 'Prompt', sans-serif;
  }
  input[type="text"]:focus {
    border-color: #339af0;
  }
  button {
    padding: 10px 18px;
    font-size: 16px;
    border-radius: 8px;
    border: none;
    background-color: #339af0;
    color: #fff;
    cursor: pointer;
    margin-left: 10px;
    font-family: 'Prompt', sans-serif;
    transition: background-color 0.3s ease;
  }
  button:hover {
    background-color: #1c7ed6;
  }
  .export-btns {
    text-align: right;
    margin-bottom: 15px;
  }
  .export-btns a {
    display: inline-block;
    margin-left: 10px;
    padding: 8px 14px;
    background-color: #38d9a9;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-family: 'Prompt', sans-serif;
    transition: background-color 0.3s ease;
  }
  .export-btns a:hover {
    background-color: #20c997;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  table thead tr {
    background: #e7f5ff;
  }
  th, td {
    padding: 10px 12px;
    border: 1px solid #ddd;
    text-align: center;
  }
  tbody tr:hover {
    background-color: #f1f3f5;
  }
  tbody td:first-child,
  tbody td:nth-child(2) {
    text-align: left;
  }
  canvas {
    margin-top: 40px;
    display: block;
    margin-left: auto;
    margin-right: auto;
  }
</style>
</head>
<body>
<div class="container">
  <a href="index.php" class="btn-home">← กลับหน้าแรก</a>

  <h1>รายงาน TypeArea ตามหน่วยบริการ</h1>

  <form method="get" action="">
    <input type="text" name="search" maxlength="5" placeholder="รหัสหน่วยบริการ 5 หลัก" value="<?php echo htmlspecialchars($search); ?>" />
    <button type="submit">ค้นหา</button>
  </form>

  <div class="export-btns">
    <a href="export_excel_report_typearea.php?search=<?php echo urlencode($search); ?>">Export Excel</a>
    <a href="export_csv_report_typearea.php?search=<?php echo urlencode($search); ?>">Export CSV</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>รหัสหน่วยบริการ</th>
        <th>ชื่อหน่วยบริการ</th>
        <th>รวม</th>
        <th>TypeArea 1</th>
        <th>TypeArea 2</th>
        <th>TypeArea 3</th>
        <th>TypeArea 4</th>
        <th>TypeArea 5</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($data) === 0): ?>
      <tr><td colspan="8">ไม่พบข้อมูล</td></tr>
      <?php else: ?>
      <?php foreach ($data as $row): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['hospcode']); ?></td>
        <td><?php echo htmlspecialchars($row['hosname']); ?></td>
        <td><?php echo number_format($row['total']); ?></td>
        <td><?php echo number_format($row['ta1']); ?></td>
        <td><?php echo number_format($row['ta2']); ?></td>
        <td><?php echo number_format($row['ta3']); ?></td>
        <td><?php echo number_format($row['ta4']); ?></td>
        <td><?php echo number_format($row['ta5']); ?></td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <canvas id="typeareaChart" width="400" height="400"></canvas>
</div>

<script>
  const ctx = document.getElementById('typeareaChart').getContext('2d');

  const data = {
    labels: ['TypeArea 1', 'TypeArea 2', 'TypeArea 3', 'TypeArea 4', 'TypeArea 5'],
    datasets: [{
      label: 'จำนวนประชากรตาม TypeArea',
      data: [
        <?php echo $total_ta1; ?>,
        <?php echo $total_ta2; ?>,
        <?php echo $total_ta3; ?>,
        <?php echo $total_ta4; ?>,
        <?php echo $total_ta5; ?>
      ],
      backgroundColor: [
        '#4caf50',
        '#2196f3',
        '#ffc107',
        '#f44336',
        '#9c27b0'
      ],
      hoverOffset: 30
    }]
  };

  const config = {
    type: 'pie',
    data: data,
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        title: {
          display: true,
          text: 'สัดส่วนจำนวนประชากรตาม TypeArea รวมทั้งหมด'
        }
      }
    }
  };

  new Chart(ctx, config);
</script>
<?php include 'footer.php'; ?>
</body>
</html>
