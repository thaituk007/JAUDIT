
<?php
set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');

$config = require 'config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
		DROP TABLE IF EXISTS hdc_nhso;
		
        CREATE TABLE IF NOT EXISTS hdc_nhso (
            Person_ID VARCHAR(13) PRIMARY KEY,
            Title VARCHAR(50),
            Fname VARCHAR(100),
            Lname VARCHAR(100),
            Sex CHAR(1),
            BirthDate DATE,
            Nation VARCHAR(10),
            Status VARCHAR(10),
            StatusName VARCHAR(100),
            Purchase VARCHAR(100),
            Chat VARCHAR(100),
            Province_Name VARCHAR(100),
            Amphur_name VARCHAR(100),
            Tumbon_name VARCHAR(100),
            Moo VARCHAR(10),
            MooBan_Name VARCHAR(100),
            Pttype VARCHAR(50),
            MasterCupID VARCHAR(20),
            MainInscl VARCHAR(20),
            MainInscl_Name VARCHAR(100),
            SubInscl VARCHAR(20),
            SubInscl_Name VARCHAR(100),
            Card_ID VARCHAR(20),
            HMain VARCHAR(20),
            HMain_Name VARCHAR(100),
            HMainOP VARCHAR(20),
            HSub VARCHAR(20),
            HSub_Name VARCHAR(100),
            StartDate DATE,
            ExpDate DATE,
            Remark TEXT,
            LastUpdate TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";

    $pdo->exec($sql);
    $message = "✅ สร้างตาราง `hdc_nhso` สำเร็จเรียบร้อยแล้ว";
} catch (PDOException $e) {
    $message = "❌ เกิดข้อผิดพลาดในการสร้างตาราง: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สร้างตาราง hdc_nhso</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding: 40px;
        }
        .result {
            background-color: #ffffff;
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
            font-size: 18px;
        }
        .success {
            color: green;
        }
        .error {
            color: crimson;
        }
    </style>
</head>
<body>
    <div class="result <?php echo strpos($message, '✅') === 0 ? 'success' : 'error'; ?>">
        <?php echo nl2br(htmlspecialchars($message)); ?>
    </div>
</body>
</html>
