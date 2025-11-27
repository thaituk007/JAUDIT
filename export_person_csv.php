<?php
// export_person_csv.php
$config = include 'config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

$stmt = $pdo->prepare("SELECT * FROM pcperson");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pcperson_export_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

if (count($data) > 0) {
    fputcsv($output, array_keys($data[0]));
}

foreach ($data as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
