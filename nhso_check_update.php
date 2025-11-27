<?php
set_time_limit(0);
$successCount = 0;
$failCount = 0;
$skipCount = 0;
$invalidCount = 0;
$totalCount = 0;
$percent = 0;
$startTime = microtime(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cid_file'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir);
    }

    $uploadedFile = $uploadDir . basename($_FILES['cid_file']['name']);
    if (move_uploaded_file($_FILES['cid_file']['tmp_name'], $uploadedFile)) {
        $config = require __DIR__ . '/config.php';

        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ));
        } catch (PDOException $e) {
            die("<div class='error'>❌ ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $e->getMessage() . "</div>");
        }

        $user_person_id = $config['nhso_cid'];
        $smctoken = $config['nhso_token'];
        $wsdl = "http://ucws.nhso.go.th:80/ucwstokenp1/UCWSTokenP1?wsdl";

        try {
            $client = new SoapClient($wsdl, array(
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE
            ));
        } catch (SoapFault $e) {
            die("<div class='error'>❌ WebService ไม่พร้อมใช้งาน: " . $e->getMessage() . "</div>");
        }

        function convertResponseToArray($response) {
            if (!isset($response->return)) return array();
            $src = $response->return;
            $map = array(
                'person_id' => 'Person_ID', 'title' => 'Title', 'fname' => 'Fname',
                'lname' => 'Lname', 'sex' => 'Sex', 'birthdate' => 'BirthDate',
                'nation' => 'Nation', 'status' => 'Status', 'status_name' => 'StatusName',
                'purchase' => 'Purchase', 'chat' => 'Chat', 'province_name' => 'Province_Name',
                'amphur_name' => 'Amphur_name', 'tumbon_name' => 'Tumbon_name',
                'moo' => 'Moo', 'mooBan_name' => 'MooBan_Name', 'pttype' => 'Pttype',
                'masterCupID' => 'MasterCupID', 'maininscl' => 'MainInscl',
                'maininscl_name' => 'MainInscl_Name', 'subinscl' => 'SubInscl',
                'subinscl_name' => 'SubInscl_Name', 'card_id' => 'Card_ID',
                'hmain' => 'HMain', 'hmain_name' => 'HMain_Name', 'hmainop' => 'HMainOP',
                'hsub' => 'HSub', 'hsub_name' => 'HSub_Name', 'startdate' => 'StartDate',
                'expdate' => 'ExpDate', 'remark' => 'Remark'
            );
            $result = array();
            foreach ($map as $srcKey => $dstKey) {
                $result[$dstKey] = isset($src->$srcKey) ? $src->$srcKey : null;
            }
            return $result;
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
            foreach ($data as $field => $value) {
                $stmt->bindValue(":$field", $value);
            }
            return $stmt->execute();
        }

        $lines = file($uploadedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalCount = count($lines);

        echo "<div id='progress'></div>";
        echo "<div id='summary'></div>";

        foreach ($lines as $index => $person_id) {
            $person_id = trim($person_id);
            $percent = intval((($index + 1) / $totalCount) * 100);
            echo "<script>document.getElementById('progress').innerHTML = '<b>⏳ กำลังดำเนินการ: ' + $percent + '%</b>';</script>";
            flush();
            ob_flush();

            if (!preg_match('/^\d{13}$/', $person_id)) {
                $invalidCount++;
                continue;
            }

            $params = array(
                'user_person_id' => $user_person_id,
                'smctoken' => $smctoken,
                'person_id' => $person_id
            );

            try {
                $response = $client->__soapCall('searchCurrentByPID', array($params));
                $data = convertResponseToArray($response);
                if (empty($data['Person_ID'])) {
                    $skipCount++;
                    continue;
                }

                if (updateNHSOData($pdo, $data)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (SoapFault $e) {
                $failCount++;
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        echo "<script>
            document.getElementById('progress').innerHTML = '<b>✅ ดำเนินการตรวจสอบสิทธิ์เรียบร้อยแล้ว (100%)</b>';
            document.getElementById('summary').innerHTML = `
                <h3>📊 สรุป:</h3>
                <ul>
                    <li>✔️ สำเร็จ: {$successCount}</li>
                    <li>❌ ล้มเหลว: {$failCount}</li>
                    <li>ℹ️ ไม่มีข้อมูลสิทธิ์: {$skipCount}</li>
                    <li>⚠️ รูปแบบผิด: {$invalidCount}</li>
                </ul>
                <p><strong>⏱️ ใช้เวลา:</strong> {$duration} วินาที</p>
            `;
        </script>";
    } else {
        echo "<div class='result error'>❌ ไม่สามารถอัปโหลดไฟล์ได้</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบสิทธิ์ สปสช.</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f8;
            padding: 2rem;
            color: #333;
            text-align: center;
        }
        h2, h3 { color: #1976d2; }
        .result {
            padding: 8px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .success { background: #e8f5e9; color: #2e7d32; }
        .warning { background: #fff3e0; color: #ef6c00; }
        .error { background: #ffebee; color: #c62828; }
        .info { background: #e3f2fd; color: #1565c0; }
        form {
            margin: 0 auto 2rem auto;
            padding: 1rem;
            background: #ffffff;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: fit-content;
        }
        #progress {
            font-size: 1.2rem;
            margin: 1rem 0;
            color: #444;
        }
        ul { text-align: left; display: inline-block; margin-top: 0; }
    </style>
</head>
<body>
    <h2>✔️ ตรวจสอบสิทธิ์ สปสช. ด้วยไฟล์ .txt</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>เลือกไฟล์ .txt ที่มีเลขบัตรประชาชน:</label><br>
        <input type="file" name="cid_file" accept=".txt" required><br><br>
        <button type="submit">🔍 ตรวจสอบสิทธิ์</button>
    </form>
</body>
</html>
