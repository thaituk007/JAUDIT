<?php
session_start();
date_default_timezone_set("Asia/Bangkok");

$file = "online_users.json"; // ไฟล์เก็บ session

// โหลดข้อมูลเก่า
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// ลบ session ที่ไม่ active เกิน 5 นาที
$now = time();
foreach ($users as $id => $lastActive) {
    if ($now - $lastActive > 300) {
        unset($users[$id]);
    }
}

// อัปเดต session ปัจจุบัน
$users[session_id()] = $now;

// บันทึกไฟล์ใหม่
file_put_contents($file, json_encode($users));

// ส่งค่า JSON กลับไป
header("Content-Type: application/json");
echo json_encode(["online" => count($users)]);
