<?php
// โหลดการตั้งค่าจาก config.php
$config = require_once 'config.php';

// เชื่อมต่อฐานข้อมูล
$servername = $config['db_host'];
$port = $config['db_port'];
$username = $config['db_user'];
$password = $config['db_pass'];
$dbname = $config['db_name'];

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ตั้งค่า charset เป็น utf8
$conn->set_charset("utf8");

// SQL Query
$sql = "SELECT p.pcucodeperson,
               p.pid,
               p.idcard AS เลขบัตรประจำตัวประชาชน,
               CONCAT(IFNULL(p.fname,''),' ',IFNULL(p.lname,'')) AS ชื่อและนามสกุล,
               DATE_FORMAT(p.birth, '%d/%m/%Y') AS วันเกิด,
               getAgeYearnum(p.birth, CURRENT_DATE) AS อายุ,
               IF(p.sex = 1, 'ชาย', 'หญิง') AS เพศ,
               p.typelive,
               p.dischargetype
        FROM personfunddetail pf
        LEFT JOIN person p ON pf.pid = p.idcard
        WHERE pf.deathDate IS NOT NULL
          AND p.dischargetype IN ('9')
          AND p.typelive IN ('1','3')
        ORDER BY p.typelive ASC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานข้อมูลผู้เสียชีวิต</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            max-width: 1600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .subtitle {
            font-size: 1.1em;
            font-weight: 300;
            opacity: 0.95;
            position: relative;
        }

        .content-wrapper {
            padding: 40px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(240, 147, 251, 0.4);
        }

        .stat-card .number {
            font-size: 3em;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .stat-card .label {
            font-size: 1.1em;
            font-weight: 400;
            opacity: 0.95;
        }

        .controls {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.05em;
            font-weight: 500;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-export {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
        }

        .btn-print {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(250, 112, 154, 0.4);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95em;
            color: #333;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: linear-gradient(90deg, #f8f9ff 0%, #fff 100%);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }

        tbody tr:nth-child(even) {
            background-color: #fafbff;
        }

        .no-data {
            text-align: center;
            padding: 80px 40px;
            color: #999;
            font-size: 1.3em;
            font-weight: 300;
        }

        .no-data::before {
            content: '📭';
            display: block;
            font-size: 4em;
            margin-bottom: 20px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-male {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge-female {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .controls, .btn {
                display: none !important;
            }
            .container {
                box-shadow: none;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 30px 20px;
            }
            h1 {
                font-size: 1.8em;
            }
            .content-wrapper {
                padding: 20px;
            }
            .stat-card .number {
                font-size: 2.5em;
            }
            table {
                font-size: 0.85em;
            }
            th, td {
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 รายงานข้อมูลผู้เสียชีวิต จากระบบ SRM ที่ยังไม่ได้จำหน่ายใน JHCIS</h1>
            <p class="subtitle">ระบบบริหารจัดการข้อมูลสาธารณสุข</p>
        </div>

        <div class="content-wrapper">
            <?php
            if ($result->num_rows > 0) {
                // นับเพศ
                $maleCount = 0;
                $femaleCount = 0;
                $data = [];

                while($row = $result->fetch_assoc()) {
                    $data[] = $row;
                    if ($row['เพศ'] == 'ชาย') {
                        $maleCount++;
                    } else {
                        $femaleCount++;
                    }
                }

                echo '<div class="stats-container">';
                echo '<div class="stat-card">';
                echo '<div class="number">' . count($data) . '</div>';
                echo '<div class="label">รายทั้งหมด</div>';
                echo '</div>';
                echo '<div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">';
                echo '<div class="number">' . $maleCount . '</div>';
                echo '<div class="label">เพศชาย</div>';
                echo '</div>';
                echo '<div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">';
                echo '<div class="number">' . $femaleCount . '</div>';
                echo '<div class="label">เพศหญิง</div>';
                echo '</div>';
                echo '</div>';

                echo '<div class="controls">';
                echo '<button class="btn btn-export" onclick="exportToCSV()">📥 ส่งออกข้อมูล Excel</button>';
                echo '<button class="btn btn-print" onclick="window.print()">🖨️ พิมพ์รายงาน</button>';
                echo '</div>';

                echo '<div class="table-container">';
                echo '<table id="dataTable">';
                echo '<thead>';
                echo '<tr>';
                echo '<th style="width: 60px;">ลำดับ</th>';
                echo '<th>รหัส PCU</th>';
                echo '<th>PID</th>';
                echo '<th>เลขบัตรประชาชน</th>';
                echo '<th>ชื่อ-นามสกุล</th>';
                echo '<th>วันเกิด</th>';
                echo '<th style="text-align: center; width: 80px;">อายุ</th>';
                echo '<th style="text-align: center; width: 100px;">เพศ</th>';
                echo '<th style="text-align: center; width: 100px;">ประเภทอยู่</th>';
                echo '<th style="text-align: center; width: 100px;">ประเภทจำหน่าย</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                $no = 1;
                foreach($data as $row) {
                    echo '<tr>';
                    echo '<td style="text-align: center; font-weight: 600;">' . $no++ . '</td>';
                    echo '<td>' . htmlspecialchars($row['pcucodeperson']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['pid']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['เลขบัตรประจำตัวประชาชน']) . '</td>';
                    echo '<td style="font-weight: 500;">' . htmlspecialchars($row['ชื่อและนามสกุล']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['วันเกิด']) . '</td>';
                    echo '<td style="text-align: center; font-weight: 600;">' . htmlspecialchars($row['อายุ']) . '</td>';

                    $sexClass = $row['เพศ'] == 'ชาย' ? 'badge-male' : 'badge-female';
                    echo '<td style="text-align: center;"><span class="badge ' . $sexClass . '">' . htmlspecialchars($row['เพศ']) . '</span></td>';

                    echo '<td style="text-align: center;">' . htmlspecialchars($row['typelive']) . '</td>';
                    echo '<td style="text-align: center;">' . htmlspecialchars($row['dischargetype']) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="no-data">ไม่พบข้อมูล</div>';
            }

            $conn->close();
            ?>
        </div>
    </div>

    <script>
        function exportToCSV() {
            let table = document.getElementById('dataTable');
            let csv = [];

            // เพิ่ม BOM สำหรับ UTF-8
            csv.push('\uFEFF');

            // หัวตาราง
            let headers = [];
            for (let th of table.querySelectorAll('thead th')) {
                headers.push('"' + th.textContent.trim() + '"');
            }
            csv.push(headers.join(','));

            // ข้อมูล
            for (let row of table.querySelectorAll('tbody tr')) {
                let rowData = [];
                for (let cell of row.querySelectorAll('td')) {
                    rowData.push('"' + cell.textContent.trim() + '"');
                }
                csv.push(rowData.join(','));
            }

            // ดาวน์โหลดไฟล์
            let csvContent = csv.join('\n');
            let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement('a');
            let url = URL.createObjectURL(blob);

            let today = new Date().toISOString().slice(0, 10);
            link.setAttribute('href', url);
            link.setAttribute('download', 'รายงานผู้เสียชีวิต_' + today + '.csv');
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
