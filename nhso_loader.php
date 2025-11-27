<?php
// nhso_loader.php

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);
$config = require 'config.php';

// เชื่อมต่อฐานข้อมูล
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("<p style='color:red; text-align:center;'>❌ เชื่อมต่อฐานข้อมูลล้มเหลว: " . htmlspecialchars($e->getMessage()) . "</p>");
}


// Load NHSO credentials from DB
function get_sys_var($name) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT sys_value FROM sys_var WHERE sys_name = ?");
    $stmt->execute(array($name));
    $value = $stmt->fetchColumn();
    return $value;
}

$nhsoUser = get_sys_var($pdo, 'nhso_user');
$nhsoPass = get_sys_var($pdo, 'nhso_password');

// Read CID file
$cidFile = 'C:/Temp/CID.TXT';
if (!file_exists($cidFile)) {
    exit("❌ CID file not found at: $cidFile\n");
}
$cidList = file($cidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Build SOAP request
function buildSoapRequest($cid, $username, $password) {
    return <<<XML
<?xml version="1.0"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
<S:Body>
<ns2:seacrhByPid xmlns:ns2="http://rightsearch.nhso.go.th/">
    <pid>$cid</pid>
    <userName>$username</userName>
    <password>$password</password>
</ns2:seacrhByPid>
</S:Body>
</S:Envelope>
XML;
}

// Call Web Service
function callWebService($xml) {
    $url = "http://ucws.nhso.go.th/RightsSearchService/RightsSearchServiceService";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    if (curl_errno($ch)) return false;
    curl_close($ch);
    return $response;
}

// Extract <return> XML
function extractReturnXml($response) {
    $doc = new DOMDocument();
    @$doc->loadXML($response);
    $returnNodes = $doc->getElementsByTagName("return");
    return ($returnNodes->length > 0) ? $returnNodes->item(0)->nodeValue : null;
}

// Parse <return> content
function parseReturnXml($xml) {
    $data = array();
    $wrappedXml = "<data>" . $xml . "</data>";

    $doc = new DOMDocument();
    if (@$doc->loadXML($wrappedXml)) {
        $nodes = $doc->documentElement->childNodes;
        foreach ($nodes as $node) {
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $key = strtolower($node->nodeName);
                $value = $node->nodeValue;
                $data[$key] = $value;
            }
        }
    } else {
        // ถ้า XML ผิดพลาดหรือไม่สมบูรณ์
        $data['error'] = 'Invalid XML';
    }

    return $data;
}

// Insert into MySQL
function insertData($pdo, $data) {
			$fields = array(
				"person_id", "title", "fname", "lname", "sex", "birthdate", "nation",
				"status", "statusname", "purchase", "chat", "province_name", "amphur_name",
				"tumbon_name", "moo", "mooban_name", "pttype", "mastercupid", "maininscl",
				"maininscl_name", "subinscl", "subinscl_name", "card_id", "hmain",
				"hmain_name", "hmainop", "hsub", "hsub_name", "startdate", "expdate", "remark"
			);


    $placeholders = implode(",", array_fill(0, count($fields), "?"));
    $columns = implode(",", $fields);
    $types = str_repeat("s", count($fields));
    $stmt = $pdo->prepare("REPLACE INTO hdc_nhso ($columns) VALUES ($placeholders)");

	$values = array();
	foreach ($fields as $field) {
		$values[] = isset($data[$field]) ? $data[$field] : null;
	}


$params = array_merge(array($types), $values);

	// สร้าง array แบบ reference
	$tmp = array();
	foreach ($params as $key => $value) {
		$tmp[$key] = &$params[$key]; // จำเป็นต้องเป็น reference
	}

	// เรียก bind_param แบบ dynamic
	call_user_func_array(array($stmt, 'bind_param'), $tmp);
    $stmt->execute();
    $stmt->close();
}

// === START PROCESS ===
$total = count($cidList);
$success = 0;
foreach ($cidList as $i => $cid) {
    echo "[$i/$total] CID: $cid\n";
    $xml = buildSoapRequest($cid, $nhsoUser, $nhsoPass);
    $response = callWebService($xml);
    if (!$response) {
        echo "❌ Cannot contact web service\n";
        continue;
    }

    $returnXML = extractReturnXml($response);
    if (!$returnXML) {
        echo "❌ No <return> section in response\n";
        continue;
    }

    $data = parseReturnXml($returnXML);
    if (!isset($data['person_id'])) {
        echo "❌ Invalid data: no person_id\n";
        continue;
    }

    insertData($pdo, $data);
    echo "✅ Inserted Person_ID: {$data['person_id']}\n";
    $success++;
}

echo "✔️  Done: $success records inserted from $total CIDs\n";
?>
