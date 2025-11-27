<?php
// โหลดการตั้งค่าจาก config.php
$config = require_once 'config.php';

// เชื่อมต่อฐานข้อมูล
$servername = $config['db_host'];
$port = $config['db_port'];
$username = $config['db_user'];
$password = $config['db_pass'];
$dbname = $config['db_name'];

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

$conn->set_charset("utf8");

// ดึงสถิติข้อมูล
$stats = [];

// นับจำนวนผู้เสียชีวิต
$sql = "SELECT COUNT(*) as total FROM personfunddetail WHERE deathDate IS NOT NULL";
$result = $conn->query($sql);
$stats['death_total'] = $result->fetch_assoc()['total'];

// นับจำนวนผู้เสียชีวิตที่จำหน่ายแล้ว
$sql = "SELECT COUNT(*) as total
        FROM personfunddetail pf
        LEFT JOIN person p ON pf.pid = p.idcard
        WHERE pf.deathDate IS NOT NULL
        AND p.dischargetype = '9'";
$result = $conn->query($sql);
$stats['death_discharged'] = $result->fetch_assoc()['total'];

// นับจำนวนผู้เสียชีวิตที่ยังไม่จำหน่าย
$stats['death_not_discharged'] = $stats['death_total'] - $stats['death_discharged'];

// นับจำนวนสิทธิทั้งหมด
$sql = "SELECT COUNT(DISTINCT pid) as total FROM personfunddetail";
$result = $conn->query($sql);
$stats['right_total'] = $result->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบ SRM - Single Register Management</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-size: 2.5em;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header .subtitle {
            color: #666;
            font-size: 1.1em;
            font-weight: 400;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            animation: slideUp 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
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

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
        }

        .stat-card .icon {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .stat-card .label {
            color: #666;
            font-size: 1.05em;
            font-weight: 500;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
        }

        .menu-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .menu-card:hover::before {
            opacity: 0.95;
        }

        .menu-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 45px rgba(102, 126, 234, 0.4);
        }

        .menu-card:hover .menu-content {
            color: white;
        }

        .menu-card:hover .menu-icon {
            transform: scale(1.2) rotate(5deg);
        }

        .menu-content {
            position: relative;
            z-index: 1;
            transition: color 0.3s ease;
        }

        .menu-icon {
            font-size: 4em;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .menu-title {
            font-size: 1.4em;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .menu-desc {
            font-size: 0.95em;
            opacity: 0.85;
            line-height: 1.6;
        }

        .menu-card:nth-child(1) .menu-icon { color: #667eea; }
        .menu-card:nth-child(2) .menu-icon { color: #f093fb; }
        .menu-card:nth-child(3) .menu-icon { color: #4facfe; }
        .menu-card:nth-child(4) .menu-icon { color: #fa709a; }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            margin-top: 12px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .menu-card:hover .badge {
            background: white;
            color: #667eea;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            .header {
                padding: 25px;
            }
            .stat-card {
                padding: 20px;
            }
            .menu-card {
                padding: 30px;
            }
            .menu-icon {
                font-size: 3em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 ระบบ SRM</h1>
            <p class="subtitle">Single Register Management System</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="number"><?php echo number_format($stats['right_total']); ?></div>
                <div class="label">จำนวนสิทธิทั้งหมด</div>
            </div>

            <div class="stat-card">
                <div class="icon">📊</div>
                <div class="number"><?php echo number_format($stats['death_total']); ?></div>
                <div class="label">ผู้เสียชีวิตทั้งหมด</div>
            </div>

            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="number"><?php echo number_format($stats['death_discharged']); ?></div>
                <div class="label">จำหน่ายแล้ว</div>
            </div>

            <div class="stat-card">
                <div class="icon">⚠️</div>
                <div class="number"><?php echo number_format($stats['death_not_discharged']); ?></div>
                <div class="label">ยังไม่ได้จำหน่าย</div>
            </div>
        </div>

        <div class="menu-grid">
            <div class="menu-card" onclick="window.location.href='api_right.php'">
                <div class="menu-content">
                    <div class="menu-icon">🔍</div>
                    <div class="menu-title">ตรวจสอบสิทธิ NHSO</div>
                    <div class="menu-desc">ตรวจสอบสิทธิการรักษาพยาบาลจากระบบ SRM-API ของสำนักงานหลักประกันสุขภาพแห่งชาติ</div>
                    <span class="badge">SRM-API</span>
                </div>
            </div>

            <div class="menu-card" onclick="window.location.href='death_report_srm.php'">
                <div class="menu-content">
                    <div class="menu-icon">📋</div>
                    <div class="menu-title">รายงานผู้เสียชีวิต</div>
                    <div class="menu-desc">รายงานข้อมูลผู้เสียชีวิตที่จำหน่ายในระบบ JHCIS พร้อมสถิติและข้อมูลรายละเอียด</div>
                    <span class="badge">รายงาน</span>
                </div>
            </div>

            <div class="menu-card" onclick="window.location.href='report_rights.php'">
                <div class="menu-content">
                    <div class="menu-icon">💳</div>
                    <div class="menu-title">รายงานสิทธิการรักษา</div>
                    <div class="menu-desc">รายงานข้อมูลสิทธิการรักษาพยาบาล แยกตามประเภทสิทธิและสถานะการใช้งาน</div>
                    <span class="badge">รายงาน</span>
                </div>
            </div>

            <div class="menu-card" onclick="window.location.href='death_report_srm_notdischarge.php'">
                <div class="menu-content">
                    <div class="menu-icon">⚠️</div>
                    <div class="menu-title">ผู้เสียชีวิตยังไม่จำหน่าย</div>
                    <div class="menu-desc">รายงานผู้เสียชีวิตจากระบบ SRM ที่ยังไม่ได้ทำการจำหน่ายในระบบ JHCIS</div>
                    <span class="badge">สำคัญ</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // เพิ่ม effect เมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.menu-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>
