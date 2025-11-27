<?php
require_once 'config.php';
date_default_timezone_set('Asia/Bangkok');

$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : date('n');

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'], $config['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = <<<SQL
SELECT village.villname
,IF (length(village.villno)=1,concat(0,village.villno),village.villno) AS village_no
,SUM(CASE WHEN person.sex='1' THEN 1 ELSE null END) AS male
,SUM(CASE WHEN person.sex='2' THEN 1 ELSE null END) AS female
,SUM(CASE WHEN person.sex IN ('1','2') THEN 1 ELSE null END) AS total
,SUM(CASE WHEN person.typelive IN (1,2,3,4,5) THEN 1 ELSE 0 END) AS all_type
,SUM(CASE WHEN person.typelive IN (1,3) THEN 1 ELSE 0 END) AS type_1_3
,SUM(CASE WHEN person.typelive IN (1,2) THEN 1 ELSE 0 END) AS type_1_2
,SUM(CASE WHEN person.typelive IN (1,2,3) THEN 1 ELSE 0 END) AS type_1_2_3
,SUM(CASE WHEN person.typelive='1' THEN 1 ELSE 0 END) AS type_1
,SUM(CASE WHEN person.typelive='2' THEN 1 ELSE 0 END) AS type_2
,SUM(CASE WHEN person.typelive='3' THEN 1 ELSE 0 END) AS type_3
,SUM(CASE WHEN person.typelive='4' THEN 1 ELSE 0 END) AS type_4
,SUM(CASE WHEN person.typelive='5' THEN 1 ELSE 0 END) AS type_5
FROM person
LEFT OUTER JOIN house ON person.hcode = house.hcode AND house.pcucode = person.pcucodeperson
LEFT OUTER JOIN village ON house.villcode = village.villcode AND village.pcucode = house.pcucode
LEFT OUTER JOIN persondeath ON person.pid = persondeath.pid AND person.pcucodeperson = persondeath.pcucodeperson
WHERE persondeath.pid IS NULL
GROUP BY village.villno

UNION

SELECT 'รวม',''
,SUM(CASE WHEN person.sex='1' THEN 1 ELSE null END)
,SUM(CASE WHEN person.sex='2' THEN 1 ELSE null END)
,SUM(CASE WHEN person.sex IN ('1','2') THEN 1 ELSE null END)
,SUM(CASE WHEN person.typelive IN (1,2,3,4,5) THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive IN (1,3) THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive IN (1,2) THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive IN (1,2,3) THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive='1' THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive='2' THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive='3' THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive='4' THEN 1 ELSE 0 END)
,SUM(CASE WHEN person.typelive='5' THEN 1 ELSE 0 END)
FROM person
LEFT OUTER JOIN house ON person.hcode = house.hcode AND house.pcucode = person.pcucodeperson
LEFT OUTER JOIN village ON house.villcode = village.villcode AND village.pcucode = house.pcucode
LEFT OUTER JOIN persondeath ON person.pid = persondeath.pid AND person.pcucodeperson = persondeath.pcucodeperson
WHERE persondeath.pid IS NULL
SQL;

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงาน TypeArea แยกหมู่บ้าน</title>
    <style>
        body { font-family: Tahoma, sans-serif; margin: 20px; }
        h2 { color: #2c3e50; }
        form { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        select, button { padding: 5px 10px; font-size: 16px; }
        button { background-color: #3498db; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #2980b9; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: center; }
        th { background-color: #f4f4f4; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:last-child { background-color: #ffeaa7; font-weight: bold; }
    </style>
</head>
<body>

<h2>รายงานจำนวนประชากรแยกตาม TypeArea รายหมู่บ้าน</h2>

<form method="get">
    <label for="year">ปี:</label>
    <select name="year" id="year">
        <?php
        $currentYear = date('Y');
        for ($i = $currentYear; $i >= $currentYear - 9; $i--) {
            echo "<option value=\"$i\"" . ($year == $i ? ' selected' : '') . ">$i</option>";
        }
        ?>
    </select>

    <label for="month">เดือน:</label>
    <select name="month" id="month">
        <?php
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];
        foreach ($thaiMonths as $num => $name) {
            echo "<option value=\"$num\"" . ($month == $num ? ' selected' : '') . ">$name</option>";
        }
        ?>
    </select>

    <button type="submit">แสดงผล</button>
    <button type="reset" onclick="window.location.href='report_typearea_by_village.php'">ล้างค่า</button>
</form>

<table>
    <thead>
        <tr>
            <th>หมู่บ้าน</th>
            <th>หมู่ที่</th>
            <th>เพศชาย</th>
            <th>เพศหญิง</th>
            <th>รวม</th>
            <th>ทุก TypeArea</th>
            <th>1+3</th>
            <th>1+2</th>
            <th>1,2,3</th>
            <th>TypeArea 1</th>
            <th>TypeArea 2</th>
            <th>TypeArea 3</th>
            <th>TypeArea 4</th>
            <th>TypeArea 5</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['villname']) ?></td>
                <td><?= htmlspecialchars($row['village_no']) ?></td>
                <td><?= $row['male'] ?></td>
                <td><?= $row['female'] ?></td>
                <td><?= $row['total'] ?></td>
                <td><?= $row['all_type'] ?></td>
                <td><?= $row['type_1_3'] ?></td>
                <td><?= $row['type_1_2'] ?></td>
                <td><?= $row['type_1_2_3'] ?></td>
                <td><?= $row['type_1'] ?></td>
                <td><?= $row['type_2'] ?></td>
                <td><?= $row['type_3'] ?></td>
                <td><?= $row['type_4'] ?></td>
                <td><?= $row['type_5'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
