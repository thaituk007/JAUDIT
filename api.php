<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

require __DIR__ . '/db.php';

// ฟังก์ชันส่งออก JSON
function json_out($data, $code = 200) {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Router เบื้องต้น
$path = $_GET['path'] ?? '';
switch ($path) {

    // ดึงข้อมูลผู้ป่วยทั้งหมด (ตาราง person ใน JHCIS)
    case "patients":
        $stmt = $pdo->query("SELECT pid, idcard as cid, prename, fname, lname, birth, sex
                             FROM person
                             AND dischargetype!='1'
                             ORDER BY pid DESC LIMIT 50");
        $rows = $stmt->fetchAll();
        json_out(['patients'=>$rows]);
        break;

    // ดึงข้อมูลผู้ป่วยรายเดียว
    case "patient":
        $pid = $_GET['pid'] ?? 0;
        $stmt = $pdo->prepare("SELECT pid, idcard as cid, prename, fname, lname, birth, sex
                               FROM person WHERE pid = ?");
        $stmt->execute([$pid]);
        $row = $stmt->fetch();
        if (!$row) {
            json_out(['error'=>'ไม่พบข้อมูลผู้ป่วย'], 404);
        }
        json_out($row);
        break;

    // ตัวอย่างดึง Vital Signs ล่าสุด
    case "vitals":
        $pid = $_GET['pid'] ?? 0;
        $stmt = $pdo->prepare("SELECT v.vstdate, v.bp1, v.bp2, v.bw, v.height, v.temperature, v.pulse, v.resp
                               FROM visit v
                               WHERE v.pid = ?
                               ORDER BY v.vstdate DESC LIMIT 5");
        $stmt->execute([$pid]);
        $rows = $stmt->fetchAll();
        json_out(['vitals'=>$rows]);
        break;

    default:
        json_out(['error'=>'API path ไม่ถูกต้อง', 'usage'=>[
            '/api.php?path=patients',
            '/api.php?path=patient&pid=123',
            '/api.php?path=vitals&pid=123'
        ]], 400);
}
