<?php
require 'config.php';

$config = include 'config.php';
$pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8", $config['db_user'], $config['db_pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ฟิลเตอร์จังหวัด
$province = isset($_GET['province']) ? $_GET['province'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if (!empty($province)) {
    $where = "WHERE province_name = :province";
    $params[':province'] = $province;
}

// ดึงจำนวนทั้งหมด
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rpt_excel_import $where");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// ดึงข้อมูลหน้า
$sql = "SELECT * FROM rpt_excel_import $where LIMIT $offset, $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงจังหวัดทั้งหมด
$provinces = $pdo->query("SELECT DISTINCT province FROM rpt_excel_import ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>รายงานข้อมูลจาก Excel</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
    .pagination a { margin: 0 5px; text-decoration: none; }
  </style>
</head>
<body>
  <h2>📊 รายงานข้อมูลจาก Excel</h2>

  <form method="get">
    จังหวัด:
    <select name="province" onchange="this.form.submit()">
      <option value="">-- เลือกทั้งหมด --</option>
      <?php foreach ($provinces as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>" <?= $province === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
      <?php endforeach ?>
    </select>
  </form>

  <table>
    <thead>
      <tr>
        <?php if (!empty($data)): ?>
          <?php foreach (array_keys($data[0]) as $key): ?>
            <th><?= htmlspecialchars($key) ?></th>
          <?php endforeach ?>
        <?php endif ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $row): ?>
        <tr>
          <?php foreach ($row as $cell): ?>
            <td><?= htmlspecialchars($cell) ?></td>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div class="pagination">
    หน้า:
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?province=<?= urlencode($province) ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>

  <h3>📈 กราฟจำแนกตามจังหวัด</h3>
  <canvas id="chart" width="600" height="300"></canvas>
  <script>
    fetch('get_chart_data.php?province=<?= urlencode($province) ?>')
      .then(res => res.json())
      .then(chartData => {
        new Chart(document.getElementById('chart'), {
          type: 'bar',
          data: {
            labels: chartData.labels,
            datasets: [{
              label: 'จำนวน',
              data: chartData.counts,
              backgroundColor: 'rgba(75, 192, 192, 0.6)'
            }]
          }
        });
      });
  </script>

  <h3>📥 Export</h3>
  <a href="export.php?format=excel&province=<?= urlencode($province) ?>">Export to Excel</a> |
  <a href="export.php?format=csv&province=<?= urlencode($province) ?>">Export to CSV</a>
</body>
</html>
