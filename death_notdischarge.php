<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$config = require __DIR__ . '/config.php';

// Database connect
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

$sql = "
SELECT
    person_data.hospcode AS HOSCODE,
    person_data.pid AS HN,
    CONCAT(person_data.name, ' ', person_data.lname) AS FULLNAME,
    CONCAT(
        LPAD(deathchon.DDATE,2,'0'), '/',
        LPAD(deathchon.DMON,2,'0'), '/',
        deathchon.DYEAR
    ) AS DEATHDATE,
    deathchon.NCAUSE AS ICD10,
    deathchon.GroupName AS GROUPNAME
FROM deathchon
INNER JOIN person_data ON person_data.cid = deathchon.PID
WHERE person_data.discharge = '9'
ORDER BY person_data.hospcode ASC;
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($data); // ✅ นับจำนวนข้อมูลทั้งหมด
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อผู้เสียชีวิตที่ยังไม่จำหน่าย จากแฟ้ม PERSON</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 20px 30px;
        }

        h2 {
            text-align: center;
            color: #2b3e73;
            margin-bottom: 8px;
        }

        .total {
            text-align: center;
            font-size: 16px;
            color: #444;
            margin-bottom: 20px;
        }

        .top-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .btn {
            background: linear-gradient(135deg, #4f8ef7, #6fc3f7);
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
        }

        .btn:hover {
            background: linear-gradient(135deg, #3c7de0, #58b5eb);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border-radius: 10px;
            overflow: hidden;
        }

        th {
            background-color: #4f8ef7;
            color: white;
            padding: 12px;
            text-align: center;
        }

        td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: center;
        }

        /* ✅ ชิดซ้ายเฉพาะ HN, ชื่อ–สกุล, กลุ่มโรค */
        td:nth-child(2),
        td:nth-child(3),
        td:nth-child(6) {
            text-align: left;
            padding-left: 16px;
        }

        tr:nth-child(even) {
            background-color: #f8faff;
        }

        tr:hover {
            background-color: #e6f0ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-buttons">
            <button class="btn" onclick="window.location='index.php'">🏠 กลับหน้าแรก</button>
            <button class="btn" onclick="exportTableToExcel('deathTable')">📤 นำออก Excel</button>
        </div>

        <h2>รายชื่อผู้เสียชีวิตที่ยังไม่จำหน่าย จากแฟ้ม PERSON</h2>

        <!-- ✅ แสดงจำนวนข้อมูลทั้งหมด -->
        <div class="total">
            พบข้อมูลทั้งหมด <strong><?= number_format($total) ?></strong> รายการ
        </div>

        <table id="deathTable">
            <thead>
                <tr>
                    <th>รหัสหน่วยบริการ</th>
                    <th>HN</th>
                    <th>ชื่อ–สกุล</th>
                    <th>วันที่เสียชีวิต</th>
                    <th>ICD10</th>
                    <th>กลุ่มโรค</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total > 0): ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['HOSCODE']) ?></td>
                            <td><?= htmlspecialchars($row['HN']) ?></td>
                            <td><?= htmlspecialchars($row['FULLNAME']) ?></td>
                            <td><?= htmlspecialchars($row['DEATHDATE']) ?></td>
                            <td><?= htmlspecialchars($row['ICD10']) ?></td>
                            <td><?= htmlspecialchars($row['GROUPNAME']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">ไม่มีข้อมูล</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function exportTableToExcel(tableID, filename = ''){
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');

            // ✅ ตั้งชื่อไฟล์ให้ตรงกับหัวเรื่อง
            filename = filename ? filename + '.xls' : 'รายชื่อผู้เสียชีวิตที่ยังไม่จำหน่าย_แฟ้ม_PERSON.xls';

            var downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);

            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
                navigator.msSaveOrOpenBlob( blob, filename );
            } else {
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>
</body>
</html>
