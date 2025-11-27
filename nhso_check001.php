<?php
set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');

// โหลดค่าจาก config.php
$config = require 'config.php';

// ไฟล์ export CID
$cidFile = __DIR__ . '/export_cid.txt';

// รับไฟล์อัปโหลด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cidfile'])) {
    if ($_FILES['cidfile']['error'] === UPLOAD_ERR_OK) {
        if (move_uploaded_file($_FILES['cidfile']['tmp_name'], $cidFile)) {
            echo "<p class='success'>✅ อัปโหลดไฟล์เรียบร้อยแล้ว</p>";
        } else {
            echo "<p class='error'>❌ ไม่สามารถบันทึกไฟล์ได้</p>";
        }
    } else {
        echo "<p class='error'>❌ อัปโหลดไฟล์ผิดพลาด</p>";
    }
}

// ตรวจสอบไฟล์
if (!file_exists($cidFile)) {
    exit("<p class='error'>❌ ไม่พบไฟล์ export_cid.txt กรุณาอัปโหลดก่อน</p>");
}

$cidList = file($cidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$cidList) {
    exit("<p class='warning'>⚠️ ไฟล์ export_cid.txt ว่างเปล่า</p>");
}

// ฟังก์ชันช่วย
function formatDate($str) {
    return (preg_match('/^\d{8}$/', $str)) ? substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) : null;
}

function statusName($code) {
    switch ($code) {
        case '001':
        case '002':
            return 'มีสิทธิ';
        case '003':
            return 'ต่างด้าวใช้สิทธิ';
        case '004':
            return 'สิทธิ UC บัตรประชาชนซ้ำ';
        case '005':
            return 'ย้ายภูมิลำเนา';
        case '006':
            return 'เด็กแรกเกิด';
        default:
            return '';
    }
}

function buildXmlRequest($cid, $user, $pass) {
    return '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ns2:searchByPid xmlns:ns2="http://rightsearch.nhso.go.th/">
      <pid>' . htmlspecialchars($cid) . '</pid>
      <userName>' . htmlspecialchars($user) . '</userName>
      <password>' . htmlspecialchars($pass) . '</password>
    </ns2:searchByPid>
  </soap:Body>
</soap:Envelope>';
}

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("<p class='error'>❌ เชื่อมต่อฐานข้อมูลล้มเหลว: " . htmlspecialchars($e->getMessage()) . "</p>");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ตรวจสอบสิทธิ NHSO</title>
  <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Kanit', sans-serif; background: #f4f7fa; padding: 20px; }
    .container { max-width: 960px; margin: auto; background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    h1 { text-align: center; margin-bottom: 1.5rem; }
    form { text-align: center; margin-bottom: 2rem; }
    input[type="file"] { display: none; }
    label.upload-label {
        background: #3498db; color: white; padding: 10px 20px; border-radius: 5px;
        cursor: pointer; font-weight: bold;
    }
    button { padding: 10px 20px; background: #2ecc71; border: none; color: white; border-radius: 5px; font-weight: bold; cursor: pointer; }
    .success { color: #27ae60; font-weight: bold; }
    .error { color: #c0392b; font-weight: bold; }
    .warning { color: #e67e22; font-weight: bold; }
    .result { margin-bottom: 1rem; }
  </style>
</head>
<body>
<div class="container">
  <h1>📋 ตรวจสอบสิทธิ สปสช. จากไฟล์ export_cid.txt</h1>
  <form method="post" enctype="multipart/form-data">
    <label class="upload-label" for="cidfile">📂 เลือกไฟล์ .txt</label>
    <input type="file" name="cidfile" id="cidfile" accept=".txt" required>
    <button type="submit">⬆️ อัปโหลด</button>
  </form>

<?php
foreach ($cidList as $cid) {
    $cid = trim($cid);
    if (!preg_match('/^\d{13}$/', $cid)) {
        echo "<p class='warning'>⚠️ CID ไม่ถูกต้อง: {$cid}</p>";
        continue;
    }

    $xmlRequest = buildXmlRequest($cid, $config['nhso_cid'], $config['nhso_token']);

    $ch = curl_init("http://ucws.nhso.go.th:80/RightsSearchService/RightsSearchServiceService?wsdl");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xmlRequest,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "<p class='error'>❌ CURL ERROR: " . curl_error($ch) . " - CID: {$cid}</p>";
        curl_close($ch);
        continue;
    }
    curl_close($ch);

    if (!$response || !preg_match('/<return>(.*?)<\/return>/s', $response, $matches)) {
        echo "<p class='error'>❌ ไม่พบข้อมูล <return> สำหรับ CID: {$cid}</p>";
        continue;
    }

    $xml = @simplexml_load_string("<Data>{$matches[1]}</Data>");
    if (!$xml) {
        echo "<p class='warning'>⚠️ แปลง XML ไม่สำเร็จ สำหรับ CID: {$cid}</p>";
        continue;
    }

    $data = [];
    foreach ($xml as $k => $v) {
        $data[strtolower($k)] = (string)$v;
    }

    if (!isset($data['personid'])) {
        echo "<p class='error'>❌ ไม่พบ personid สำหรับ CID: {$cid}</p>";
        continue;
    }

    try {
        $stmt = $pdo->prepare("REPLACE INTO hdc_nhso (
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
        )");

        $stmt->execute([
            ':Person_ID' => isset($data['personid']) ? $data['personid'] : '',
            ':Title' => isset($data['titlename']) ? $data['titlename'] : '',
            ':Fname' => isset($data['fname']) ? $data['fname'] : '',
            ':Lname' => isset($data['lname']) ? $data['lname'] : '',
            ':Sex' => isset($data['sex']) ? $data['sex'] : '',
            ':BirthDate' => formatDate(isset($data['birthdate']) ? $data['birthdate'] : ''),
            ':Nation' => isset($data['nation']) ? $data['nation'] : '',
            ':Status' => isset($data['status']) ? $data['status'] : '',
            ':StatusName' => statusName(isset($data['status']) ? $data['status'] : ''),
            ':Purchase' => isset($data['purchaseprovincename']) ? $data['purchaseprovincename'] : '',
            ':Chat' => isset($data['chat']) ? $data['chat'] : '',
            ':Province_Name' => isset($data['provincename']) ? $data['provincename'] : '',
            ':Amphur_name' => isset($data['amphurname']) ? $data['amphurname'] : '',
            ':Tumbon_name' => isset($data['tumbonname']) ? $data['tumbonname'] : '',
            ':Moo' => isset($data['moo']) ? $data['moo'] : '',
            ':MooBan_Name' => isset($data['moobanname']) ? $data['moobanname'] : '',
            ':Pttype' => isset($data['pttype']) ? $data['pttype'] : '',
            ':MasterCupID' => isset($data['mastercupid']) ? $data['mastercupid'] : '',
            ':MainInscl' => isset($data['maininscl']) ? $data['maininscl'] : '',
            ':MainInscl_Name' => isset($data['maininsclname']) ? $data['maininsclname'] : '',
            ':SubInscl' => isset($data['subinscl']) ? $data['subinscl'] : '',
            ':SubInscl_Name' => isset($data['subinsclname']) ? $data['subinsclname'] : '',
            ':Card_ID' => isset($data['cardid']) ? $data['cardid'] : '',
            ':HMain' => isset($data['hmain']) ? $data['hmain'] : '',
            ':HMain_Name' => isset($data['hmainname']) ? $data['hmainname'] : '',
            ':HMainOP' => isset($data['hmainop']) ? $data['hmainop'] : '',
            ':HSub' => isset($data['hsub']) ? $data['hsub'] : '',
            ':HSub_Name' => isset($data['hsubname']) ? $data['hsubname'] : '',
            ':StartDate' => formatDate(isset($data['startdate']) ? $data['startdate'] : ''),
            ':ExpDate' => formatDate(isset($data['expdate']) ? $data['expdate'] : ''),
            ':Remark' => isset($data['wsstatus']) ? $data['wsstatus'] : '',
        ]);

        echo "<p class='success'>✅ บันทึกข้อมูลเรียบร้อย: " . htmlspecialchars($data['personid']) . "</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>❌ DB ERROR: " . htmlspecialchars($e->getMessage()) . " - CID: {$cid}</p>";
    }
}
?>
</div>
</body>
</html>
