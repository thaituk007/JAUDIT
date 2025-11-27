<?php
require 'connect.php';

$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

$start_date = "$selectedYear-$selectedMonth-01";
$end_date = date("Y-m-t", strtotime($start_date));

// Query ข้อมูล
$sql = "
    SELECT
        v.visitdate,
        COUNT(DISTINCT CASE WHEN vd.diagcode != '' THEN v.visitno END) AS visit_count,
        COUNT(DISTINCT v.pid) AS person_count,
        SUM(CASE WHEN vd.diagcode LIKE 'Z48%' THEN 1 ELSE 0 END) AS wound,
        SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' THEN 1 ELSE 0 END) AS general,
        SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' AND u.officertype IN ('8','081') THEN 1 ELSE 0 END) AS thai_tradition,
        SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' AND u.officertype IN ('2','6') THEN 1 ELSE 0 END) AS dental
    FROM visit v
    LEFT JOIN visitdiag vd ON v.visitno = vd.visitno
    LEFT JOIN user u ON vd.doctordiag = u.username
    WHERE v.visitdate BETWEEN '$start_date' AND '$end_date'
    AND vd.dxtype = '01'
    GROUP BY v.visitdate
    ORDER BY v.visitdate
";

$result = $conn->query($sql);

// เตรียมข้อมูลรายวัน
function getDaysInMonth($year, $month) {
    $days = [];
    $numDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($day = 1; $day <= $numDays; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $days[$date] = [
            'visitdate' => $date,
            'visit_count' => 0,
            'person_count' => 0,
            'wound' => 0,
            'general' => 0,
            'thai_tradition' => 0,
            'dental' => 0,
        ];
    }
    return $days;
}

$daysInMonth = getDaysInMonth((int)$selectedYear, (int)$selectedMonth);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['visitdate'];
        if (isset($daysInMonth[$date])) {
            $daysInMonth[$date] = array_merge($daysInMonth[$date], $row);
        }
    }
}

$dailyData = array_values($daysInMonth);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>รายงานบริการรายวัน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    tr.saturday { background-color: #f3e5f5; }
    tr.sunday { background-color: #ffe5e5; }
  </style>
</head>
<body class="p-4">
<div class="container">
  <h3 class="mb-4">รายงานบริการรายวัน: <?= date('F Y', strtotime($start_date)) ?></h3>

  <!-- Filter -->
  <form method="GET" class="row g-2 mb-4">
    <div class="col-auto">
      <label class="form-label">เดือน:</label>
      <select name="month" class="form-select">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= sprintf('%02d', $m) ?>" <?= $selectedMonth == sprintf('%02d', $m) ? 'selected' : '' ?>>
            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label">ปี:</label>
      <select name="year" class="form-select">
        <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
          <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary">แสดงผล</button>
    </div>
  </form>

  <!-- ส่วนของ Chart -->
<canvas id="dailyChart" height="100"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.4.0"></script>
<script>
  const labels = <?= json_encode(array_map(fn($d) => (new DateTime($d['visitdate']))->format('d'), $dailyData)) ?>;
  const data = <?= json_encode(array_map(fn($d) => (int)$d['visit_count'], $dailyData)) ?>;
  const bgColors = <?= json_encode(array_map(function($d) {
      $dow = (int)(new DateTime($d['visitdate']))->format('w');
      return $dow === 0 ? '#ffe5e5' : ($dow === 6 ? '#f3e5f5' : '#bbdefb');
  }, $dailyData)) ?>;
  const weekendLines = <?= json_encode(array_keys(array_filter($dailyData, function($d) {
      $dow = (int)(new DateTime($d['visitdate']))->format('w');
      return $dow === 0 || $dow === 6;
  }))) ?>.map(idx => ({
    type: 'line',
    scaleID: 'x',
    value: (new Date(idx)).getDate() - 1,
    borderColor: '#ff1744',
    borderWidth: 1,
    label: {
      content: 'WEEKEND',
      enabled: false
    }
  }));

  new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'จำนวน Visit รายวัน',
        data: data,
        backgroundColor: bgColors,
        borderColor: '#333',
        borderWidth: 1
      }]
    },
    options: {
      plugins: {
        legend: { display: false },
        annotation: {
          annotations: weekendLines
        }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
</script>

  <!-- Table -->
  <!-- Table -->
<div class="table-responsive mt-4">
  <table class="table table-bordered table-hover">
    <thead class="table-secondary">
      <tr>
        <th>วันที่</th>
        <th>Person</th>
        <th>Visit</th>
        <th>แผลเรื้อรัง</th>
        <th>ทั่วไป</th>
        <th>แพทย์แผนไทย</th>
        <th>ทันตกรรม</th>
      </tr>
    </thead>
    <tbody>
      <?php
      // เตรียมตัวแปรรวม
      $sum = ['person' => 0, 'visit' => 0, 'wound' => 0, 'general' => 0, 'thai' => 0, 'dental' => 0];
      foreach ($dailyData as $row):
        $dt = new DateTime($row['visitdate']);
        $dow = (int)$dt->format('w');
        $rowClass = $dow === 0 ? 'sunday' : ($dow === 6 ? 'saturday' : '');
        // สะสมรวม
        $sum['person'] += $row['person_count'];
        $sum['visit']  += $row['visit_count'];
        $sum['wound']  += $row['wound'];
        $sum['general'] += $row['general'];
        $sum['thai']   += $row['thai_tradition'];
        $sum['dental'] += $row['dental'];
      ?>
      <tr class="<?= $rowClass ?>">
        <td><?= $dt->format('d M Y (D)') ?></td>
        <td><?= number_format($row['person_count']) ?></td>
        <td><?= number_format($row['visit_count']) ?></td>
        <td><?= number_format($row['wound']) ?></td>
        <td><?= number_format($row['general']) ?></td>
        <td><?= number_format($row['thai_tradition']) ?></td>
        <td><?= number_format($row['dental']) ?></td>
      </tr>
      <?php endforeach; ?>
      <!-- รวมท้ายตาราง -->
      <tr class="table-success fw-bold">
        <td>รวมทั้งหมด</td>
        <td><?= number_format($sum['person']) ?></td>
        <td><?= number_format($sum['visit']) ?></td>
        <td><?= number_format($sum['wound']) ?></td>
        <td><?= number_format($sum['general']) ?></td>
        <td><?= number_format($sum['thai']) ?></td>
        <td><?= number_format($sum['dental']) ?></td>
      </tr>
    </tbody>
  </table>
</div>
</div>
</body>
</html>
