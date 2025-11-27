<?php
// ====================================================================================
// ระบบรายงานข้อมูลสิทธิการรักษา จากตาราง personfunddetail
// ====================================================================================

$config = include('config.php');

// ====================================================================================
// ฟังก์ชันส่งออก Excel
// ====================================================================================
function exportToExcel($data, $filename = 'fund_report.xls') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    // Header
    echo "<table border='1'>";
    echo "<thead style='background-color:#556ee6;color:white;'>";
    echo "<tr>";
    echo "<th>ลำดับ</th>";
    echo "<th>PID</th>";
    echo "<th>คำนำหน้า</th>";
    echo "<th>ชื่อ</th>";
    echo "<th>นามสกุล</th>";
    echo "<th>เพศ</th>";
    echo "<th>วันเกิด</th>";
    echo "<th>สิทธิหลัก (ID)</th>";
    echo "<th>สิทธิหลัก (ชื่อ)</th>";
    echo "<th>สิทธิย่อย (ID)</th>";
    echo "<th>สิทธิย่อย (ชื่อ)</th>";
    echo "<th>รพ.หลัก (รหัส)</th>";
    echo "<th>รพ.หลัก (ชื่อ)</th>";
    echo "<th>รพ.ย่อย (รหัส)</th>";
    echo "<th>รพ.ย่อย (ชื่อ)</th>";
    echo "<th>วันเริ่มสิทธิ</th>";
    echo "<th>วันหมดสิทธิ</th>";
    echo "<th>ประเภทกองทุน</th>";
    echo "<th>วันที่ตรวจสอบ</th>";
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
        echo "<td>" . htmlspecialchars($row['mainInscl_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['mainInscl_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['subInscl_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['subInscl_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['hospMain_hcode'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['hospMain_hname'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['hospSub_hcode'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['hospSub_hname'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['startDateTime'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['expireDateTime'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['fundType'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['checkDate'] ?? '') . "</td>";
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

    // ตัวกรอง
    $filter_main = $_GET['main'] ?? '';
    $filter_sub = $_GET['sub'] ?? '';
    $filter_hosp_main = $_GET['hosp_main'] ?? '';
    $filter_hosp_sub = $_GET['hosp_sub'] ?? '';
    $filter_fund_type = $_GET['fund_type'] ?? '';

    // สร้าง SQL
    $sql = "SELECT
                pfd.pid, pfd.tname, pfd.fname, pfd.lname, pfd.sex_id, pfd.birthDate,
                pfd.mainInscl_id, pfd.mainInscl_name,
                pfd.subInscl_id, pfd.subInscl_name,
                pfd.hospMain_hcode, pfd.hospMain_hname,
                pfd.hospSub_hcode, pfd.hospSub_hname,
                pfd.startDateTime, pfd.expireDateTime, pfd.fundType, pfd.checkDate,
                p.typelive
            FROM personfunddetail pfd
            LEFT JOIN person p ON pfd.pid = p.idcard
            WHERE pfd.deathDate IS NULL
            AND pfd.mainInscl_id IS NOT NULL
            AND p.typelive IN ('1','2','3')
            AND p.pid NOT IN (SELECT persondeath.pid FROM persondeath)
            AND p.nation='99'";

    if(!empty($filter_main)){
        $sql .= " AND pfd.mainInscl_id = '" . $mysqli->real_escape_string($filter_main) . "'";
    }
    if(!empty($filter_sub)){
        $sql .= " AND pfd.subInscl_id = '" . $mysqli->real_escape_string($filter_sub) . "'";
    }
    if(!empty($filter_hosp_main)){
        $sql .= " AND pfd.hospMain_hcode = '" . $mysqli->real_escape_string($filter_hosp_main) . "'";
    }
    if(!empty($filter_hosp_sub)){
        $sql .= " AND pfd.hospSub_hcode = '" . $mysqli->real_escape_string($filter_hosp_sub) . "'";
    }
    if(!empty($filter_fund_type)){
        $sql .= " AND pfd.fundType = '" . $mysqli->real_escape_string($filter_fund_type) . "'";
    }

    $sql .= " ORDER BY pfd.mainInscl_id, pfd.subInscl_id, pfd.checkDate DESC";

    $result = $mysqli->query($sql);
    $data = [];

    if($result){
        while($row = $result->fetch_assoc()){
            $data[] = $row;
        }
        $result->free();
    }

    $mysqli->close();

    $filename = 'fund_report_' . date('Ymd_His') . '.xls';
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
    <title>รายงานข้อมูลสิทธิการรักษา</title>
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
            max-width: 1600px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(102,126,234,0.3);
        }
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 8px;
        }
        .stat-card p {
            font-size: 14px;
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
            font-size: 14px;
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background-color: #556ee6;
            color: #fff;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 13px;
        }
        tr:nth-child(even) { background-color: #f8f9fc; }
        tr:nth-child(odd) { background-color: #ffffff; }
        tr:hover { background-color: #e8eaf6; }
        .table-wrapper {
            max-height: 600px;
            overflow: auto;
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
            font-size: 14px;
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
        .badge-uc {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge-sss {
            background: #fff3e0;
            color: #e65100;
        }
        .badge-ofc {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        .badge-lgo {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .summary-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .summary-item {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #556ee6;
        }
        .summary-item h4 {
            color: #556ee6;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .summary-item .count {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🏥 รายงานข้อมูลสิทธิการรักษา</h2>

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
        $filter_main = $_GET['main'] ?? '';
        $filter_sub = $_GET['sub'] ?? '';
        $filter_hosp_main = $_GET['hosp_main'] ?? '';
        $filter_hosp_sub = $_GET['hosp_sub'] ?? '';
        $filter_fund_type = $_GET['fund_type'] ?? '';

        // ดึงรายการสิทธิหลักทั้งหมด
        $main_list = [];
        $result_main = $mysqli->query("SELECT DISTINCT mainInscl_id, mainInscl_name FROM personfunddetail WHERE mainInscl_id IS NOT NULL ORDER BY mainInscl_id");
        if($result_main){
            while($row = $result_main->fetch_assoc()){
                $main_list[] = $row;
            }
            $result_main->free();
        }

        // ดึงรายการสิทธิย่อยทั้งหมด
        $sub_list = [];
        $result_sub = $mysqli->query("SELECT DISTINCT subInscl_id, subInscl_name FROM personfunddetail WHERE subInscl_id IS NOT NULL ORDER BY subInscl_id");
        if($result_sub){
            while($row = $result_sub->fetch_assoc()){
                $sub_list[] = $row;
            }
            $result_sub->free();
        }

        // ดึงรายการโรงพยาบาลหลัก
        $hosp_main_list = [];
        $result_hosp_main = $mysqli->query("SELECT DISTINCT hospMain_hcode, hospMain_hname FROM personfunddetail WHERE hospMain_hcode IS NOT NULL ORDER BY hospMain_hcode");
        if($result_hosp_main){
            while($row = $result_hosp_main->fetch_assoc()){
                $hosp_main_list[] = $row;
            }
            $result_hosp_main->free();
        }

        // สร้าง SQL พร้อมตัวกรอง
        $sql = "SELECT
                    pid, tname, fname, lname, sex_id, birthDate,
                    mainInscl_id, mainInscl_name,
                    subInscl_id, subInscl_name,
                    hospMain_hcode, hospMain_hname,
                    hospSub_hcode, hospSub_hname,
                    startDateTime, expireDateTime, fundType, checkDate
                FROM personfunddetail
                WHERE deathDate IS NULL
                AND mainInscl_id IS NOT NULL";

        if(!empty($filter_main)){
            $sql .= " AND mainInscl_id = '" . $mysqli->real_escape_string($filter_main) . "'";
        }
        if(!empty($filter_sub)){
            $sql .= " AND subInscl_id = '" . $mysqli->real_escape_string($filter_sub) . "'";
        }
        if(!empty($filter_hosp_main)){
            $sql .= " AND hospMain_hcode = '" . $mysqli->real_escape_string($filter_hosp_main) . "'";
        }
        if(!empty($filter_hosp_sub)){
            $sql .= " AND hospSub_hcode = '" . $mysqli->real_escape_string($filter_hosp_sub) . "'";
        }
        if(!empty($filter_fund_type)){
            $sql .= " AND fundType = '" . $mysqli->real_escape_string($filter_fund_type) . "'";
        }

        $sql .= " ORDER BY mainInscl_id, subInscl_id, checkDate DESC";

        $result = $mysqli->query($sql);
        $total_records = 0;
        $data = [];

        if($result){
            $total_records = $result->num_rows;
            while($row = $result->fetch_assoc()){
                $data[] = $row;
            }
            $result->free();
        }

        // สถิติสรุป
        $summary = [];
        $summary_sql = "SELECT
                            mainInscl_id, mainInscl_name, COUNT(*) as cnt
                        FROM personfunddetail
                        WHERE deathDate IS NULL
                        AND mainInscl_id IS NOT NULL
                        GROUP BY mainInscl_id, mainInscl_name
                        ORDER BY cnt DESC
                        LIMIT 10";

        $result_summary = $mysqli->query($summary_sql);
        if($result_summary){
            while($row = $result_summary->fetch_assoc()){
                $summary[] = $row;
            }
            $result_summary->free();
        }

        // นับจำนวน PID ทั้งหมด
        $total_pids = 0;
        $result_pid = $mysqli->query("SELECT COUNT(DISTINCT pid) as cnt FROM personfunddetail WHERE deathDate IS NULL AND mainInscl_id IS NOT NULL");
        if($result_pid){
            $row = $result_pid->fetch_assoc();
            $total_pids = (int)$row['cnt'];
            $result_pid->free();
        }

        $mysqli->close();
        ?>

        <!-- สถิติ -->
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($total_records); ?></h3>
                <p>Records ทั้งหมด</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <h3><?php echo number_format($total_pids); ?></h3>
                <p>PID ทั้งหมด</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);">
                <h3><?php echo number_format(count($main_list)); ?></h3>
                <p>สิทธิหลัก</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                <h3><?php echo number_format(count($hosp_main_list)); ?></h3>
                <p>โรงพยาบาล</p>
            </div>
        </div>

        <!-- สรุปตามสิทธิหลัก Top 10 -->
        <?php if(!empty($summary) && empty($filter_main)): ?>
        <div class="summary-section">
            <h3 style="margin-bottom:15px;color:#333;">📊 สรุปตามสิทธิหลัก (Top 10)</h3>
            <div class="summary-grid">
                <?php foreach($summary as $item): ?>
                <div class="summary-item">
                    <h4><?php echo htmlspecialchars($item['mainInscl_id']); ?> - <?php echo htmlspecialchars($item['mainInscl_name']); ?></h4>
                    <div class="count"><?php echo number_format($item['cnt']); ?> records</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ตัวกรองข้อมูล -->
        <form method="GET" class="filter-section">
            <div class="filter-group">
                <label>สิทธิหลัก</label>
                <select name="main">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($main_list as $item): ?>
                    <option value="<?php echo htmlspecialchars($item['mainInscl_id']); ?>"
                        <?php echo $filter_main == $item['mainInscl_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($item['mainInscl_id'] . ' - ' . $item['mainInscl_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>สิทธิย่อย</label>
                <select name="sub">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($sub_list as $item): ?>
                    <option value="<?php echo htmlspecialchars($item['subInscl_id']); ?>"
                        <?php echo $filter_sub == $item['subInscl_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($item['subInscl_id'] . ' - ' . $item['subInscl_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>โรงพยาบาลหลัก</label>
                <select name="hosp_main">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($hosp_main_list as $item): ?>
                    <option value="<?php echo htmlspecialchars($item['hospMain_hcode']); ?>"
                        <?php echo $filter_hosp_main == $item['hospMain_hcode'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($item['hospMain_hcode'] . ' - ' . $item['hospMain_hname']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>ประเภทกองทุน</label>
                <select name="fund_type">
                    <option value="">ทั้งหมด</option>
                    <option value="UCS" <?php echo $filter_fund_type == 'UCS' ? 'selected' : ''; ?>>UCS - บัตรทอง</option>
                    <option value="SSS" <?php echo $filter_fund_type == 'SSS' ? 'selected' : ''; ?>>SSS - ประกันสังคม</option>
                    <option value="OFC" <?php echo $filter_fund_type == 'OFC' ? 'selected' : ''; ?>>OFC - ข้าราชการ</option>
                    <option value="LGO" <?php echo $filter_fund_type == 'LGO' ? 'selected' : ''; ?>>LGO - อปท.</option>
                </select>
            </div>
            <div class="filter-group" style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-refresh" style="width:100%;">🔍 กรองข้อมูล</button>
            </div>
        </form>

        <!-- Action Bar -->
        <div class="action-bar">
            <div>
                <strong>ข้อมูลที่แสดง:</strong> <?php echo number_format($total_records); ?> records
            </div>
            <div style="display:flex;gap:10px;">
                <a href="?action=export<?php
                    echo !empty($filter_main) ? '&main=' . urlencode($filter_main) : '';
                    echo !empty($filter_sub) ? '&sub=' . urlencode($filter_sub) : '';
                    echo !empty($filter_hosp_main) ? '&hosp_main=' . urlencode($filter_hosp_main) : '';
                    echo !empty($filter_hosp_sub) ? '&hosp_sub=' . urlencode($filter_hosp_sub) : '';
                    echo !empty($filter_fund_type) ? '&fund_type=' . urlencode($filter_fund_type) : '';
                ?>" class="btn btn-export">
                    📥 ส่งออก Excel
                </a>
                <a href="?" class="btn btn-refresh">🔄 รีเซ็ต</a>
            </div>
        </div>

        <!-- ตารางข้อมูล -->
        <div class="table-wrapper">
            <?php if(empty($data)): ?>
                <div class="no-data">
                    ℹ️ ไม่พบข้อมูลสิทธิการรักษาตามเงื่อนไขที่เลือก
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">ลำดับ</th>
                            <th>PID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>สิทธิหลัก</th>
                            <th>สิทธิย่อย</th>
                            <th>รพ.หลัก</th>
                            <th>รพ.ย่อย</th>
                            <th style="width:110px;">วันเริ่มสิทธิ</th>
                            <th style="width:110px;">วันหมดสิทธิ</th>
                            <th style="width:80px;">Fund Type</th>
                            <th style="width:100px;">วันตรวจสอบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach($data as $row):
                            $fullname = trim(($row['tname'] ?? '') . ' ' . ($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));

                            // Badge สำหรับ Fund Type
                            $fund_badge_class = '';
                            switch($row['fundType']){
                                case 'UCS': $fund_badge_class = 'badge-uc'; break;
                                case 'SSS': $fund_badge_class = 'badge-sss'; break;
                                case 'OFC': $fund_badge_class = 'badge-ofc'; break;
                                case 'LGO': $fund_badge_class = 'badge-lgo'; break;
                                default: $fund_badge_class = 'badge';
                            }
                        ?>
                        <tr>
                            <td style="text-align:center;"><?php echo $no; ?></td>
                            <td><?php echo htmlspecialchars($row['pid'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($fullname); ?></strong></td>
                            <td>
                                <div style="font-weight:600;color:#556ee6;"><?php echo htmlspecialchars($row['mainInscl_id'] ?? ''); ?></div>
                                <small style="color:#666;"><?php echo htmlspecialchars($row['mainInscl_name'] ?? ''); ?></small>
                            </td>
                            <td>
                                <div style="font-weight:600;color:#ff9800;"><?php echo htmlspecialchars($row['subInscl_id'] ?? '-'); ?></div>
                                <small style="color:#666;"><?php echo htmlspecialchars($row['subInscl_name'] ?? ''); ?></small>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($row['hospMain_hcode'] ?? ''); ?></div>
                                <small style="color:#666;"><?php echo htmlspecialchars($row['hospMain_hname'] ?? ''); ?></small>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($row['hospSub_hcode'] ?? '-'); ?></div>
                                <small style="color:#666;"><?php echo htmlspecialchars($row['hospSub_hname'] ?? ''); ?></small>
                            </td>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($row['startDateTime'] ?? ''); ?></td>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($row['expireDateTime'] ?? ''); ?></td>
                            <td>
                                <span class="badge <?php echo $fund_badge_class; ?>">
                                    <?php echo htmlspecialchars($row['fundType'] ?? '-'); ?>
                                </span>
                            </td>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($row['checkDate'] ?? ''); ?></td>
                        </tr>
                        <?php
                        $no++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ส่วนท้าย -->
        <div style="margin-top:20px;padding:15px;background:#f8f9fa;border-radius:8px;text-align:center;color:#666;">
            <p><strong>หมายเหตุ:</strong> ข้อมูลที่แสดงเป็นเฉพาะผู้ที่ยังมีชีวิต (deathDate = NULL) และมีสิทธิการรักษา</p>
            <p style="margin-top:5px;font-size:14px;">
                <strong>Fund Types:</strong>
                <span class="badge badge-uc">UCS</span> บัตรทอง |
                <span class="badge badge-sss">SSS</span> ประกันสังคม |
                <span class="badge badge-ofc">OFC</span> ข้าราชการ |
                <span class="badge badge-lgo">LGO</span> อปท.
            </p>
        </div>
    </div>
</body>
</html>
