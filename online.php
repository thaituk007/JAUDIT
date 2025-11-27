<?php
session_start();
date_default_timezone_set("Asia/Bangkok");

$config = include 'config.php';

// DB connect
$dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "DB connect failed"]);
    exit;
}

// create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS online_users (
    session_id VARCHAR(255) PRIMARY KEY,
    last_activity INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

// current session
$sid = session_id();
$time = time();
$timeout = 60 * 2; // 2 นาที

// update or insert current session
$stmt = $pdo->prepare("REPLACE INTO online_users (session_id, last_activity) VALUES (:sid, :time)");
$stmt->execute([':sid' => $sid, ':time' => $time]);

// delete expired
$pdo->prepare("DELETE FROM online_users WHERE last_activity < :expire")
    ->execute([':expire' => $time - $timeout]);

// count online
$count = $pdo->query("SELECT COUNT(*) FROM online_users")->fetchColumn();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(["success" => true, "online" => (int)$count]);
