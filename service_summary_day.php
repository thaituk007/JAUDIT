<?php
require_once __DIR__ . '/functions.php';  // โหลดฟังก์ชันช่วยเหลือ

$config = require 'config.php';
date_default_timezone_set('Asia/Bangkok');

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}

// ใช้ isset() แทน ?? เพื่อรองรับ PHP ต่ำกว่า 7
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'day';
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// ดึงข้อมูลตาม report type
if ($reportType === 'day') {
    $start_date = $selectedYear . '-' . $selectedMonth . '-01';
    $end_date = date("Y-m-t", strtotime($start_date));
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
        WHERE v.visitdate BETWEEN :start_date AND :end_date
        AND vd.dxtype = '01'
        GROUP BY v.visitdate
        ORDER BY v.visitdate
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $dataList = $stmt->fetchAll();

} elseif ($reportType === 'month') {
    $sql = "
        SELECT
            DATE_FORMAT(v.visitdate, '%Y-%m') AS visit_month,
            COUNT(DISTINCT CASE WHEN vd.diagcode != '' THEN v.visitno END) AS visit_count,
            COUNT(DISTINCT v.pid) AS person_count,
            SUM(CASE WHEN vd.diagcode LIKE 'Z48%' THEN 1 ELSE 0 END) AS wound,
            SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' THEN 1 ELSE 0 END) AS general,
            SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' AND u.officertype IN ('8','081') THEN 1 ELSE 0 END) AS thai_tradition,
            SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' AND u.officertype IN ('2','6') THEN 1 ELSE 0 END) AS dental
        FROM visit v
        LEFT JOIN visitdiag vd ON v.visitno = vd.visitno
        LEFT JOIN user u ON vd.doctordiag = u.username
        WHERE YEAR(v.visitdate) = :year
        AND vd.dxtype = '01'
        GROUP BY visit_month
        ORDER BY visit_month
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['year' => $selectedYear]);
    $dataList = $stmt->fetchAll();

} else { // reportType = 'year'
    $sql = "
        SELECT
            YEAR(v.visitdate) AS visit_year,
            COUNT(DISTINCT CASE WHEN vd.diagcode != '' THEN v.visitno END) AS visit_count,
            COUNT(DISTINCT v.pid) AS person_count,
            SUM(CASE WHEN vd.diagcode LIKE 'Z48%' THEN 1 ELSE 0 END) AS wound,
            SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' THEN 1 ELSE 0 END) AS general,
            SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' AND u.officertype IN ('8','081') THEN 1 ELSE 0 END) AS thai_tradition,
            SUM(CASE WHEN vd.diagcode NOT LIKE 'Z48%' AND u.officertype IN ('2','6') THEN 1 ELSE 0 END) AS dental
        FROM visit v
        LEFT JOIN visitdiag vd ON v.visitno = vd.visitno
        LEFT JOIN user u ON vd.doctordiag = u.username
        WHERE YEAR(v.visitdate) = :year
        AND vd.dxtype = '01'
        GROUP BY visit_year
        ORDER BY visit_year
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['year' => $selectedYear]);
    $dataList = $stmt->fetchAll();
}

// Export PDF / Excel
if (isset($_GET['export'])) {
    if ($_GET['export'] === 'excel') {
        require_once __DIR__ . '/export_excel.php';
        exit;
    } elseif ($_GET['export'] === 'pdf') {
        require_once __DIR__ . '/export_pdf.php';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>รายงานบริการราย<?php echo $reportType; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { font-family: 'Prompt', sans-serif; }
    tr.saturday { background-color: #f3e5f5; }
    tr.sunday { background-color: #ffe5e5; }
  </style>
</head>
<body class="p-4">
<div class="container">
  <h3 class="mb-4">รายงานบริการราย<?php echo ($reportType === 'day' ? 'วัน' : ($reportType === 'month' ? 'เดือน' : 'ปี')); ?>
    : <?php
      if ($reportType === 'day') {
          echo thaiMonthName((int)$selectedMonth) . ' ' . toBuddhistYear((int)$selectedYear);
      } else {
          echo toBuddhistYear((int)$selectedYear);
      }
    ?></h3>

  <form method="GET" class="row g-2 mb-4">
    <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($reportType); ?>" />
    <?php if ($reportType === 'day'): ?>
    <div class="col-auto">
      <label class="form-label">เดือน:</label>
      <select name="month" class="form-select">
        <?php
        for ($m = 1; $m <= 12; $m++) {
            $selected = ($selectedMonth == sprintf('%02d', $m)) ? 'selected' : '';
            echo '<option value="' . sprintf('%02d', $m) . '" ' . $selected . '>' . thaiMonthName($m) . '</option>';
        }
        ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="col-auto">
      <label class="form-label">ปี:</label>
      <select name="year" class="form-select">
        <?php
        for ($y = date('Y'); $y >= date('Y') - 3; $y--) {
            $selected = ($selectedYear == $y) ? 'selected' : '';
            echo '<option value="' . $y . '" ' . $selected . '>' . toBuddhistYear($y) . '</option>';
        }
        ?>
      </select>
    </div>

    <div class="col-auto">
      <label class="form-label">รายงาน:</label>
      <select name="report_type" class="form-select">
        <option value="day" <?php echo ($reportType === 'day') ? 'selected' : ''; ?>>รายวัน</option>
        <option value="month" <?php echo ($reportType === 'month') ? 'selected' : ''; ?>>รายเดือน</option>
        <option value="year" <?php echo ($reportType === 'year') ? 'selected' : ''; ?>>รายปี</option>
      </select>
    </div>

    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary">แสดงผล</button>
      <button type="submit" name="export" value="pdf" class="btn btn-danger">Export PDF</button>
      <button type="submit" name="export" value="excel" class="btn btn-success">Export Excel</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-hover">
      <thead class="table-secondary">
        <tr>
          <th><?php echo ($reportType === 'day' ? 'วันที่' : ($reportType === 'month' ? 'เดือน' : 'ปี')); ?></th>
          <th>Person</th>
          <th>Visit</th>
          <th>แผลเรื้อรัง</th>
          <th>ทั่วไป</th>
          <th>แพทย์แผนไทย</th>
          <th>ทันตกรรม</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($dataList)): ?>
          <?php
          $sum = ['person'=>0,'visit'=>0,'wound'=>0,'general'=>0,'thai'=>0,'dental'=>0];
          foreach ($dataList as $row):
            if ($reportType === 'day') {
                $displayDate = thaiDateFull($row['visitdate']);
            } elseif ($reportType === 'month') {
                list($y, $m) = explode('-', $row['visit_month']);
                $displayDate = thaiMonthName((int)$m) . ' ' . toBuddhistYear((int)$y);
            } else {
                $displayDate = toBuddhistYear((int)$row['visit_year']);
            }
            $sum['person'] += $row['person_count'];
            $sum['visit'] += $row['visit_count'];
            $sum['wound'] += $row['wound'];
            $sum['general'] += $row['general'];
            $sum['thai'] += $row['thai_tradition'];
            $sum['dental'] += $row['dental'];

            if ($reportType === 'day') {
                $dtObj = new DateTime($row['visitdate']);
                $dow = (int)$dtObj->format('w');
                $rowClass = $dow === 0 ? 'sunday' : ($dow === 6 ? 'saturday' : '');
            } else {
                $rowClass = '';
            }
          ?>
          <tr class="<?php echo $rowClass; ?>">
            <td><?php echo htmlspecialchars($displayDate); ?></td>
            <td><?php echo number_format($row['person_count']); ?></td>
            <td><?php echo number_format($row['visit_count']); ?></td>
            <td><?php echo number_format($row['wound']); ?></td>
            <td><?php echo number_format($row['general']); ?></td>
            <td><?php echo number_format($row['thai_tradition']); ?></td>
            <td><?php echo number_format($row['dental']); ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="table-success fw-bold">
            <td>รวมทั้งหมด</td>
            <td><?php echo number_format($sum['person']); ?></td>
            <td><?php echo number_format($sum['visit']); ?></td>
            <td><?php echo number_format($sum['wound']); ?></td>
            <td><?php echo number_format($sum['general']); ?></td>
            <td><?php echo number_format($sum['thai']); ?></td>
            <td><?php echo number_format($sum['dental']); ?></td>
          </tr>
        <?php else: ?>
          <tr><td colspan="7" class="text-center">ไม่มีข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
