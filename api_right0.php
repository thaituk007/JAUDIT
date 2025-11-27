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
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'token.txt';
    if(file_exists($path)) return $path;
    return null;
}

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

function write_log($message){
    global $log_file;
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file,"[$date] $message\n",FILE_APPEND);
}

// ====================================================================================
// AJAX POST: log
// ====================================================================================
if(isset($_POST['log_message'])){
    write_log($_POST['log_message']);
    echo 'OK';
    exit;
}

// ====================================================================================
// AJAX POST: batch save (แก้ไขใหม่ robust)
// ====================================================================================
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($raw = file_get_contents('php://input'))){
    $input = json_decode($raw, true);
    if(!isset($input['batch']) || !is_array($input['batch'])){
        echo json_encode(['status'=>'ERROR','message'=>'invalid payload']);
        exit;
    }

    $mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name'],$config['db_port']);
    if($mysqli->connect_errno){
        write_log("DB connect error: ".$mysqli->connect_error);
        echo json_encode(['status'=>'ERROR','message'=>'DB connect error']);
        exit;
    }
    $mysqli->set_charset('utf8mb4');

    $insertSql = "
        INSERT INTO personfunddetail
        (pid,checkDate,tname,fname,lname,nation_id,birthDate,sex_id,deathDate,ransDate,
         fundType,mainInscl_id,mainInscl_name,subInscl_id,subInscl_name,
         startDateTime,expireDateTime,paidModel,hospMainOp_hcode,hospMainOp_hname,
         hospSub_hcode,hospSub_hname,hospMain_hcode,hospMain_hname)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ";
    $stmt = $mysqli->prepare($insertSql);
    if(!$stmt){
        write_log("Prepare failed: ".$mysqli->error);
        echo json_encode(['status'=>'ERROR','message'=>'Prepare failed','error'=>$mysqli->error]);
        $mysqli->close();
        exit;
    }

    $inserted = 0;
    $failed = 0;

    foreach($input['batch'] as $itemIndex => $item){
        $pid = $item['pid'] ?? null;
        $data = $item['data'] ?? [];

        $checkDate = date('Y-m-d H:i:s');
        $tname = $data['tname'] ?? null;
        $fname = $data['fname'] ?? null;
        $lname = $data['lname'] ?? null;
        $nation = $data['nation'] ?? null;
        $birthDate = $data['birthDate'] ?? null;
        $sex = $data['sex'] ?? null;
        $deathDate = $data['deathDate'] ?? null;

        if(isset($data['funds']) && is_array($data['funds']) && count($data['funds'])>0){
            foreach($data['funds'] as $fund){
                $ransDate = $fund['transDate'] ?? null;
                $fundType = $fund['fundType'] ?? 'N';
                $mainInscl_id = $fund['mainInscl_id'] ?? null;
                $mainInscl_name = $fund['mainInscl_name'] ?? null;
                $subInscl_id = $fund['subInscl_id'] ?? null;
                $subInscl_name = $fund['subInscl_name'] ?? null;
                $startDateTime = $fund['startDateTime'] ?? null;
                $expireDateTime = $fund['expireDateTime'] ?? null;
                $paidModel = $fund['paidModel'] ?? null;

                $hospMainOp_hcode = $fund['hospMainOp']['hcode'] ?? null;
                $hospMainOp_hname = $fund['hospMainOp']['hname'] ?? null;
                $hospSub_hcode = $fund['hospSub']['hcode'] ?? null;
                $hospSub_hname = $fund['hospSub']['hname'] ?? null;
                $hospMain_hcode = $fund['hospMain']['hcode'] ?? null;
                $hospMain_hname = $fund['hospMain']['hname'] ?? null;

                $ok = $stmt->bind_param(
                    "ssssssssssssssssssssssss",
                    $pid, $checkDate, $tname, $fname, $lname, $nation, $birthDate, $sex, $deathDate,
                    $ransDate,
                    $fundType,
                    $mainInscl_id,
                    $mainInscl_name,
                    $subInscl_id,
                    $subInscl_name,
                    $startDateTime,
                    $expireDateTime,
                    $paidModel,
                    $hospMainOp_hcode,
                    $hospMainOp_hname,
                    $hospSub_hcode,
                    $hospSub_hname,
                    $hospMain_hcode,
                    $hospMain_hname
                );

                if($ok === false || !$stmt->execute()){
                    write_log("Insert failed PID {$pid} (item {$itemIndex}): ".$stmt->error);
                    $failed++;
                } else {
                    $inserted++;
                }
            }
        } else {
            $stmt_simple = $mysqli->prepare("
                INSERT INTO personfunddetail
                (pid, checkDate, tname, fname, lname, nation_id, birthDate, sex_id, deathDate)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            if($stmt_simple){
                $ok2 = $stmt_simple->bind_param("sssssssss",
                    $pid, $checkDate, $tname, $fname, $lname, $nation, $birthDate, $sex, $deathDate
                );
                if($ok2 !== false && $stmt_simple->execute()){
                    $inserted++;
                } else {
                    write_log("Insert simple failed PID {$pid}: ".$stmt_simple->error);
                    $failed++;
                }
                $stmt_simple->close();
            } else {
                write_log("Prepare simple failed: ".$mysqli->error);
                $failed++;
            }
        }
    }

    $stmt->close();
    $mysqli->close();

    write_log("Batch save completed. inserted={$inserted}, failed={$failed}");
    echo json_encode(['status'=>'OK','inserted'=>$inserted,'failed'=>$failed]);
    exit;
}

// ====================================================================================
// HANDLE token
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
// HTML + CSS + JS
// ====================================================================================
echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
echo '<title>NHSO Batch Check</title>';
echo '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">';
echo '<style>
body { font-family:"Prompt", sans-serif; background:#eef1f6; color:#333; padding:20px;}
.container { max-width:1200px; margin:auto; background:#fff; padding:20px; border-radius:15px; box-shadow:0 6px 18px rgba(0,0,0,0.1);}
h2 { color:#2c3e50; margin-bottom:10px;}
table { width:100%; border-collapse: separate; border-spacing:0; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-top:15px;}
th, td { padding:12px 15px; text-align:left; font-size:14px;}
th { background:linear-gradient(90deg,#4e73df,#1cc88a); color:#fff; font-weight:600;}
tr:nth-child(even){ background-color:#f8f9fc;}
tr:nth-child(odd){ background-color:#ffffff;}
pre { background:#f4f6f9; padding:10px; border-radius:8px; overflow-x:auto; font-size:13px; color:#333; max-height:200px;}
.status-success {color:#28a745; font-weight:500;}
.status-error {color:#e53e3e; font-weight:500;}
#progress_container { width:100%; background:#e0e0e0; border-radius:10px; overflow:hidden; margin-bottom:15px; height:22px;}
#progress_bar { width:0%; height:100%; background:linear-gradient(90deg,#4e73df,#1cc88a);}
#progress_text { font-weight:600; display:block; margin-bottom:10px;}
</style></head><body><div class="container">';

echo "<h2>ผลการเรียก API ตรวจสอบสิทธิ NHSO (Batch)</h2>";
echo "<p><strong>Token:</strong> ใช้จาก <code>{$token_file}</code> สำเร็จ</p>";
echo '<div id="progress_container"><div id="progress_bar"></div></div><span id="progress_text">0 / 0</span>';
echo '<table id="result_table"><tr><th>PID</th><th>Status</th><th>Detail / Response</th></tr>';

// ====================================================================================
// ดึง PID จากฐานข้อมูล
// ====================================================================================
$mysqli = new mysqli($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name'],$config['db_port']);
if ($mysqli->connect_errno) die("❌ MySQL Connection Error: ".$mysqli->connect_error);

$pids = [];
$sql = "SELECT idcard AS cid FROM person WHERE cid13Chk(idcard)='t' AND nation='99' LIMIT 50";
if($result = $mysqli->query($sql)){
    while($row = $result->fetch_assoc()) $pids[] = $row['cid'];
    $result->free();
} else die("❌ SQL Error: ".$mysqli->error);
$mysqli->close();

// ====================================================================================
// JS batch fetch + insert
// ====================================================================================
echo '<script>
const pids = '.json_encode($pids).';
document.getElementById("progress_text").innerText = "0 / "+pids.length;
const access_token = "'.$access_token.'";
const base_url = "'.$base_url.'";
const batchSize = 500;
const concurrent = 10;
let totalChecked = 0;

async function sendBatch(batch){
    await fetch("",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body: JSON.stringify({batch})
    });
}

async function checkPID(pid){
    let max_retry=3, attempt=0;
    while(attempt<max_retry){
        attempt++;
        try{
            const resp = await fetch(base_url+"?pid="+pid,{
                headers:{ "Authorization":"Bearer "+access_token,"Accept":"application/json" }
            });
            if(resp.status===200){
                const data=await resp.json();
                return {pid,data,status:"success"};
            } else if(resp.status===401){
                alert("❌ Token หมดอายุหรือไม่ถูกต้อง กรุณาอัปโหลดใหม่");
                throw new Error("Unauthorized");
            } else {
                if(attempt>=max_retry) return {pid,data:null,status:"error",error:"HTTP "+resp.status};
            }
        } catch(e){
            if(attempt>=max_retry) return {pid,data:null,status:"error",error:e.message};
            await new Promise(r=>setTimeout(r,500));
        }
    }
}

async function runBatchPID(allPids){
    for(let i=0;i<allPids.length;i+=batchSize){
        const batchPids = allPids.slice(i,i+batchSize);
        const batchResults = [];
        for(let j=0;j<batchPids.length;j+=concurrent){
            const subBatch = batchPids.slice(j,j+concurrent);
            const results = await Promise.all(subBatch.map(pid=>checkPID(pid)));
            batchResults.push(...results);
            totalChecked += subBatch.length;
            const percent = Math.round(totalChecked / allPids.length * 100);
            document.getElementById("progress_bar").style.width = percent+"%";
            document.getElementById("progress_text").innerText = totalChecked+" / "+allPids.length;
            results.forEach(r=>{
                const tr=document.createElement("tr");
                const status_class = r.status==="success"?"status-success":"status-error";
                const detail = r.status==="success"?JSON.stringify(r.data,null,2):r.error;
                tr.innerHTML=`<td>${r.pid}</td><td class="${status_class}">${r.status}</td><td><pre>${detail}</pre></td>`;
                document.getElementById("result_table").appendChild(tr);
            });
        }
        const batchData = batchResults.map(r=>({pid:r.pid,data:r.data}));
        await sendBatch(batchData);
    }
    alert("✅ ตรวจสอบสิทธิและบันทึกฐานเสร็จเรียบร้อย");
}

runBatchPID(pids);
</script>';

echo "</div></body></html>";
?>
