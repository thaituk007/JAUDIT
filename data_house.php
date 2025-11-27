<?php
$config = require 'config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT house_id, latitude, longitude, address FROM house WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
