<?php
set_time_limit(0);
$config = require __DIR__ . '/config.php';

$dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("❌ ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

$user_person_id = $config['nhso_cid'];
$smctoken = $config['nhso_token'];
$wsdl = "http://ucws.nhso.go.th:80/ucwstokenp1/UCWSTokenP1?wsdl";

$cidFile = __DIR__ . '/export_cid.txt';
if (!file_exists($cidFile)) {
    die("❌ ไม่พบไฟล์ export_cid.txt");
}

try {
    $client = new SoapClient($wsdl, [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE
    ]);
} catch (SoapFault $e) {
    die("❌ ไม่สามารถเชื่อมต่อ Web Service: " . $e->getMessage());
}

function convertResponseToArray($response) {
    $fields = [
        'Person_ID', 'Title', 'Fname', 'Lname', 'Sex', 'BirthDate', 'Nation',
        'Status', 'StatusName', 'Purchase', 'Chat', 'Province_Name',
        'Amphur_name', 'Tumbon_name', 'Moo', 'MooBan_Name', 'Pttype',
        'MasterCupID', 'MainInscl', 'MainInscl_Name', 'SubInscl', 'SubInscl_Name',
        'Card_ID', 'HMain', 'HMain_Name', 'HMainOP', 'HSub', 'HSub_Name',
        'StartDate', 'ExpDate', 'Remark'
    ];
    $result = [];
    foreach ($fields as $field) {
        $result[$field] = property_exists($response, $field) ? $response->$field : null;
    }
    return empty($result['Person_ID']) ? null : $result;
}

function updateNHSOData($pdo, $data) {
    $sql = "REPLACE INTO hdc_nhso (
        Person_ID, Title, Fname, Lname, Sex, BirthDate, Nation,
        Status, StatusName, Purchase, Chat, Province_Name,
        Amphur_name, Tumbon_name, Moo, MooBan_Name, Pttype,
        MasterCupID, MainInscl, MainInscl_Name, SubInscl, SubInscl_Name,
        Card_ID, HMain, HMain_Name, HMainOP, HSub, HSub_Name,
        StartDate, ExpDate, Remark
    ) VALUES (
        :Person_ID, :Title, :Fname, :Lname, :Sex, :BirthDate, :Nation,
        :Status, :StatusName, :Purchase, :Chat, :Province_Name,
        :Amphur_name, :Tumbon_name, :Moo, :MooBan_Name, :Pttype,
        :MasterCupID, :MainInscl, :MainInscl_Name, :SubInscl, :SubInscl_Name,
        :Card_ID, :HMain, :HMain_Name, :HMainOP, :HSub, :HSub_Name,
        :StartDate, :ExpDate, :Remark
    )";
    $stmt = $pdo->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    return $stmt->execute();
}

$lines = file($cidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) {
    die("❌ ไฟล์ว่างหรืออ่านไม่สำเร็จ");
}

$start = microtime(true);

$total = count($lines);
$success = 0;
$fail = 0;
$noData = 0;
$invalid = 0;

$results = [];  // สำหรับ export
$logLines = [];

echo "<h2>🔍 ผลลัพธ์การตรวจสอบสิทธิ์:</h2>";

foreach ($lines as $index => $person_id) {
    $person_id = trim($person_id);
    $result = [
        'index' => $index + 1,
        'cid' => $person_id,
        'status' => '',
        'message' => ''
    ];

    if (!preg_match('/^\d{13}$/', $person_id)) {
        echo "<p style='color:orange;'>⚠️ #{$result['index']} เลขบัตรไม่ถูกต้อง: {$person_id}</p>";
        $result['status'] = 'invalid';
        $result['message'] = 'รูปแบบไม่ถูกต้อง';
        $invalid++;
        $results[] = $result;
        $logLines[] = "[⚠️] {$person_id} - รูปแบบผิด";
        continue;
    }

    $params = [
        'user_person_id' => $user_person_id,
        'smctoken' => $smctoken,
        'person_id' => $person_id
    ];

    try {
        $response = $client->__soapCall('searchCurrentByPID', [$params]);
        $data = convertResponseToArray($response);

        if (is_null($data)) {
            echo "<p style='color:gray;'>ℹ️ #{$result['index']} {$person_id} ไม่พบข้อมูล Person_ID</p>";
            $result['status'] = 'nodata';
            $result['message'] = 'ไม่พบข้อมูล Person_ID';
            $noData++;
            $results[] = $result;
            $logLines[] = "[ℹ️] {$person_id} - ไม่พบข้อมูล";
            continue;
        }

        if (updateNHSOData($pdo, $data)) {
            echo "<p style='color:green;'>✔️ #{$result['index']} {$person_id} บันทึกสำเร็จ</p>";
            $result['status'] = 'success';
            $result['message'] = 'บันทึกสำเร็จ';
            $success++;
            $logLines[] = "[✔️] {$person_id} - สำเร็จ";
        } else {
            echo "<p style='color:red;'>❌ #{$result['index']} {$person_id} บันทึกล้มเหลว</p>";
            $result['status'] = 'fail';
            $result['message'] = 'บันทึกล้มเหลว';
            $fail++;
            $logLines[] = "[❌] {$person_id} - บันทึกล้มเหลว";
        }
    } catch (SoapFault $e) {
        echo "<p style='color:red;'>❌ #{$result['index']} {$person_id} : " . $e->getMessage() . "</p>";
        $result['status'] = 'fail';
        $result['message'] = $e->getMessage();
        $fail++;
        $logLines[] = "[❌] {$person_id} - SOAP Error: " . $e->getMessage();
    }

    $results[] = $result;
}

$end = microtime(true);
$duration = round($end - $start, 2);

echo "<hr><h3>📊 สรุป:</h3><ul>";
echo "<li>✔️ สำเร็จ: {$success}</li>";
echo "<li>❌ ล้มเหลว: {$fail}</li>";
echo "<li>ℹ️ ไม่มีข้อมูลสิทธิ์: {$noData}</li>";
echo "<li>⚠️ รูปแบบผิด: {$invalid}</li>";
echo "<li>⏱️ ใช้เวลา: {$duration} วินาที</li>";
echo "</ul>";

// Export CSV
$csvFile = fopen(__DIR__ . '/nhso_result.csv', 'w');
fputcsv($csvFile, ['#', 'CID', 'สถานะ', 'ข้อความ']);
foreach ($results as $row) {
    fputcsv($csvFile, [$row['index'], $row['cid'], $row['status'], $row['message']]);
}
fclose($csvFile);

// Export JSON
file_put_contents(__DIR__ . '/nhso_result.json', json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Export Log
file_put_contents(__DIR__ . '/nhso_result.log', implode(PHP_EOL, $logLines));

echo "<p>📁 ส่งออกข้อมูลเรียบร้อย: <code>nhso_result.csv</code>, <code>nhso_result.json</code>, <code>nhso_result.log</code></p>";
?>
