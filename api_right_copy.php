<?php
// ====================================================================================
// โหลด config
// ====================================================================================
$config = include('config.php');

// ====================================================================================
// ฟังก์ชันช่วยเหลือ
// ====================================================================================

$log_file = 'nhso_log.txt';
$base_url = 'https://srm.nhso.go.th/api/ucws/v1/right-search';

// หา token.txt แบบ recursive ใน %USERPROFILE%
function find_token_file_recursive() {
    $userprofile = getenv('USERPROFILE');
    if($userprofile && is_dir($userprofile)){
        try {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($userprofile));
            foreach ($rii as $file) {
                if (!$file->isDir() && strtolower($file->getFilename()) === 'token.txt') {
                    return $file->getPathname();
                }
            }
        } catch (Exception $e){}
    }
    $path = __DIR__ . '\\token.txt';
    if(file_exists($path)) return $path;
    return null;
}

// อ่านไฟล์ token.txt และดึงเฉพาะ access-token
function read_access_token($file_path) {
    if(!file_exists($file_path)) return '';
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($lines as $line){
        $line = trim($line);
        if(strpos($line, 'access-token=') === 0){
            return trim(substr($line, strlen('access-token=')));
        }
    }
    return '';
}

// เขียน log
function write_log($message){
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file,"[$date] $message\n",FILE_APPEND);
}

// ====================================================================================
// HANDLE AJAX LOG POST
// ====================================================================================
if(isset($_POST['log_message'])){
    write_log($_POST['log_message']);
    echo 'OK';
    exit;
}

// ====================================================================================
// HANDLE AJAX SAVE TO DB
// ====================================================================================
if($_SERVER['REQUEST_METHOD']==='POST' && !empty(file_get_contents('php://input'))){
    $input = json_decode(file_get_contents('php://input'), true);
    if(isset($input['pid'],$input['data'])){
        $mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name'],$config['db_port']);
        if($mysqli->connect_errno) die("DB connect error: ".$mysqli->connect_error);

        $stmt = $mysqli->prepare("
            INSERT INTO nhso_check
            (pid, tname, fname, lname, nation, birthDate, sex, deathDate, hospMain, funds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $pid = $input['pid'];
        $data = $input['data'];
        $tname = $data['tname'] ?? null;
        $fname = $data['fname'] ?? null;
        $lname = $data['lname'] ?? null;
        $nation = $data['nation'] ?? null;
        $birthDate = $data['birthDate'] ?? null;
        $sex = $data['sex'] ?? null;
        $deathDate = $data['deathDate'] ?? null;
        $hospMain = $data['hospMain'] ?? null;
        $funds = isset($data['funds']) ? json_encode($data['funds'], JSON_UNESCAPED_UNICODE) : null;

        $stmt->bind_param(
            "ssssssssss",
            $pid, $tname, $fname, $lname, $nation, $birthDate, $sex, $deathDate, $hospMain, $funds
        );
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    }
    echo json_encode(['status'=>'OK']);
    exit;
}

// ====================================================================================
// HANDLE FILE UPLOAD OR AUTO FIND
// ====================================================================================
$access_token = '';
if(isset($_FILES['token_file']) && $_FILES['token_file']['error'] === UPLOAD_ERR_OK){
    $tmp_name = $_FILES['token_file']['tmp_name'];
    $access_token = read_access_token($tmp_name);
    $token_file = $_FILES['token_file']['name'];
} else {
    $token_file = find_token_file_recursive();
    if(!$token_file){
        echo "<h2>❌ ไม่พบไฟล์ token.txt</h2>";
        echo "<p>โปรดเลือกไฟล์ token.txt ด้วยตัวเอง:</p>";
        echo '<form method="POST" enctype="multipart/form-data">';
        echo '<input type="file" name="token_file" accept=".txt" required style="padding:8px;">';
        echo '<button type="submit" style="padding:8px 16px; background:#4CAF50; color:#fff; border:none; border-radius:5px; cursor:pointer;">อัปโหลดและตรวจสอบ</button>';
        echo '</form>';
        exit;
    }
    $access_token = read_access_token($token_file);
}

if(empty($access_token)) die("❌ Error: ไฟล์ token.txt ไม่มี access-token หรือว่าง");

// ====================================================================================
// HTML Header + CSS Modern
// ====================================================================================
echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
echo '<title>NHSO Batch Check</title>';
echo '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">';
echo '<style>
body { font-family:"Prompt", sans-serif; background:#f4f7fa; color:#333; padding:20px;}
h2 { color:#333;}
table { width:100%; border-collapse: separate; border-spacing:0; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
th, td { padding:12px 15px; text-align:left;}
th { background-color:#556ee6; color:#fff; font-weight:600;}
tr:nth-child(even){ background-color:#f2f4f8;}
tr:nth-child(odd){ background-color:#ffffff;}
pre { background:#f0f4f8; padding:10px; border-radius:8px; overflow-x:auto; font-size:14px; color:#333;}
.status-success {color:#28a745; font-weight:500;}
.status-warning {color:#ff9800; font-weight:500;}
.status-error {color:#e53e3e; font-weight:500;}
button { font-family:"Prompt", sans-serif;}
#progress { margin-bottom:15px; font-weight:500;}
</style></head><body>';

echo "<h2>ผลการเรียก API ตรวจสอบสิทธิ NHSO (Batch)</h2>";
echo "<p><strong>Token:</strong> ใช้จาก <code>{$token_file}</code> สำเร็จ</p>";
echo '<div id="progress">ตรวจสอบ PID: <span id="checked">0</span> / <span id="total">0</span></div>';
echo '<table id="result_table"><tr><th>PID</th><th>Status</th><th>Detail / Response</th></tr>';

// ====================================================================================
// ดึง PID จากฐานข้อมูล
// ====================================================================================
$mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name'],$config['db_port']);
if ($mysqli->connect_errno) die("❌ MySQL Connection Error: ".$mysqli->connect_error);

$pids = [];
$sql = "SELECT idcard AS cid FROM person WHERE cid13Chk(idcard)='t' AND nation='99' LIMIT 10"; // ปรับ limit ตามต้องการ
if($result = $mysqli->query($sql)){
    while($row = $result->fetch_assoc()) $pids[] = $row['cid'];
    $result->free();
} else die("❌ SQL Error: ".$mysqli->error);
$total_pids = count($pids);
$mysqli->close();

echo '<script>
const pids = '.json_encode($pids).';
document.getElementById("total").innerText = pids.length;
const access_token = "'.$access_token.'";
const base_url = "'.$base_url.'";
const concurrent = 10; // จำนวน request พร้อมกัน

async function sendLog(message){
    await fetch("",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"log_message="+encodeURIComponent(message)
    });
}

async function saveResultToDB(pid,data){
    await fetch("",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body: JSON.stringify({pid:pid,data:data})
    });
}

async function checkPID(pid){
    let max_retry=3, attempt=0, result=null;
    do{
        attempt++;
        try{
            const resp = await fetch(base_url+"?pid="+pid,{
                headers:{ "Authorization":"Bearer "+access_token,"Accept":"application/json" }
            });
            const text = await resp.text();
            result={http_code:resp.status,response:text,curl_error:null};
            if(resp.status>=500) await new Promise(r=>setTimeout(r,500));
            else break;
        }catch(e){ result={curl_error:e.message}; await new Promise(r=>setTimeout(r,500)); }
    }while(attempt<max_retry);

    let status="", detail="", status_class="", data_for_db={};
    if(result.curl_error){
        status="❌ cURL Error";
        detail=result.curl_error;
        status_class="status-error";
    } else if(result.http_code!==200){
        status="⚠️ HTTP "+result.http_code;
        detail=result.response;
        status_class="status-warning";
        if(result.http_code===401) status+=" - 🛑 Unauthorized (Token หมดอายุ)";
    } else {
        try{
            const data=JSON.parse(result.response);
            status="✅ Success";
            detail=JSON.stringify(data,null,2);
            status_class="status-success";

            data_for_db = {
                tname: data.tname ?? null,
                fname: data.fname ?? null,
                lname: data.lname ?? null,
                nation: data.nation ?? null,
                birthDate: data.birthDate ?? null,
                sex: data.sex ?? null,
                deathDate: data.deathDate ?? null,
                hospMain: data.hospMain ?? null,
                funds: data.funds ?? null
            };
            await saveResultToDB(pid, data_for_db);

        } catch(e){
            status="⚠️ JSON Decode Error";
            detail=result.response;
            status_class="status-warning";
        }
    }

    const table = document.getElementById("result_table");
    const tr = document.createElement("tr");
    tr.innerHTML=`<td>${pid}</td><td class="${status_class}">${status}</td><td><pre>${detail}</pre></td>`;
    table.appendChild(tr);

    document.getElementById("checked").innerText=parseInt(document.getElementById("checked").innerText)+1;
    sendLog(pid+" - "+status);
}

async function runBatch(){
    for(let i=0;i<pids.length;i+=concurrent){
        const batch = pids.slice(i,i+concurrent);
        await Promise.all(batch.map(pid=>checkPID(pid)));
    }
}

runBatch();
</script>';

echo "</body></html>";
?>
