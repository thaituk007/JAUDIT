<?php
// โหลด config
$config = include('config.php');

// เชื่อมต่อฐานข้อมูล
$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);

// ตรวจสอบการเชื่อมต่อ
if ($mysqli->connect_errno) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $mysqli->connect_error);
}

// SQL Query
$sql = "
SELECT p.pcucodeperson, p.pid,
       p.idcard AS เลขบัตรประจำตัวประชาชน,
       CONCAT(IFNULL(p.fname,''),' ',IFNULL(p.lname,'')) AS ชื่อและนามสกุล,
       DATE_FORMAT(p.birth, '%d/%m/%Y') AS วันเกิด,
       getAgeYearnum(p.birth,CURRENT_DATE) AS อายุ,
       IF(p.sex =1,'ชาย','หญิง') AS เพศ,
       p.typelive, p.dischargetype, person_checkright.death_status
FROM person_checkright
LEFT JOIN person p ON person_checkright.cid = p.idcard
WHERE person_checkright.death_status IN ('1')
  AND p.dischargetype IN ('9')
ORDER BY p.typelive ASC;
";

// ดึงข้อมูล
$result = $mysqli->query($sql);
if (!$result) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $mysqli->error);
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลผู้เสียชีวิต</title>
    <!-- Google Font Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f4f7f9;
            margin: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .table-container {
            background-color: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
            color: #fff;
        }
        tr:nth-child(even) {
            background-color: #f7f9fc;
        }
        tr:hover {
            background-color: #e1f0ff;
        }
        .btn-home {
            display: inline-block;
            margin: 20px 0;
            padding: 10px 20px;
            background: #4facfe;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-home:hover {
            background: #00f2fe;
            color: #000;
        }
    </style>
</head>
<body>
    <h1>ข้อมูลผู้เสียชีวิตจาก SRM ที่ยังไม่ได้จำหน่ายใน JHCIS</h1>
    <a href="index.php" class="btn-home">หน้าแรก</a>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>PCU Code</th>
                    <th>PID</th>
                    <th>เลขบัตรประชาชน</th>
                    <th>ชื่อและนามสกุล</th>
                    <th>วันเกิด</th>
                    <th>อายุ</th>
                    <th>เพศ</th>
                    <th>ประเภทการอยู่อาศัย</th>
                    <th>ประเภทจำหน่าย</th>
                    <th>สถานะการเสียชีวิต</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['pcucodeperson']) ?></td>
                        <td><?= htmlspecialchars($row['pid']) ?></td>
                        <td><?= htmlspecialchars($row['เลขบัตรประจำตัวประชาชน']) ?></td>
                        <td><?= htmlspecialchars($row['ชื่อและนามสกุล']) ?></td>
                        <td><?= htmlspecialchars($row['วันเกิด']) ?></td>
                        <td><?= htmlspecialchars($row['อายุ']) ?></td>
                        <td><?= htmlspecialchars($row['เพศ']) ?></td>
                        <td><?= htmlspecialchars($row['typelive']) ?></td>
                        <td><?= htmlspecialchars($row['dischargetype']) ?></td>
                        <td><?= htmlspecialchars($row['death_status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
$mysqli->close();
?>
