<?php
set_time_limit(0);
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --- โหลด config ---
$config = include('config.php');

// --- ฟังก์ชัน log ---
function logMessage($msg) {
    $time = date('Y-m-d H:i:s');
    echo "[$time] $msg<br>";
}

// --- ฟังก์ชันค้นหา token.txt แบบ recursive ---
function findTokenRecursive($dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (strtolower($file->getFilename()) === 'token.txt') {
            return $file->getPathname();
        }
    }
    return false;
}

// --- ฟังก์ชันอ่าน access token ---
function getAccessToken($config) {
    // ถ้า config มี token จริง ให้ใช้
    if(!empty($config['nhso_token']) && $config['nhso_token']!=='00000000000') {
        return $config['nhso_token'];
    }

    // หา token.txt ใน C:\Users
    $usersDir = "C:\\Users";
    $tokenPath = findTokenRecursive($usersDir);
    if ($tokenPath) {
        $content = trim(file_get_contents($tokenPath));
        if (strpos($content,'access-token=')===0) {
            return trim(substr($content, strlen('access-token=')));
        }
        return $content;
    }

    // ถ้าไม่เจอ สร้างไฟล์ตัวอย่าง
    $tokenPath = __DIR__ . '\\token.txt';
    if (!file_exists($tokenPath)) {
        file_put_contents($tokenPath,'access-token=ใส่-token-จริงที่นี่');
        logMessage("ไม่พบ token.txt, สร้างไฟล์ตัวอย่างที่: $tokenPath");
    }
    die("ไม่พบ token.txt กรุณาใส่ access-token ลงในไฟล์ token.txt\n");
}

// --- อ่าน token ---
$accessToken = getAccessToken($config);
logMessage("ใช้ access token: ".substr($accessToken,0,20)."...");

// --- เชื่อมต่อ Database จาก config.php ---
$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
if($mysqli->connect_error) die("Connect Error: ".$mysqli->connect_error);

// --- ฟังก์ชันเรียก API batch ---
function checkRightBatch($cidBatch, $accessToken){
    $apiUrl = "https://srm.nhso.go.th/api/ucws/v1/right-search";
    $cids = implode(',', $cidBatch);
    $url = $apiUrl . "?cid=" . urlencode($cids);

    $attempt=0; $maxRetries=3;
    do{
        $attempt++;
        $ch = curl_init($url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_HTTPHEADER,[
            "Authorization: Bearer $accessToken",
            "Accept: application/json"
        ]);
        curl_setopt($ch,CURLOPT_TIMEOUT,120);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($response!==false && $httpCode==200){
            $data = json_decode($response,true);
            return $data['Data'] ?? [];
        } else {
            sleep(pow(2,$attempt));
        }
    }while($attempt<$maxRetries);

    return [];
}

// --- ดึง CID จาก DB ---
$query = "SELECT idcard AS cid FROM person WHERE cid13Chk(idcard)='t' AND nation='99' LIMIT 500";
$result = $mysqli->query($query);
if(!$result) die("Query Error: ".$mysqli->error);

$cidList=[];
while($row=$result->fetch_assoc()) $cidList[]=$row['cid'];

// --- ตรวจสอบสิทธิ API batch 500 ---
$batches = array_chunk($cidList,500);
$allData=[];
foreach($batches as $batch){
    $batchResult = checkRightBatch($batch,$accessToken);
    foreach($batchResult as $item) $allData[]=$item;
}

// --- Export Excel ---
if(isset($_POST['export_excel'])){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('RightCheck');

    $headers=['CID','รหัสสิทธิหลัก','ชื่อสิทธิหลัก','รหัสสิทธิย่อย','ชื่อสิทธิย่อย','วันที่ตรวจสอบสิทธิ','สถานะการเสียชีวิต'];
    $sheet->fromArray($headers,NULL,'A1');

    $rowNum=2;
    foreach($allData as $item){
        $sheet->setCellValue("A$rowNum",$item['cid'] ?? '');
        $sheet->setCellValue("B$rowNum",$item['maininscl'] ?? '');
        $sheet->setCellValue("C$rowNum",$item['maininsclname'] ?? '');
        $sheet->setCellValue("D$rowNum",$item['subinscl'] ?? '');
        $sheet->setCellValue("E$rowNum",$item['subinsclname'] ?? '');
        $sheet->setCellValue("F$rowNum",$item['lastcheckdate'] ?? '');
        $sheet->setCellValue("G$rowNum",$item['death_status'] ?? '');
        $rowNum++;
    }

    $filename="RightCheck_".date('Ymd_His').".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');

    $writer=new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
