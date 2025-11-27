<?php
// create_pcperson_table.php
$config = include 'config.php';

$message = '';
$error = false;

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "
    -- สร้างตาราง pcperson ใหม่
    CREATE TABLE `pcperson` (
      `hospcode` VARCHAR(5) NOT NULL COMMENT 'รหัสหน่วยบริการ',
      `cid` VARCHAR(128) NOT NULL COMMENT 'เลขประจำตัวประชาชน (เข้ารหัส base64)',
      `pid` INT(11) DEFAULT NULL COMMENT 'ลำดับคนไข้',
      `hid` INT(11) DEFAULT NULL COMMENT 'รหัสครัวเรือน',
      `prename` VARCHAR(10) DEFAULT NULL COMMENT 'คำนำหน้า',
      `name` VARCHAR(128) DEFAULT NULL COMMENT 'ชื่อ (เข้ารหัส base64)',
      `lname` VARCHAR(128) DEFAULT NULL COMMENT 'นามสกุล (เข้ารหัส base64)',
      `hn` VARCHAR(20) DEFAULT NULL COMMENT 'HN',
      `sex` TINYINT(1) DEFAULT NULL COMMENT 'เพศ',
      `birth` DATE DEFAULT NULL COMMENT 'วันเกิด',
      `mstatus` TINYINT(1) DEFAULT NULL COMMENT 'สถานภาพสมรส',
      `occupation_old` VARCHAR(10) DEFAULT NULL COMMENT 'อาชีพเก่า',
      `occupation_new` VARCHAR(10) DEFAULT NULL COMMENT 'อาชีพใหม่',
      `race` VARCHAR(10) DEFAULT NULL COMMENT 'เชื้อชาติ',
      `nation` VARCHAR(10) DEFAULT NULL COMMENT 'สัญชาติ',
      `religion` VARCHAR(10) DEFAULT NULL COMMENT 'ศาสนา',
      `education` VARCHAR(10) DEFAULT NULL COMMENT 'การศึกษา',
      `fstatus` VARCHAR(10) DEFAULT NULL COMMENT 'สถานะในครอบครัว',
      `father` VARCHAR(128) DEFAULT NULL COMMENT 'พ่อ (เข้ารหัส base64)',
      `mother` VARCHAR(128) DEFAULT NULL COMMENT 'แม่ (เข้ารหัส base64)',
      `couple` VARCHAR(128) DEFAULT NULL COMMENT 'คู่สมรส (เข้ารหัส base64)',
      `vstatus` VARCHAR(10) DEFAULT NULL COMMENT 'สถานะผู้ป่วย',
      `movein` DATE DEFAULT NULL COMMENT 'วันย้ายเข้า',
      `discharge` VARCHAR(10) DEFAULT NULL COMMENT 'วันจำหน่าย',
      `ddischarge` VARCHAR(10) DEFAULT NULL COMMENT 'วันที่จำหน่าย',
      `abogroup` VARCHAR(5) DEFAULT NULL COMMENT 'กรุ๊ปเลือด ABO',
      `rhgroup` VARCHAR(5) DEFAULT NULL COMMENT 'กรุ๊ปเลือด RH',
      `labor` VARCHAR(10) DEFAULT NULL COMMENT 'งาน',
      `passport` VARCHAR(20) DEFAULT NULL COMMENT 'เลขหนังสือเดินทาง',
      `typearea` TINYINT(1) DEFAULT NULL COMMENT 'ประเภทพื้นที่',
      `d_update` DATETIME DEFAULT NULL COMMENT 'วันเวลาปรับปรุงข้อมูล',
      `telephone` VARCHAR(20) DEFAULT NULL COMMENT 'โทรศัพท์บ้าน',
      `mobile` VARCHAR(20) DEFAULT NULL COMMENT 'โทรศัพท์มือถือ',
      PRIMARY KEY (`cid`),
      KEY `idx_hospcode` (`hospcode`),
      KEY `idx_pid` (`pid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='ตารางข้อมูล PCPERSON ที่เก็บข้อมูลเข้ารหัส';
    ";

    $pdo->exec($sql);
    $message = "🎉 สร้างตาราง <strong>pcperson</strong> สำเร็จแล้ว";
} catch (PDOException $e) {
    $message = "❌ เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage());
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>สร้างตาราง PCPERSON</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background: #f0f5fa;
    margin: 0; padding: 0;
    display: flex;
    height: 100vh;
    justify-content: center;
    align-items: center;
  }
  .container {
    background: #fff;
    padding: 2.5rem 3rem;
    border-radius: 1rem;
    box-shadow: 0 8px 20px rgba(43, 123, 185, 0.25);
    max-width: 480px;
    width: 90%;
    text-align: center;
  }
  h1 {
    color: #2b7bb9;
    margin-bottom: 1rem;
  }
  p {
    font-size: 1.2rem;
    margin-top: 1rem;
    color: <?= $error ? '#d9534f' : '#28a745' ?>;
  }
  a.button {
    display: inline-block;
    margin-top: 2rem;
    background: #2b7bb9;
    color: #fff;
    padding: 0.7rem 2rem;
    border-radius: 0.6rem;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.3s ease;
  }
  a.button:hover {
    background: #1b5f90;
  }
  footer {
    margin-top: 3rem;
    font-size: 0.9rem;
    color: #666;
  }
</style>
</head>
<body>
  <div class="container">
    <h1>สร้างตาราง PCPERSON</h1>
    <p><?= $message ?></p>
    <a href="import_person.php" class="button" role="button" aria-label="ไปยังหน้าการนำเข้า">➡️ ไปยังหน้าการนำเข้า</a>
    <footer>© <?= date('Y') ?> JHCISAUDIT Health Report & Tools</footer>
  </div>
</body>
</html>
