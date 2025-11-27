<?php
session_start();

if (empty($_SESSION['config_logged_in'])) {
    header('Location: edit_config.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit_config.php');
    exit;
}

// โหลด config เดิม
$oldConfig = require 'config.php';

$config = [];

// ฟิลด์ที่ต้องเป็นตัวเลข
$intFields = ['db_port'];

// ฟิลด์ที่ต้องเป็นวันที่ yyyy-mm-dd
$dateFields = ['dateversion'];

// ฟิลด์ที่ซ่อน (password / token) ถ้าไม่เปลี่ยน จะเก็บค่าเดิม
$maskedFieldsPatterns = ['pass', 'token'];

$errors = [];

foreach ($_POST as $key => $value) {
    $val = trim($value);

    // ตรวจสอบ password / token ถ้าเป็น ●●● (Unicode bullet) ให้เก็บค่าเดิม
    foreach ($maskedFieldsPatterns as $pattern) {
        if (strpos($key, $pattern) !== false && preg_match('/^•+$/u', $val)) {
            $config[$key] = $oldConfig[$key] ?? '';
            continue 2; // ข้ามไป key ถัดไป
        }
    }

    // ตรวจสอบชนิดข้อมูล
    if (in_array($key, $intFields)) {
        if (!ctype_digit($val)) {
            $errors[] = "ค่าของ \"$key\" ต้องเป็นตัวเลขเท่านั้น";
            continue;
        }
    } elseif (in_array($key, $dateFields)) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $errors[] = "ค่าของ \"$key\" ต้องอยู่ในรูปแบบ YYYY-MM-DD เท่านั้น";
            continue;
        }
    }

    $config[$key] = $val;
}

if (!empty($errors)) {
    // แสดง error แบบสวยงาม และกลับไปหน้าแก้ไข
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8" />
        <title>ข้อผิดพลาด - บันทึกการตั้งค่า</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
        <style>body { font-family: 'Prompt', sans-serif; }</style>
    </head>
    <body class="bg-red-50 min-h-screen flex flex-col justify-center items-center p-6">
        <div class="max-w-lg w-full bg-white p-6 rounded-lg shadow-lg border border-red-300">
            <h1 class="text-2xl font-semibold text-red-700 mb-4">❌ พบข้อผิดพลาดในการบันทึก</h1>
            <ul class="list-disc list-inside text-red-600 mb-6">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button onclick="history.back()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded transition">
                กลับไปแก้ไข
            </button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// สร้างสตริง PHP สำหรับเขียนไฟล์ config.php
$exported  = "<?php\n";
$exported .= "/**\n * Configuration file for JHCISAUDITs application\n * Updated: " . date('Y-m-d H:i:s') . "\n */\n";
$exported .= "return " . var_export($config, true) . ";\n";

// เขียนไฟล์ config.php
if (file_put_contents('config.php', $exported) !== false) {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8" />
        <title>บันทึกสำเร็จ</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
        <style>body { font-family: 'Prompt', sans-serif; }</style>
        <script>
            setTimeout(() => {
                window.location.href = 'edit_config.php';
            }, 1500);
        </script>
    </head>
    <body class="bg-green-50 min-h-screen flex flex-col justify-center items-center p-6">
        <div class="max-w-md w-full bg-white p-6 rounded-lg shadow-lg border border-green-300 text-center text-green-700 font-semibold text-xl">
            ✅ บันทึกการตั้งค่าสำเร็จแล้ว
        </div>
    </body>
    </html>
    <?php
} else {
    // กรณีบันทึกไฟล์ไม่สำเร็จ
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8" />
        <title>บันทึกไม่สำเร็จ</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet" />
        <style>body { font-family: 'Prompt', sans-serif; }</style>
    </head>
    <body class="bg-red-50 min-h-screen flex flex-col justify-center items-center p-6">
        <div class="max-w-md w-full bg-white p-6 rounded-lg shadow-lg border border-red-300 text-center text-red-700 font-semibold text-xl">
            ❌ ไม่สามารถบันทึกไฟล์ config.php ได้
        </div>
        <div class="mt-4 text-center">
            <button onclick="history.back()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded transition">
                กลับไปแก้ไข
            </button>
        </div>
    </body>
    </html>
    <?php
}
