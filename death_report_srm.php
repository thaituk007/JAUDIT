<?php
// ====================================================================================
// ระบบรายงานข้อมูลผู้เสียชีวิต จากตาราง personfunddetail
// ====================================================================================

$config = include('config.php');

// ====================================================================================
// ฟังก์ชันส่งออก Excel
// ====================================================================================
function exportToExcel($data, $filename = 'death_report.xls') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    // Header
    echo "<table border='1'>";
    echo "<thead style='background-color:#4CAF50;color:white;'>";
    echo "<tr>";
    echo "<th>ลำดับ</th>";
    echo "<th>PID</th>";
    echo "<th>คำนำหน้า</th>";
    echo "<th>ชื่อ</th>";
    echo "<th>นามสกุล</th>";
    echo "<th>เพศ</th>";
    echo "<th>วันเกิด</th>";
    echo "<th>วันที่เสียชีวิต</th>";
    echo "<th>วันที่ตรวจสอบ</th>";
    echo "<th>สัญชาติ</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    $no = 1;
    foreach($data as $row){
        echo "<tr>";
        echo "<td>{$no}</td>";
        echo "<td>" . htmlspecialchars($row['pid'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['tname'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['fname'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['lname'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['sex_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['birthDate'] ?? '') . "</td>";
        echo "<td style='background-color:#ffebee;'><strong>" . htmlspecialchars($row['deathDate'] ?? '') . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['checkDate'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['nation_id'] ?? '') . "</td>";
        echo "</tr>";
        $no++;
    }

    echo "</tbody>";
    echo "</table>";
    exit;
}

// ====================================================================================
// Action: Export Excel
// ====================================================================================
if(isset($_GET['action']) && $_GET['action'] === 'export'){
    $mysqli = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name'],
        $config['db_port']
    );

    if($mysqli->connect_errno){
        die("Database connection error: " . $mysqli->connect_error);
    }

    // ดึงข้อมูลผู้เสียชีวิต (deathDate ไม่เป็น NULL)
    $sql = "SELECT DISTINCT
                pid, tname, fname, lname, sex_id, birthDate, deathDate, checkDate, nation_id
            FROM personfunddetail
            WHERE deathDate IS NOT NULL
            ORDER BY deathDate DESC, checkDate DESC";

    $result = $mysqli->query($sql);
    $data = [];

    if($result){
        while($row = $result->fetch_assoc()){
            $data[] = $row;
        }
        $result->free();
    }

    $mysqli->close();

    $filename = 'death_report_' . date('Ymd_His') . '.xls';
    exportToExcel($data, $filename);
}

// ====================================================================================
// HTML Interface
// ====================================================================================
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานข้อมูลผู้เสียชีวิต</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Prompt", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
            text-align: center;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #e53e3e 0%, #c62828 100%);
            color: #fff;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(229,62,62,0.3);
        }
        .stat-card h3 {
            font-size: 40px;
            margin-bottom: 8px;
        }
        .stat-card p {
            font-size: 16px;
            opacity: 0.95;
        }
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-export {
            background: #28a745;
            color: #fff;
        }
        .btn-export:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
        }
        .btn-refresh {
            background: #17a2b8;
            color: #fff;
        }
        .btn-refresh:hover {
            background: #138496;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
        }
        th {
            background-color: #e53e3e;
            color: #fff;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) { background-color: #fff5f5; }
        tr:nth-child(odd) { background-color: #ffffff; }
        tr:hover { background-color: #ffebee; }
        td.death-date {
            color: #e53e3e;
            font-weight: 600;
            font-size: 15px;
        }
        .table-wrapper {
            max-height: 600px;
            overflow-y: auto;
            border-radius: 10px;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
            font-size: 18px;
        }
        .filter-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-male {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge-female {
            background: #fce4ec;
            color: #c2185b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>☠️ รายงานข้อมูลผู้เสียชีวิต</h2>

        <?php
        // เชื่อมต่อฐานข้อมูล
        $mysqli = new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name'],
            $config['db_port']
        );

        if($mysqli->connect_errno){
            die("<div class='no-data'>❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . htmlspecialchars($mysqli->connect_error) . "</div>");
        }

        // ตัวกรอง
        $filter_date_start = $_GET['date_start'] ?? '';
        $filter_date_end = $_GET['date_end'] ?? '';
        $filter_sex = $_GET['sex'] ?? '';

        // สร้าง SQL พร้อมตัวกรอง
        $sql = "SELECT DISTINCT
                    pid, tname, fname, lname, sex_id, birthDate, deathDate, checkDate, nation_id
                FROM personfunddetail
                WHERE deathDate IS NOT NULL";

        if(!empty($filter_date_start)){
            $sql .= " AND deathDate >= '" . $mysqli->real_escape_string($filter_date_start) . "'";
        }
        if(!empty($filter_date_end)){
            $sql .= " AND deathDate <= '" . $mysqli->real_escape_string($filter_date_end) . "'";
        }
        if(!empty($filter_sex)){
            $sql .= " AND sex_id = '" . $mysqli->real_escape_string($filter_sex) . "'";
        }

        $sql .= " ORDER BY deathDate DESC, checkDate DESC";

        $result = $mysqli->query($sql);
        $total_deaths = 0;
        $data = [];

        if($result){
            $total_deaths = $result->num_rows;
            while($row = $result->fetch_assoc()){
                $data[] = $row;
            }
            $result->free();
        }

        // นับเพศ
        $count_male = 0;
        $count_female = 0;
        foreach($data as $row){
            if($row['sex_id'] == '1') $count_male++;
            if($row['sex_id'] == '2') $count_female++;
        }

        $mysqli->close();
        ?>

        <!-- สถิติ -->
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($total_deaths); ?></h3>
                <p>ผู้เสียชีวิตทั้งหมด</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);">
                <h3><?php echo number_format($count_male); ?></h3>
                <p>เพศชาย</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #c2185b 0%, #ad1457 100%);">
                <h3><?php echo number_format($count_female); ?></h3>
                <p>เพศหญิง</p>
            </div>
        </div>

        <!-- ตัวกรองข้อมูล -->
        <form method="GET" class="filter-section">
            <div class="filter-group">
                <label>วันที่เสียชีวิต (เริ่มต้น)</label>
                <input type="date" name="date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>">
            </div>
            <div class="filter-group">
                <label>วันที่เสียชีวิต (สิ้นสุด)</label>
                <input type="date" name="date_end" value="<?php echo htmlspecialchars($filter_date_end); ?>">
            </div>
            <div class="filter-group">
                <label>เพศ</label>
                <select name="sex">
                    <option value="">ทั้งหมด</option>
                    <option value="1" <?php echo $filter_sex == '1' ? 'selected' : ''; ?>>ชาย</option>
                    <option value="2" <?php echo $filter_sex == '2' ? 'selected' : ''; ?>>หญิง</option>
                </select>
            </div>
            <div class="filter-group" style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-refresh">🔍 กรองข้อมูล</button>
            </div>
        </form>

        <!-- Action Bar -->
        <div class="action-bar">
            <div>
                <strong>ข้อมูลทั้งหมด:</strong> <?php echo number_format($total_deaths); ?> รายการ
            </div>
            <div style="display:flex;gap:10px;">
                <a href="?action=export<?php
                    echo !empty($filter_date_start) ? '&date_start=' . urlencode($filter_date_start) : '';
                    echo !empty($filter_date_end) ? '&date_end=' . urlencode($filter_date_end) : '';
                    echo !empty($filter_sex) ? '&sex=' . urlencode($filter_sex) : '';
                ?>" class="btn btn-export">
                    📥 ส่งออก Excel
                </a>
                <a href="?" class="btn btn-refresh">🔄 รีเฟรช</a>
            </div>
        </div>

        <!-- ตารางข้อมูล -->
        <div class="table-wrapper">
            <?php if(empty($data)): ?>
                <div class="no-data">
                    ℹ️ ไม่พบข้อมูลผู้เสียชีวิตในระบบ
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;">ลำดับ</th>
                            <th>PID</th>
                            <th>คำนำหน้า</th>
                            <th>ชื่อ</th>
                            <th>นามสกุล</th>
                            <th style="width:80px;text-align:center;">เพศ</th>
                            <th>วันเกิด</th>
                            <th style="width:140px;">วันที่เสียชีวิต</th>
                            <th>วันที่ตรวจสอบ</th>
                            <th style="width:80px;text-align:center;">สัญชาติ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach($data as $row):
                            $sex_label = '';
                            $sex_class = '';
                            if($row['sex_id'] == '1'){
                                $sex_label = 'ชาย';
                                $sex_class = 'badge-male';
                            } else if($row['sex_id'] == '2'){
                                $sex_label = 'หญิง';
                                $sex_class = 'badge-female';
                            } else {
                                $sex_label = $row['sex_id'] ?? '-';
                            }
                        ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $no; ?></td>
                            <td><?php echo htmlspecialchars($row['pid'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['tname'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['fname'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['lname'] ?? ''); ?></td>
                            <td style="text-align:center;">
                                <span class="badge <?php echo $sex_class; ?>"><?php echo $sex_label; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['birthDate'] ?? ''); ?></td>
                            <td class="death-date">☠️ <?php echo htmlspecialchars($row['deathDate'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['checkDate'] ?? ''); ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($row['nation_id'] ?? ''); ?></td>
                        </tr>
                        <?php
                        $no++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
