<?php
set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');

$config = require 'config.php';

// โหลด CID
$cidFile = __DIR__ . '/export_cid.txt';

// ตรวจสอบและโหลดไฟล์ที่อัปโหลด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cidfile'])) {
    if ($_FILES['cidfile']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['cidfile']['tmp_name'];
        $uploadOk = move_uploaded_file($tmpName, $cidFile);
        if ($uploadOk) {
            echo "<p style='color:green; text-align:center;'>✅ อัปโหลดไฟล์ export_cid.txt เรียบร้อยแล้ว</p>";
        } else {
            echo "<p style='color:red; text-align:center;'>❌ เกิดข้อผิดพลาดในการบันทึกไฟล์</p>";
        }
    } else {
        echo "<p style='color:red; text-align:center;'>❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์</p>";
    }
}

if (!file_exists($cidFile)) {
    exit("<p style='color:red; text-align:center;'>❌ ไม่พบไฟล์ export_cid.txt กรุณาอัปโหลดไฟล์ก่อนใช้งาน</p>");
}

$cidList = file($cidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($cidList)) {
    exit("<p style='color:orange; text-align:center;'>⚠️ ไฟล์ export_cid.txt ว่างเปล่า</p>");
}

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("<p style='color:red; text-align:center;'>❌ เชื่อมต่อฐานข้อมูลล้มเหลว: " . htmlspecialchars($e->getMessage()) . "</p>");
}

// ฟังก์ชันช่วย
function formatDate($str) {
    return (preg_match('/^\d{8}$/', $str)) ? substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2) : null;
}

function statusName($code) {
    switch ($code) {
        case '001':
        case '002': return 'มีสิทธิ';
        case '003': return 'ต่างด้าวใช้สิทธิ';
        case '004': return 'สิทธิ UC บัตรประชาชนซ้ำ';
        case '005': return 'ย้ายภูมิลำเนา';
        case '006': return 'เด็กแรกเกิด';
        default: return '';
    }
}

function buildXmlRequest($cid, $user, $pass) {
    return '<?xml version="1.0"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
  <S:Body>
    <ns2:searchByPid xmlns:ns2="http://rightsearch.nhso.go.th/">
      <pid>' . htmlspecialchars($cid) . '</pid>
      <userName>' . htmlspecialchars($user) . '</userName>
      <password>' . htmlspecialchars($pass) . '</password>
    </ns2:searchByPid>
  </S:Body>
</S:Envelope>';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ดึงข้อมูลสิทธิ NHSO</title>
  <!-- โหลดฟอนต์ Kanit -->
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Kanit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f7fa;
      color: #2c3e50;
      padding: 2rem;
      font-size: 16px;
      line-height: 1.6;
    }
    h1 {
      font-weight: 600;
      font-size: 2.2rem;
      color: #34495e;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .log {
      max-width: 900px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 10px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.1);
      padding: 2rem 2.5rem;
    }

    /* ปุ่มกลุ่ม */
    .btn-group {
      display: flex;
      justify-content: center; /* กึ่งกลาง */
      align-items: center;
      gap: 16px; /* ช่องว่างระหว่างปุ่ม */
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    /* ปุ่มดาวน์โหลด */
    .btn-download {
      padding: 12px 24px;
      background-color: #2980b9;
      color: #fff;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      cursor: pointer;
      border: none;
      transition: background-color 0.3s ease;
      display: inline-block;
    }
    .btn-download:hover {
      background-color: #1f6391;
    }

    /* ฟอร์มอัปโหลดให้อยู่แนวเดียว */
    .upload-form {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin: 0;
    }

    /* ซ่อน input file จริง */
    input[type="file"] {
      display: none;
    }

    /* ปุ่ม label สำหรับเลือกไฟล์ */
    .upload-label {
      background-color: #2980b9;
      color: #fff;
      padding: 12px 18px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      user-select: none;
      transition: background-color 0.3s ease;
    }
    .upload-label:hover {
      background-color: #1f6391;
    }

    /* ปุ่มอัปโหลด */
    .btn-upload {
      padding: 12px 24px;
      background-color: #2980b9;
      color: #fff;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      transition: background-color 0.3s ease;
      height: 42px;
    }
    .btn-upload:hover {
      background-color: #1f6391;
    }

    /* ข้อความสถานะ */
    p.success {
      color: #27ae60;
      font-weight: 600;
      margin-bottom: 0.5rem;
      text-align: center;
    }
    p.error {
      color: #c0392b;
      font-weight: 600;
      margin-bottom: 0.5rem;
      text-align: center;
    }
    p.warning {
      color: #e67e22;
      font-weight: 600;
      margin-bottom: 0.5rem;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="log">
    <h1>📋 รายงานผลการดึงข้อมูลจาก สปสช.</h1>

    <div class="btn-group">
      <!-- ปุ่มดาวน์โหลดไฟล์ -->
      <a href="export_cid.txt" download class="btn-download" target="_blank" rel="noopener noreferrer">
        ⬇️ ดาวน์โหลดไฟล์ export_cid.txt
      </a>

      <!-- ฟอร์มอัปโหลดไฟล์ -->
      <form method="post" enctype="multipart/form-data" class="upload-form">
        <input type="file" name="cidfile" id="cidfile" accept=".txt" required />
        <label for="cidfile" class="upload-label">📂 เลือกไฟล์ export_cid.txt</label>
        <button type="submit" class="btn-upload">⬆️ นำเข้าไฟล์</button>
      </form>
    </div>

<?php
foreach ($cidList as $cid) {
    $cid = trim($cid);
    if ($cid === '') continue;

    $xmlRequest = buildXmlRequest($cid, $config['nhso_cid'], $config['nhso_token']);

    $ch = curl_init("http://ucws.nhso.go.th:80/RightsSearchService/RightsSearchServiceService?wsdl");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=utf-8'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "<p class='error'>❌ CID: {$cid} - CURL ERROR: " . htmlspecialchars(curl_error($ch)) . "</p>";
        curl_close($ch);
        continue;
    }

    curl_close($ch);

    if (!$response) {
        echo "<p class='error'>❌ ไม่สามารถติดต่อ Web Service สำหรับ CID: {$cid}</p>";
        continue;
    }

    if (preg_match('/<return>(.*?)<\/return>/s', $response, $matches)) {
        $xml = @simplexml_load_string("<Data>{$matches[1]}</Data>");
        if ($xml === false) {
            echo "<p class='warning'>⚠️ แปลง XML ไม่สำเร็จ สำหรับ CID: {$cid}</p>";
            continue;
        }

        $data = array();
        foreach ($xml as $key => $val) {
            $data[strtolower($key)] = (string)$val;
        }

        if (!empty($data['personid'])) {
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

                $stmt->execute(array(
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
                ));

                echo "<p class='success'>✅ บันทึกข้อมูล: " . htmlspecialchars($data['personid']) . "</p>";
            } catch (PDOException $ex) {
                echo "<p class='error'>❌ เกิดข้อผิดพลาดฐานข้อมูลสำหรับ CID: {$cid} - " . htmlspecialchars($ex->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='error'>❌ ไม่พบ PersonID ในข้อมูลสำหรับ CID: {$cid}</p>";
        }
    } else {
        echo "<p class='error'>❌ ไม่พบข้อมูล &lt;return&gt; สำหรับ CID: {$cid}</p>";
    }
}
?>
  </div>
</body>
</html>
