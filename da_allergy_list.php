<?php
$config = include 'config.php';

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
$mysqli->set_charset("utf8");

$keyword = $_GET['keyword'] ?? '';
$vill = $_GET['vill'] ?? '';

$sql = "
SELECT IF(t.titlecode IS NULL OR titlename IS NULL OR trim(titlename)='', '..', titlename) AS prename,
p.fname, p.lname, p.birth,
TIMESTAMPDIFF(YEAR, p.birth, CURDATE()) AS age,
GROUP_CONCAT(cd.drugname ORDER BY cd.drugname SEPARATOR ', ') AS drugname,
MAX(pg.daterecord) AS daterecord,
h.hno, v.villno, v.villname,
p.pid
FROM personalergic pg
JOIN person p ON pg.pcucodeperson = p.pcucodeperson AND pg.pid = p.pid
LEFT JOIN persondeath pd ON p.pcucodeperson = pd.pcucodeperson AND pd.pid = pg.pid
LEFT JOIN ctitle t ON p.prename = t.titlecode
JOIN cdrug cd ON pg.drugcode = cd.drugcode
JOIN house h ON p.pcucodeperson = h.pcucode AND p.hcode = h.hcode
JOIN village v ON h.pcucode = v.pcucode AND h.villcode = v.villcode
WHERE pd.pid IS NULL
";

if ($keyword !== '') {
    $sql .= " AND (p.fname LIKE '%$keyword%' OR p.lname LIKE '%$keyword%' OR p.pid LIKE '%$keyword%') ";
}

if ($vill !== '') {
    $sql .= " AND v.villno = '$vill' ";
}

$sql .= " GROUP BY p.pid, p.pcucodeperson, p.fname, p.lname, p.birth, h.hno, v.villno, v.villname, t.titlecode, t.titlename, p.prename ORDER BY p.pid ";
$result = $mysqli->query($sql);
$total = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงานทะเบียนแพ้ยา</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Prompt', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 30px 20px;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
}

.header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 30px 40px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.header h1 {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats {
    display: flex;
    gap: 15px;
    align-items: center;
}

.stat-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-home {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    transition: all 0.3s ease;
}

.btn-home:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 35px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    margin-bottom: 30px;
}

.search-section {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 25px;
}

.search-group {
    flex: 1;
    min-width: 200px;
}

.search-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #4a5568;
    font-size: 14px;
}

input[type="text"], select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 15px;
    font-family: 'Prompt', sans-serif;
    transition: all 0.3s ease;
    background: white;
}

input[type="text"]:focus, select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.btn {
    padding: 14px 28px;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: 'Prompt', sans-serif;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.btn-search {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-excel {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-pdf {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-dashboard {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.table-container {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

th {
    padding: 18px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 15px;
    white-space: nowrap;
}

td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}

tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background: #f8fafc;
    transform: scale(1.01);
}

tbody tr:last-child td {
    border-bottom: none;
}

.drug-badge {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
    margin: 2px;
}

.location-badge {
    background: #e0e7ff;
    color: #4c1d95;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 60px 40px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    display: block;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #64748b;
}

.empty-state p {
    font-size: 15px;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .header {
        padding: 20px;
    }

    .header h1 {
        font-size: 24px;
    }

    .stats {
        flex-direction: column;
        width: 100%;
    }

    .btn-home, .stat-badge {
        width: 100%;
        justify-content: center;
    }

    .search-section {
        flex-direction: column;
    }

    .search-group {
        width: 100%;
    }

    .btn-group {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    table {
        font-size: 13px;
    }

    th, td {
        padding: 12px 10px;
    }
}
</style>

</head>
<body>

<div class="container">

    <div class="header">
        <h1>
            <i class="fas fa-allergies"></i>
            รายงานทะเบียนแพ้ยา
        </h1>
        <div class="stats">
            <a href="index.php" class="btn-home">
                <i class="fas fa-home"></i> หน้าแรก
            </a>
            <div class="stat-badge">
                <i class="fas fa-users"></i> ทั้งหมด <?= number_format($total) ?> รายการ
            </div>
        </div>
    </div>

    <div class="card">
        <form method="GET">
            <div class="search-section">
                <div class="search-group">
                    <label><i class="fas fa-search"></i> ค้นหา (ชื่อ-สกุล หรือ PID)</label>
                    <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="ระบุคำค้นหา...">
                </div>

                <div class="search-group">
                    <label><i class="fas fa-map-marker-alt"></i> หมู่บ้าน</label>
                    <select name="vill">
                        <option value="">-- ทั้งหมด --</option>
                        <?php
                        $vlist = $mysqli->query("SELECT villno,villname FROM village GROUP BY villno ORDER BY villno");
                        while($v = $vlist->fetch_assoc()):
                            $sel = ($vill == $v['villno']) ? "selected" : "";
                            echo "<option value='{$v['villno']}' $sel>หมู่ {$v['villno']} - {$v['villname']}</option>";
                        endwhile;
                        ?>
                    </select>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-search">
                    <i class="fas fa-search"></i> ค้นหา
                </button>
                <a href="da_dashboard.php" class="btn btn-dashboard">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="da_export_excel.php?keyword=<?= urlencode($keyword) ?>&vill=<?= urlencode($vill) ?>" class="btn btn-excel">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
                <a href="da_export_pdf.php?keyword=<?= urlencode($keyword) ?>&vill=<?= urlencode($vill) ?>" class="btn btn-pdf">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-user"></i> ชื่อ - สกุล</th>
                        <th><i class="fas fa-birthday-cake"></i> อายุ</th>
                        <th><i class="fas fa-pills"></i> ยาที่แพ้</th>
                        <th><i class="fas fa-calendar-alt"></i> วันที่บันทึก</th>
                        <th><i class="fas fa-home"></i> บ้าน/หมู่</th>
                        <th><i class="fas fa-id-card"></i> PID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total > 0): ?>
                        <?php while($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['prename'].$r['fname']." ".$r['lname']) ?></strong></td>
                            <td><?= number_format($r['age']) ?> ปี</td>
                            <td>
                                <?php
                                $drugs = explode(', ', $r['drugname']);
                                foreach($drugs as $drug):
                                ?>
                                    <span class="drug-badge"><?= htmlspecialchars($drug) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= htmlspecialchars($r['daterecord']) ?></td>
                            <td><span class="location-badge"><?= htmlspecialchars($r['hno']) ?> / หมู่ <?= htmlspecialchars($r['villno']) ?></span></td>
                            <td><?= htmlspecialchars($r['pid']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <h3>ไม่พบข้อมูล</h3>
                                    <p>ลองเปลี่ยนคำค้นหาหรือเงื่อนไขการกรองใหม่</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
