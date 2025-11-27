<?php
set_time_limit(0); // ป้องกันเวลารันหมดสำหรับข้อมูลเยอะ

$config = require 'config.php';

try {
    // สร้าง DSN เชื่อม PDO
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ดึงข้อมูล CID
    $sql = "SELECT person.idcard AS CID FROM person WHERE person.dischargetype != '1' AND cid13Chk(idcard) = 't' AND nation = '99' AND person.typelive IN (1,2,3) ORDER BY idcard ASC";
    $stmt = $pdo->query($sql);
    $cids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($cids)) {
        echo "ไม่มีข้อมูล CID ที่จะส่งออก";
        exit;
    }

    // กำหนด path ไฟล์ export
    $filename = 'export_cid.txt';
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    // ตรวจสอบโฟลเดอร์เขียนได้
    if (!is_writable(__DIR__)) {
        throw new Exception("ไม่สามารถเขียนไฟล์ในโฟลเดอร์นี้: " . __DIR__);
    }

    // รวมข้อมูล CID เป็นข้อความแยกบรรทัด
    $output = implode(PHP_EOL, $cids);

    // เขียนไฟล์ (ทับไฟล์เดิมถ้ามี)
    file_put_contents($filepath, $output);

    echo "✅ ส่งออก PERSON TypeArea 1,2,3 สำเร็จ: $filename จำนวน " . count($cids) . " รายการ";

} catch (PDOException $e) {
    echo "❌ ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
    exit;
} catch (Exception $e) {
    echo "❌ ข้อผิดพลาด: " . $e->getMessage();
    exit;
}
