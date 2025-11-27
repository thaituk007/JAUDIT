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

// สถิติรวม
$totalPatients = $mysqli->query("SELECT COUNT(DISTINCT pid) AS total FROM personalergic")->fetch_assoc()['total'];
$totalAllergies = $mysqli->query("SELECT COUNT(*) AS total FROM personalergic")->fetch_assoc()['total'];
$totalDrugs = $mysqli->query("SELECT COUNT(DISTINCT drugcode) AS total FROM personalergic")->fetch_assoc()['total'];
$totalVillages = $mysqli->query("SELECT COUNT(DISTINCT villcode) AS total FROM village")->fetch_assoc()['total'];

// จำนวนผู้แพ้ยาแยกตามหมู่บ้าน
$villageSQL = "
SELECT v.villno, v.villname, COUNT(*) AS total
FROM personalergic pg
JOIN person p ON pg.pcucodeperson = p.pcucodeperson AND pg.pid = p.pid
JOIN house h ON p.pcucodeperson = h.pcucode AND p.hcode = h.hcode
JOIN village v ON h.pcucode = v.pcucode AND h.villcode = v.villcode
GROUP BY v.villno
ORDER BY v.villno
";
$villageData = $mysqli->query($villageSQL);

// Top 10 ยาที่แพ้มากที่สุด
$drugSQL = "
SELECT cd.drugname, COUNT(*) AS total
FROM personalergic pg
JOIN cdrug cd ON pg.drugcode = cd.drugcode
GROUP BY cd.drugcode
ORDER BY total DESC
LIMIT 10
";
$drugData = $mysqli->query($drugSQL);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Drug Allergy Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

.btn-back {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.btn-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-home {
    padding: 12px 24px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: white;
}

.stat-icon.purple {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.green {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-icon.orange {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-icon.blue {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.stat-content h3 {
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    margin-bottom: 5px;
}

.stat-content p {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
}

.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 30px;
}

.card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.card h3 {
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-box {
    height: 400px;
    position: relative;
}

@media (max-width: 768px) {
    .header {
        padding: 20px;
    }

    .header h1 {
        font-size: 24px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .chart-grid {
        grid-template-columns: 1fr;
    }

    .chart-box {
        height: 300px;
    }
}
</style>

</head>
<body>

<div class="container">

    <div class="header">
        <h1>
            <i class="fas fa-chart-line"></i>
            Dashboard ระบบรายงานผู้แพ้ยา
        </h1>
        <div style="display: flex; gap: 12px;">
            <a href="index.php" class="btn-home">
                <i class="fas fa-home"></i> หน้าแรก
            </a>
            <a href="da_allergy_list.php" class="btn-back">
                <i class="fas fa-list"></i> รายงาน
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>จำนวนผู้ป่วย</h3>
                <p><?= number_format($totalPatients) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-allergies"></i>
            </div>
            <div class="stat-content">
                <h3>บันทึกการแพ้ทั้งหมด</h3>
                <p><?= number_format($totalAllergies) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-pills"></i>
            </div>
            <div class="stat-content">
                <h3>ยาที่มีการบันทึก</h3>
                <p><?= number_format($totalDrugs) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stat-content">
                <h3>หมู่บ้านในระบบ</h3>
                <p><?= number_format($totalVillages) ?></p>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> จำนวนผู้แพ้ยาแยกตามหมู่บ้าน</h3>
            <div class="chart-box">
                <canvas id="villChart"></canvas>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-chart-pie"></i> Top 10 ยาที่แพ้มากที่สุด</h3>
            <div class="chart-box">
                <canvas id="drugChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script>
// Chart.js Global Config
Chart.defaults.font.family = 'Prompt';
Chart.defaults.font.size = 13;

// ---- Village Chart ----
const villLabels = [
    <?php while($v = $villageData->fetch_assoc()) { echo "'หมู่ {$v['villno']}',"; } ?>
];
const villValues = [
    <?php
        $villageData->data_seek(0);
        while($v = $villageData->fetch_assoc()) { echo $v['total'] . ","; }
    ?>
];

const villGradient = document.getElementById("villChart").getContext('2d').createLinearGradient(0, 0, 0, 400);
villGradient.addColorStop(0, 'rgba(102, 126, 234, 0.8)');
villGradient.addColorStop(1, 'rgba(118, 75, 162, 0.8)');

new Chart(document.getElementById("villChart"), {
    type: 'bar',
    data: {
        labels: villLabels,
        datasets: [{
            label: 'จำนวนผู้แพ้ยา',
            data: villValues,
            backgroundColor: villGradient,
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            borderRadius: 8,
            hoverBackgroundColor: 'rgba(118, 75, 162, 0.9)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// ---- Drug Chart ----
const drugLabels = [
    <?php while($d = $drugData->fetch_assoc()) { echo "'{$d['drugname']}',"; } ?>
];
const drugValues = [
    <?php
        $drugData->data_seek(0);
        while($d = $drugData->fetch_assoc()) { echo $d['total'] . ","; }
    ?>
];

new Chart(document.getElementById("drugChart"), {
    type: 'doughnut',
    data: {
        labels: drugLabels,
        datasets: [{
            label: 'จำนวน',
            data: drugValues,
            backgroundColor: [
                'rgba(102, 126, 234, 0.8)',
                'rgba(118, 75, 162, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(168, 85, 247, 0.8)',
                'rgba(236, 72, 153, 0.8)',
                'rgba(20, 184, 166, 0.8)',
                'rgba(251, 146, 60, 0.8)'
            ],
            borderColor: 'white',
            borderWidth: 3,
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>
