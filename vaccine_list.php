<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");

// โหลด config
$config = require_once 'config.php';

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name'], $config['db_port']);
$conn->set_charset("utf8");
if($conn->connect_error) die("เชื่อมต่อ DB ล้มเหลว: ".$conn->connect_error);

// รับค่าฟอร์ม
$start_date = $_GET['start_date'] ?? date('Y-10-01', strtotime('-1 year'));
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$vaccine_full = $_GET['vaccine_full'] ?? [];
$selected_villages = $_GET['villages'] ?? [];

if(!is_array($vaccine_full)) $vaccine_full = [$vaccine_full];
if(!is_array($selected_villages)) $selected_villages = [$selected_villages];

// ดึงรายชื่อวัคซีนทั้งหมดแบบรวม ชนิด-ชื่อ
$vaccineFullList = [];
$res = $conn->query("
    SELECT DISTINCT CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname) AS vaccine_fullname
    FROM visitepi
    LEFT JOIN cdrug ON visitepi.vaccinecode=cdrug.drugcode
    ORDER BY vaccine_fullname ASC
");
if($res) while($r=$res->fetch_assoc()) $vaccineFullList[]=$r['vaccine_fullname'];

// ดึงรายชื่อหมู่บ้านทั้งหมด
$villages = [];
$resVill = $conn->query("SELECT DISTINCT village.villname FROM house LEFT JOIN village ON house.villcode=village.villcode ORDER BY village.villname ASC");
if($resVill) while($r=$resVill->fetch_assoc()) $villages[] = $r['villname'];

// สร้างเงื่อนไข SQL
$where = "visitepi.dateepi BETWEEN '$start_date' AND '$end_date'";
if(!empty($vaccine_full)){
    $list = array_map(function($v) use ($conn){ return "'".$conn->real_escape_string($v)."'"; }, $vaccine_full);
    $where .= " AND CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname) IN (".implode(',', $list).")";
}
if(!empty($selected_villages)){
    $vlist = array_map(function($v) use ($conn){ return "'".$conn->real_escape_string($v)."'"; }, $selected_villages);
    $where .= " AND village.villname IN (".implode(',', $vlist).")";
}

// ดึงข้อมูลผู้รับวัคซีน
$sql = "
SELECT person.pid, person.idcard, person.fname, person.lname,
       visitepi.dateepi, getAgeYearNum(person.birth,CURDATE()) AS age,
       cdrug.drugcode, CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname) AS vaccine_fullname,
       person.hnomoi, village.villno, village.villname
FROM visitepi
LEFT JOIN person ON visitepi.pid=person.pid AND visitepi.pcucodeperson=person.pcucodeperson
LEFT JOIN cdrug ON visitepi.vaccinecode=cdrug.drugcode
LEFT JOIN house ON person.hcode=house.hcode AND person.pcucodeperson=house.pcucode
LEFT JOIN village ON house.villcode=village.villcode AND person.pcucodeperson=village.pcucode
WHERE $where
GROUP BY person.pid
ORDER BY visitepi.dateepi ASC;
";
$result = $conn->query($sql);

// จัดกลุ่มช่วงอายุและเก็บข้อมูลกราฟ stacked
$age_groups_labels = ["0–4","5–14","15–59","60+"];
$ageData = [];
$colorPalette = ['#4f46e5','#22c55e','#f59e0b','#ef4444','#10b981','#f43f5e','#6366f1','#8b5cf6','#f97316','#eab308'];

foreach($vaccineFullList as $i=>$vf){
    if(!empty($vaccine_full) && !in_array($vf,$vaccine_full)) continue;
    $ageData[$vf] = array_fill(0,count($age_groups_labels),0);
    $colors[$vf] = $colorPalette[$i % count($colorPalette)];
}

$data=[];
if($result && $result->num_rows>0){
    while($row=$result->fetch_assoc()){
        $age=(int)$row['age'];
        if($age<=4) $idx=0;
        elseif($age<=14) $idx=1;
        elseif($age<=59) $idx=2;
        else $idx=3;

        if(isset($ageData[$row['vaccine_fullname']])){
            $ageData[$row['vaccine_fullname']][$idx]++;
        }
        $data[]=$row;
    }
}

// ดาวน์โหลด Excel
if(isset($_GET['download']) && $_GET['download']=='1'){
    require 'vendor/autoload.php';
    $spreadsheet=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet=$spreadsheet->getActiveSheet();
    $sheet->setTitle('Vaccine Report');

    $headers=['PID','เลขบัตร','ชื่อ','นามสกุล','อายุ','วันที่รับวัคซีน','รหัสวัคซีน','วัคซีน (ชนิด - ชื่อ)','บ้านเลขที่','หมู่ที่','หมู่บ้าน'];
    $col='A'; foreach($headers as $h){$sheet->setCellValue($col.'1',$h); $col++;}

    $r=2;
    foreach($data as $row){
        $sheet->setCellValueExplicit("A$r",$row['pid'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B$r",$row['idcard'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); // เลขบัตรเป็นข้อความ
        $sheet->setCellValue("C$r",$row['fname']);
        $sheet->setCellValue("D$r",$row['lname']);
        $sheet->setCellValue("E$r",$row['age']);
        $sheet->setCellValue("F$r",$row['dateepi']);
        $sheet->setCellValue("G$r",$row['drugcode']);
        $sheet->setCellValue("H$r",$row['vaccine_fullname']);
        $sheet->setCellValue("I$r",$row['hnomoi']);
        $sheet->setCellValue("J$r",$row['villno']);
        $sheet->setCellValue("K$r",$row['villname']);
        $r++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vaccine_report.xlsx"');
    $writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}

// เตรียมข้อมูล stacked chart หมู่บ้านตามวัคซีนรวม
$villageData=[];
foreach($vaccineFullList as $vf){
    if(!empty($vaccine_full) && !in_array($vf,$vaccine_full)) continue;
    $dataset=['label'=>$vf,'data'=>[],'backgroundColor'=>$colors[$vf]];
    foreach($villages as $vill){
        if(!empty($selected_villages) && !in_array($vill,$selected_villages)) continue;
        $sql = "
        SELECT COUNT(DISTINCT person.pid) as total
        FROM visitepi
        LEFT JOIN person ON visitepi.pid=person.pid AND visitepi.pcucodeperson=person.pcucodeperson
        LEFT JOIN house ON person.hcode=house.hcode AND person.pcucodeperson=house.pcucode
        LEFT JOIN village ON house.villcode=village.villcode AND person.pcucodeperson=village.pcucode
        LEFT JOIN cdrug ON visitepi.vaccinecode=cdrug.drugcode
        WHERE CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname)='$vf'
        AND visitepi.dateepi BETWEEN '$start_date' AND '$end_date'
        AND village.villname='$vill';
        ";
        $res=$conn->query($sql);
        $row=$res->fetch_assoc();
        $dataset['data'][]=(int)$row['total'];
    }
    $villageData[]=$dataset;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ทะเบียนวัคซีน | jAUDIT</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{font-family:'Prompt',sans-serif; background:#f5f7fa;}
.card{background:white; border-radius:1rem; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.1);}
h1{color:#0d6efd; font-weight:600;}
</style>
</head>
<body class="p-6">
<div class="max-w-6xl mx-auto">
  <div class="card mb-6">
    <h1 class="text-2xl mb-4">📊 ทะเบียนผู้ได้รับวัคซีน</h1>
    <form method="GET" class="grid md:grid-cols-2 gap-4 mb-4" id="filterForm">
      <div>
        <label class="block text-gray-700 mb-1">วันที่เริ่มต้น</label>
        <input type="date" name="start_date" value="<?= $start_date ?>" class="border p-2 rounded w-full">
      </div>
      <div>
        <label class="block text-gray-700 mb-1">วันที่สิ้นสุด</label>
        <input type="date" name="end_date" value="<?= $end_date ?>" class="border p-2 rounded w-full">
      </div>
      <div>
        <label class="block text-gray-700 mb-1">หมู่บ้าน เลือกหลายรายการได้</label>
        <select name="villages[]" class="border p-2 rounded w-full" multiple size="6" id="villagesSelect">
          <?php foreach($villages as $v):
            $selected = in_array($v,$selected_villages)?'selected':'';
          ?>
          <option value="<?= $v ?>" <?= $selected ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <div class="mt-2 flex space-x-2">
          <button type="button" id="selectAllVill" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">เลือกทั้งหมด</button>
          <button type="button" id="resetVill" class="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500">Reset</button>
        </div>
      </div>
      <div>
        <label class="block text-gray-700 mb-1">วัคซีน (ชนิด - ชื่อ) เลือกหลายรายการได้</label>
        <select name="vaccine_full[]" class="border p-2 rounded w-full" multiple size="6" id="vaccineSelect">
          <?php foreach($vaccineFullList as $vf):
              $selected = in_array($vf,$vaccine_full)?'selected':''; ?>
          <option value="<?= $vf ?>" <?= $selected ?>><?= $vf ?></option>
          <?php endforeach; ?>
        </select>
        <div class="mt-2 flex space-x-2">
          <button type="button" id="selectAllVac" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">เลือกทั้งหมด</button>
          <button type="button" id="resetVac" class="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500">Reset</button>
        </div>
      </div>
      <div class="flex items-end col-span-2 mt-2">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full md:w-auto">แสดงผล</button>
      </div>
    </form>

    <div class="flex gap-4 mb-4">
      <a href="?<?= http_build_query(array_merge($_GET,['download'=>1])) ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">⬇ ดาวน์โหลด Excel</a>
      <a href="index.php" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">🏠 กลับหน้าแรก</a>
    </div>
  </div>

  <!-- กราฟช่วงอายุ stacked -->
  <div class="card mb-6">
    <h2 class="text-xl mb-2 text-gray-700">กราฟสรุปจำนวนผู้รับวัคซีนตามช่วงอายุ (แยกตามวัคซีน)</h2>
    <canvas id="ageChart" height="100"></canvas>
  </div>

  <!-- กราฟหมู่บ้าน stacked -->
  <div class="card mb-6">
    <h2 class="text-xl mb-2 text-gray-700">กราฟสรุปจำนวนผู้รับวัคซีนตามหมู่บ้าน (แยกตามวัคซีน)</h2>
    <canvas id="villageStackChart" height="150"></canvas>
  </div>

  <!-- ตารางข้อมูล -->
  <div class="card overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-blue-600 text-white">
        <tr>
          <th class="p-2">#</th>
          <th class="p-2">PID</th>
          <th class="p-2">เลขบัตร</th>
          <th class="p-2">ชื่อ-นามสกุล</th>
          <th class="p-2">อายุ</th>
          <th class="p-2">วันที่รับวัคซีน</th>
          <th class="p-2">รหัสวัคซีน</th>
          <th class="p-2">วัคซีน (ชนิด - ชื่อ)</th>
          <th class="p-2">บ้านเลขที่</th>
          <th class="p-2">หมู่ที่</th>
          <th class="p-2">หมู่บ้าน</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!empty($data)): $i=1; foreach($data as $row): ?>
        <tr class="border-b hover:bg-gray-100">
          <td class="p-2"><?= $i++ ?></td>
          <td class="p-2"><?= $row['pid'] ?></td>
          <td class="p-2"><?= $row['idcard'] ?></td>
          <td class="p-2"><?= $row['fname'].' '.$row['lname'] ?></td>
          <td class="p-2"><?= $row['age'] ?></td>
          <td class="p-2"><?= $row['dateepi'] ?></td>
          <td class="p-2"><?= $row['drugcode'] ?></td>
          <td class="p-2"><?= $row['vaccine_fullname'] ?></td>
          <td class="p-2"><?= $row['hnomoi'] ?></td>
          <td class="p-2"><?= $row['villno'] ?></td>
          <td class="p-2"><?= $row['villname'] ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11" class="text-center py-4 text-gray-500">ไม่พบข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// ฟังก์ชันจำสถานะด้วย localStorage
const villagesSelect=document.getElementById('villagesSelect');
const vaccineSelect=document.getElementById('vaccineSelect');

function saveState(){
    localStorage.setItem('selectedVillages', JSON.stringify(Array.from(villagesSelect.selectedOptions).map(o=>o.value)));
    localStorage.setItem('selectedVaccines', JSON.stringify(Array.from(vaccineSelect.selectedOptions).map(o=>o.value)));
}
function loadState(){
    const savedV=JSON.parse(localStorage.getItem('selectedVillages')||'[]');
    const savedVac=JSON.parse(localStorage.getItem('selectedVaccines')||'[]');
    Array.from(villagesSelect.options).forEach(o=>o.selected = savedV.includes(o.value));
    Array.from(vaccineSelect.options).forEach(o=>o.selected = savedVac.includes(o.value));
}
villagesSelect.addEventListener('change', saveState);
vaccineSelect.addEventListener('change', saveState);

// ปุ่มเลือกทั้งหมด / Reset แยกหมู่บ้านและวัคซีน
document.getElementById('selectAllVill').addEventListener('click', ()=>{
    Array.from(villagesSelect.options).forEach(o=>o.selected=true);
    saveState();
});
document.getElementById('resetVill').addEventListener('click', ()=>{
    Array.from(villagesSelect.options).forEach(o=>o.selected=false);
    saveState();
});
document.getElementById('selectAllVac').addEventListener('click', ()=>{
    Array.from(vaccineSelect.options).forEach(o=>o.selected=true);
    saveState();
});
document.getElementById('resetVac').addEventListener('click', ()=>{
    Array.from(vaccineSelect.options).forEach(o=>o.selected=false);
    saveState();
});

// โหลดสถานะล่าสุดตอน reload
loadState();

// กราฟช่วงอายุ stacked
const ageCtx=document.getElementById('ageChart');
const ageDatasets = [];
<?php foreach($ageData as $vf => $vals): ?>
ageDatasets.push({
    label: "<?= $vf ?>",
    data: <?= json_encode($vals) ?>,
    backgroundColor: "<?= $colors[$vf] ?>"
});
<?php endforeach; ?>
new Chart(ageCtx,{
  type:'bar',
  data:{labels: <?= json_encode($age_groups_labels) ?>, datasets: ageDatasets},
  options:{responsive:true, plugins:{tooltip:{mode:'index',intersect:false}, legend:{position:'top'}}, scales:{x:{stacked:true}, y:{stacked:true, beginAtZero:true}}}
});

// กราฟหมู่บ้าน stacked
const villageCtx=document.getElementById('villageStackChart');
new Chart(villageCtx,{
  type:'bar',
  data:{
    labels: <?= json_encode(array_values(array_filter($villages,function($v){return empty($_GET['villages']) || in_array($v,$_GET['villages']);}))) ?>,
    datasets: <?= json_encode($villageData) ?>
  },
  options:{plugins:{tooltip:{enabled:true, mode:'index', intersect:false}, legend:{position:'top'}}, responsive:true, scales:{x:{stacked:true}, y:{stacked:true, beginAtZero:true}}}
});
</script>
</body>
</html>
<?php $conn->close(); ?>
