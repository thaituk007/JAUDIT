<?php
$config = require 'config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ดึงข้อมูล Person ทั้งหมด
    $stmt = $pdo->query("SELECT person.idcard AS CID FROM person WHERE person.dischargetype !='1' AND cid13Chk(idcard)='t' AND nation='99'");
    $cids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($cids)) {
        echo "ไม่มีข้อมูล CID ที่จะส่งออก";
        exit;
    }

    // กำหนดไฟล์เป้าหมาย
    $filename = 'export_cid.txt';
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    // รวมข้อมูลเป็นข้อความ
    $output = implode(PHP_EOL, $cids);

    // เขียนทับไฟล์เดิม (ถ้ามี) หรือสร้างใหม่
    file_put_contents($filepath, $output);

    echo "✅ ส่งออกข้อมูลเรียบร้อยแล้ว: $filename (" . count($cids) . " รายการ)";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage();
    exit;
}
