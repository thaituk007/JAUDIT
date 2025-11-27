<?php
session_start();
set_time_limit(0);
ini_set("memory_limit", "2048M");
date_default_timezone_set('Asia/Bangkok');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . htmlspecialchars($e->getMessage()));
}

function convertDate($str)
{
    if (strlen($str) === 8) {
        return substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2);
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ลบข้อมูล nation != '099'
    if (isset($_POST['delete_nation_not_099'])) {
        try {
            $rows = $pdo->exec("DELETE FROM person_data WHERE nation <> '099'");
            $_SESSION['deleteMessage'] = "ลบข้อมูลสำเร็จ: {$rows} แถว";
        } catch (Exception $e) {
            $_SESSION['deleteMessage'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ล้างไฟล์ชั่วคราว
    if (isset($_POST['clear_session'])) {
        if (!empty($_SESSION['upload_file']) && file_exists($_SESSION['upload_file'])) {
            unlink($_SESSION['upload_file']);
        }
        unset($_SESSION['upload_file']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // นำเข้าข้อมูลแบบ batch (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'import_chunk') {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['upload_file']) || !file_exists($_SESSION['upload_file'])) {
            echo json_encode(array('error' => 'ไม่พบไฟล์ชั่วคราว'));
            exit;
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batchSize = 100000;
        $file = $_SESSION['upload_file'];
        $handle = fopen($file, 'r');

        $header = fgetcsv($handle, 0, '|');
        if (!$header) {
            echo json_encode(array('error' => 'ไม่พบ header'));
            exit;
        }

        // แปลง header เป็นตัวพิมพ์เล็ก (case-insensitive)
        $header = array_map('strtolower', $header);

        // ลบ HOSPCODE9 หากเจอ
        $hosIndex = array_search('hospcode9', $header);
        if ($hosIndex !== false) {
            unset($header[$hosIndex]);
            $header = array_values($header);
        }

        // ข้ามบรรทัด offset
        for ($i = 0; $i < $offset; $i++) {
            if (fgets($handle) === false) break;
        }

        $inserted = 0;
        $skipped = 0;
        $linesRead = 0;

        while ($linesRead < $batchSize) {
            $line = fgets($handle);
            if ($line === false) break;
            $line = trim($line);
            if ($line === '') continue;

            $cols = str_getcsv($line, '|');

            // ลบ HOSPCODE9 ตาม index ถ้ามี
            if ($hosIndex !== false && isset($cols[$hosIndex])) {
                unset($cols[$hosIndex]);
                $cols = array_values($cols);
            }

            if (count($cols) < count($header)) continue;

            $row = array_combine($header, $cols);
            if (!$row || !isset($row['cid'])) continue;

            $row['birth'] = convertDate(isset($row['birth']) ? $row['birth'] : '');

            // แปลงวันที่ว่างเป็น null
            $nullableDateFields = array('movein', 'discharge', 'ddischarge', 'd_update');
            foreach ($nullableDateFields as $field) {
                if (isset($row[$field]) && trim($row[$field]) === '') {
                    $row[$field] = null;
                }
            }

            try {
                $keys = array_keys($row);

                // สร้าง SQL สำหรับ ON DUPLICATE KEY UPDATE แบบรองรับ PHP5.6
                $filteredKeys = array_filter($keys, function($k) {
                    return $k !== 'cid';
                });

                $updateFields = array();
                foreach ($filteredKeys as $k) {
                    $updateFields[] = "`$k`=VALUES(`$k`)";
                }
                $updateStr = implode(',', $updateFields);

                $placeholders = implode(',', array_fill(0, count($keys), '?'));

                $sql = "INSERT INTO person_data (" . implode(",", $keys) . ") VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateStr";

                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($row));
                $inserted++;
            } catch (Exception $e) {
                error_log("Insert error CID={$row['cid']}: " . $e->getMessage());
                $skipped++;
            }
            $linesRead++;
        }
        fclose($handle);

        $totalLines = max(1, count(file($file)) - 1); // ลบ header ออก
        $nextOffset = $offset + $linesRead;
        $done = $nextOffset >= $totalLines;

        if ($done) {
            unlink($file);
            unset($_SESSION['upload_file']);
        }

        $response = array(
            'inserted' => $inserted,
            'skipped' => $skipped,
            'progress' => min(1, $nextOffset / $totalLines),
            'next_offset' => $nextOffset,
            'done' => $done,
            'alert' => $inserted > 100000 ? '⚠️ เพิ่มข้อมูลเกิน 100,000 รายการ กรุณาตรวจสอบระบบ' : null,
        );

        echo json_encode($response);
        exit;
    }

    // อัปโหลดไฟล์
    if (isset($_FILES['person_file'])) {
        $tmp = $_FILES['person_file']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            $_SESSION['uploadError'] = 'ไม่พบไฟล์หรือไม่สามารถอัปโหลดได้';
        } else {
            $safeName = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', basename($_FILES['person_file']['name']));
            $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('upload_') . "_$safeName";
            if (!move_uploaded_file($tmp, $target)) {
                $_SESSION['uploadError'] = 'ไม่สามารถย้ายไฟล์ไปยังโฟลเดอร์ชั่วคราวได้';
            } else {
                $_SESSION['upload_file'] = $target;
                unset($_SESSION['uploadError']);
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
