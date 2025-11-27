<?php
/**
 * Auto Backup Scheduler (สำหรับ cron / task)
 * รันอัตโนมัติทุกวันเวลา 02:00
 */

error_reporting(0);
date_default_timezone_set("Asia/Bangkok");

$config = require __DIR__ . '/config.php';
$backupDir = __DIR__ . '/backup/';
$emailTo   = 'your-email@example.com';
$emailFrom = 'backup-system@example.com';
$maxDays   = 1; // สำรองทุกวัน

// โหลดฟังก์ชัน backup เดียวกับใน autobackup.php
function runBackup($config, $backupDir, $maxDays, $emailTo, $emailFrom) {
    $dbHost = $config['db_host'];
    $dbPort = $config['db_port'];
    $dbName = $config['db_name'];
    $dbUser = $config['db_user'];
    $dbPass = $config['db_pass'];

    $dumpPath = trim(shell_exec('which mysqldump')) ?: 'mysqldump';
    if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

    $date = date('Ymd_His');
    $sqlFile = "{$backupDir}{$dbName}_{$date}.sql";
    $zipFile = "{$backupDir}{$dbName}_{$date}.zip";

    $dumpCmd = sprintf(
        '%s --host=%s --port=%s --user=%s --password=%s %s > "%s"',
        escapeshellcmd($dumpPath),
        escapeshellarg($dbHost),
        escapeshellarg($dbPort),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($sqlFile)
    );

    exec($dumpCmd, $out, $result);
    $messages = [];

    if ($result === 0 && file_exists($sqlFile)) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE)) {
            $zip->addFile($sqlFile, basename($sqlFile));
            $zip->close();
            unlink($sqlFile);
            $messages[] = "✅ สำรองข้อมูลสำเร็จ: " . basename($zipFile);
        } else {
            $messages[] = "❌ ZIP ไม่สำเร็จ";
        }
    } else {
        $messages[] = "❌ Backup Database ล้มเหลว";
    }

    // บันทึก Log
    file_put_contents($backupDir . 'backup_log.txt', "[".date('Y-m-d H:i:s')."] ".implode(' | ', $messages)."\n", FILE_APPEND);

    // ส่ง Email แจ้งผล
    $subject = "Auto Backup Report - {$config['app_name']}";
    $headers = "From: $emailFrom\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail($emailTo, $subject, implode("\n", $messages), $headers);
}

// เรียกฟังก์ชันทำงาน
runBackup($config, $backupDir, $maxDays, $emailTo, $emailFrom);
