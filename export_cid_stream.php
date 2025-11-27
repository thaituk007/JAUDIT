<?php
$config = require 'config.php';

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT person.idcard AS CID FROM person WHERE person.dischargetype !='1' AND cid13Chk(idcard)='t' AND nation='99'");
    $cids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($cids)) {
        http_response_code(204); // No Content
        exit;
    }

    // สร้างไฟล์ใหม่หรือลบทับของเก่า
    $output = implode(PHP_EOL, $cids);
    $filename = 'export_cid.txt';
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    // เขียนลงไฟล์ก่อน (จะลบทับไฟล์เดิมหากมีอยู่)
    file_put_contents($filepath, $output);

    // เตรียม header เพื่อให้เบราว์เซอร์ดาวน์โหลด
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));

    // ส่งเนื้อหาไฟล์ออกไป
    readfile($filepath);

    // (ถ้าต้องการลบไฟล์หลังส่ง สามารถใช้ unlink($filepath); ด้านล่างนี้)
    // unlink($filepath);

} catch (PDOException $e) {
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
}
