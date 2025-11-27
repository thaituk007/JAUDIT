<?php
header('Content-Type: text/html; charset=utf-8');

// โหลด config
$config = require 'config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}

$sql = "
SELECT
    village.villcode AS รหัสหมู่บ้าน,
    village.villname AS ชื่อหมู่บ้าน,
    p.typelive AS TypeArea,
    SUM(CASE WHEN p.typelive IN (1,2,3,4,5) THEN 1 ELSE 0 END) AS ทั้งหมดทุก_TypeArea,
    SUM(CASE WHEN p.typelive IN (1,3) THEN 1 ELSE 0 END) AS `1+3`,
    SUM(CASE WHEN p.typelive IN (1,2) THEN 1 ELSE 0 END) AS `1+2_ตามทะเบียนราษฎร์`,
    SUM(CASE WHEN p.typelive IN (1,2,3) THEN 1 ELSE 0 END) AS `1,2,3_ตามพื้นที่รับผิดชอบ`,
    SUM(CASE WHEN p.typelive = '1' THEN 1 ELSE 0 END) AS TypeArea_1,
    SUM(CASE WHEN p.typelive = '2' THEN 1 ELSE 0 END) AS TypeArea_2,
    SUM(CASE WHEN p.typelive = '3' THEN 1 ELSE 0 END) AS TypeArea_3,
    SUM(CASE WHEN p.typelive = '4' THEN 1 ELSE 0 END) AS TypeArea_4,
    SUM(CASE WHEN p.typelive = '5' THEN 1 ELSE 0 END) AS TypeArea_5
FROM person p
LEFT JOIN house h ON p.hcode = h.hcode AND p.pcucodeperson = h.pcucode
LEFT JOIN village ON h.villcode = village.villcode AND village.pcucode = h.pcucode
LEFT JOIN persondeath pd ON p.pid = pd.pid AND p.pcucodeperson = pd.pcucodeperson
WHERE SUBSTRING(h.villcode,7,2) <> '00'
AND pd.pid IS NULL
GROUP BY village.villcode, village.villname, p.typelive

UNION ALL

SELECT
    'รวม' AS รหัสหมู่บ้าน,
    '' AS ชื่อหมู่บ้าน,
    NULL AS TypeArea,
    SUM(CASE WHEN p.typelive IN (1,2,3,4,5) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive IN (1,3) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive IN (1,2) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive IN (1,2,3) THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '1' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '2' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '3' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '4' THEN 1 ELSE 0 END),
    SUM(CASE WHEN p.typelive = '5' THEN 1 ELSE 0 END)
FROM person p
LEFT JOIN house h ON p.hcode = h.hcode AND p.pcucodeperson = h.pcucode
LEFT JOIN persondeath pd ON p.pid = pd.pid AND p.pcucodeperson = pd.pcucodeperson
WHERE SUBSTRING(h.villcode,7,2) <> '00'
AND pd.pid IS NULL
";

try {
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query ผิดพลาด: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>จำนวนประชากรแยกตาม TypeArea รายหมู่บ้าน</title>

<!-- Google Font: Sarabun -->
<link href="https://fonts.googleapis.com/css2?family=Sarabun&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Sarabun', sans-serif;
        background: #f9fafd;
        color: #333;
        margin: 20px;
    }
    h2 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 20px;
        font-weight: 700;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 0 8px rgb(0 0 0 / 0.1);
        border-radius: 6px;
        overflow: hidden;
    }
    thead tr {
        background-color: #3498db;
        color: white;
        font-weight: 600;
        font-size: 14px;
        text-align: center;
    }
    tbody tr:nth-child(even) {
        background-color: #f0f4f8;
    }
    tbody tr:hover {
        background-color: #d6e9ff;
        cursor: pointer;
    }
    tbody tr:last-child {
        background-color: #2ecc71;
        color: white;
        font-weight: 700;
    }
    th, td {
        padding: 10px 12px;
        border: 1px solid #ddd;
        font-size: 13px;
    }
    td {
        text-align: right;
    }
    td:first-child, td:nth-child(2), td:nth-child(3) {
        text-align: left;
    }
    @media (max-width: 768px) {
        body {
            margin: 10px;
        }
        table, thead, tbody, th, td, tr {
            display: block;
        }
        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        tr {
            margin-bottom: 1rem;
            border-bottom: 2px solid #ddd;
        }
        td {
            border: none;
            position: relative;
            padding-left: 50%;
            text-align: left;
            font-size: 14px;
        }
        td:before {
            position: absolute;
            top: 10px;
            left: 12px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: 600;
            content: attr(data-label);
            color: #555;
        }
        td:first-child, td:nth-child(2), td:nth-child(3) {
            padding-left: 50%;
            text-align: left;
        }
    }
</style>
</head>
<body>

<h2>จำนวนประชากรแยกตาม TypeArea รายหมู่บ้าน</h2>

<table>
    <thead>
        <tr>
            <th>รหัสหมู่บ้าน</th>
            <th>ชื่อหมู่บ้าน</th>
            <th>TypeArea</th>
            <th>ทั้งหมดทุก TypeArea</th>
            <th>1+3</th>
            <th>1+2 ตามทะเบียนราษฎร์</th>
            <th>1,2,3 ตามพื้นที่รับผิดชอบ</th>
            <th>TypeArea 1</th>
            <th>TypeArea 2</th>
            <th>TypeArea 3</th>
            <th>TypeArea 4</th>
            <th>TypeArea 5</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($results as $row): ?>
        <tr <?= ($row['รหัสหมู่บ้าน'] === 'รวม') ? 'style="font-weight:700; background:#2ecc71; color:#fff;"' : '' ?>>
            <td data-label="รหัสหมู่บ้าน"><?= htmlspecialchars($row['รหัสหมู่บ้าน']) ?></td>
            <td data-label="ชื่อหมู่บ้าน"><?= htmlspecialchars($row['ชื่อหมู่บ้าน']) ?></td>
            <td data-label="TypeArea"><?= htmlspecialchars($row['TypeArea']) ?></td>
            <td data-label="ทั้งหมดทุก TypeArea" style="text-align:right;"><?= number_format($row['ทั้งหมดทุก_TypeArea']) ?></td>
            <td data-label="1+3" style="text-align:right;"><?= number_format($row['1+3']) ?></td>
            <td data-label="1+2 ตามทะเบียนราษฎร์" style="text-align:right;"><?= number_format($row['1+2_ตามทะเบียนราษฎร์']) ?></td>
            <td data-label="1,2,3 ตามพื้นที่รับผิดชอบ" style="text-align:right;"><?= number_format($row['1,2,3_ตามพื้นที่รับผิดชอบ']) ?></td>
            <td data-label="TypeArea 1" style="text-align:right;"><?= number_format($row['TypeArea_1']) ?></td>
            <td data-label="TypeArea 2" style="text-align:right;"><?= number_format($row['TypeArea_2']) ?></td>
            <td data-label="TypeArea 3" style="text-align:right;"><?= number_format($row['TypeArea_3']) ?></td>
            <td data-label="TypeArea 4" style="text-align:right;"><?= number_format($row['TypeArea_4']) ?></td>
            <td data-label="TypeArea 5" style="text-align:right;"><?= number_format($row['TypeArea_5']) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
