<?php
set_time_limit(0);

// โหลด config
$config = require __DIR__ . '/config.php';

$user_person_id = $config['nhso_cid'];    // ใช้เลขบัตรเจ้าหน้าที่
$smctoken = $config['nhso_token'];        // ใช้ token ที่ได้มา

$wsdl = "http://ucws.nhso.go.th:80/ucwstokenp1/UCWSTokenP1?wsdl";
$cidFile = __DIR__ . '/cid.txt';

if (!file_exists($cidFile)) {
    exit("<p style='color:red;'>❌ ไม่พบไฟล์ cid.txt</p>");
}

try {
    $client = new SoapClient($wsdl, [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
    ]);
} catch (SoapFault $e) {
    exit("<p style='color:red;'>❌ สร้าง SOAP client ไม่สำเร็จ: {$e->getMessage()}</p>");
}

$lines = file($cidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) {
    exit("<p style='color:red;'>⚠️ ไฟล์ว่างหรืออ่านไม่สำเร็จ</p>");
}

echo "<h2>ผลลัพธ์การตรวจสอบสิทธิ์:</h2>";
foreach ($lines as $index => $person_id) {
    $person_id = trim($person_id);
    if (!preg_match('/^\d{13}$/', $person_id)) {
        echo "<p style='color:orange;'>❗️ CID ไม่ถูกต้อง: {$person_id}</p>";
        continue;
    }

    $params = [
        'user_person_id' => $user_person_id,
        'smctoken' => $smctoken,
        'person_id' => $person_id,
    ];

    try {
        $response = $client->__soapCall('searchCurrentByPID', [$params]);
        echo "<h4>#".($index+1).". {$person_id}</h4><pre>";
        print_r($response);
        echo "</pre>";
    } catch (SoapFault $e) {
        echo "<p style='color:red;'>❌ {$person_id} : " . $e->getMessage() . "</p>";
    }
}
?>
