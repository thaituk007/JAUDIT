<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");
header('Content-Type: application/json');

$config = require_once 'config.php';
$conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name'], $config['db_port']);
$conn->set_charset("utf8");
if($conn->connect_error) die(json_encode(['error'=>$conn->connect_error]));

$start_date = $_GET['start_date'] ?? date('Y-10-01', strtotime('-1 year'));
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$vaccine_full = $_GET['vaccine_full'] ?? [];
$selected_villages = $_GET['villages'] ?? [];

if(!is_array($vaccine_full)) $vaccine_full = [$vaccine_full];
if(!is_array($selected_villages)) $selected_villages = [$selected_villages];

// ดึงรายชื่อวัคซีนและหมู่บ้าน
$vaccineFullList=[];
$res=$conn->query("SELECT DISTINCT CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname) AS vaccine_fullname FROM visitepi LEFT JOIN cdrug ON visitepi.vaccinecode=cdrug.drugcode ORDER BY vaccine_fullname ASC");
while($r=$res->fetch_assoc()) $vaccineFullList[]=$r['vaccine_fullname'];

$villages=[];
$res=$conn->query("SELECT DISTINCT village.villname FROM house LEFT JOIN village ON house.villcode=village.villcode ORDER BY village.villname ASC");
while($r=$res->fetch_assoc()) $villages[]=$r['villname'];

// เงื่อนไข SQL
$where="visitepi.dateepi BETWEEN '$start_date' AND '$end_date'";
if(!empty($vaccine_full)){
    $list=array_map(fn($v)=>"'".$conn->real_escape_string($v)."'", $vaccine_full);
    $where.=" AND CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname) IN (".implode(',',$list).")";
}
if(!empty($selected_villages)){
    $list=array_map(fn($v)=>"'".$conn->real_escape_string($v)."'", $selected_villages);
    $where.=" AND village.villname IN (".implode(',',$list).")";
}

// ดึงข้อมูล
$sql="SELECT person.pid, person.idcard, person.fname, person.lname,
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
      ORDER BY visitepi.dateepi ASC";
$result=$conn->query($sql);

// เตรียมข้อมูล stacked
$age_groups_labels=["0–4","5–14","15–59","60+"];
$ageData=[]; $villageData=[]; $colors=['#4f46e5','#22c55e','#f59e0b','#ef4444','#10b981','#f43f5e','#6366f1','#8b5cf6','#f97316','#eab308'];
$colorMap=[]; $data=[];

foreach($vaccineFullList as $i=>$vf){
    if(!empty($vaccine_full) && !in_array($vf,$vaccine_full)) continue;
    $ageData[$vf] = array_fill(0,count($age_groups_labels),0);
    $colorMap[$vf]=$colors[$i%count($colors)];
}

if($result && $result->num_rows>0){
    while($row=$result->fetch_assoc()){
        $age=(int)$row['age'];
        if($age<=4) $idx=0;
        elseif($age<=14) $idx=1;
        elseif($age<=59) $idx=2;
        else $idx=3;
        if(isset($ageData[$row['vaccine_fullname']])) $ageData[$row['vaccine_fullname']][$idx]++;
        $data[]=$row;
    }
}

// กราฟช่วงอายุ
$age_datasets=[];
foreach($ageData as $vf=>$vals){
    $age_datasets[]= ['label'=>$vf,'data'=>$vals,'backgroundColor'=>$colorMap[$vf]];
}

// กราฟหมู่บ้าน
$village_datasets=[];
foreach($vaccineFullList as $vf){
    if(!empty($vaccine_full) && !in_array($vf,$vaccine_full)) continue;
    $dataset=['label'=>$vf,'data'=>[],'backgroundColor'=>$colorMap[$vf]];
    foreach($villages as $vill){
        if(!empty($selected_villages) && !in_array($vill,$selected_villages)) continue;
        $sql="SELECT COUNT(DISTINCT person.pid) AS total
              FROM visitepi
              LEFT JOIN person ON visitepi.pid=person.pid AND visitepi.pcucodeperson=person.pcucodeperson
              LEFT JOIN house ON person.hcode=house.hcode AND person.pcucodeperson=house.pcucode
              LEFT JOIN village ON house.villcode=village.villcode AND person.pcucodeperson=village.pcucode
              LEFT JOIN cdrug ON visitepi.vaccinecode=cdrug.drugcode
              WHERE CONCAT(visitepi.vaccinecode,' - ',cdrug.drugname)='$vf'
              AND visitepi.dateepi BETWEEN '$start_date' AND '$end_date'
              AND village.villname='$vill'";
        $res=$conn->query($sql);
        $row=$res->fetch_assoc();
        $dataset['data'][]=(int)$row['total'];
    }
    $village_datasets[]=$dataset;
}

echo json_encode([
    'age_labels'=>$age_groups_labels,
    'age_datasets'=>$age_datasets,
    'village_labels'=>$villages,
    'village_datasets'=>$village_datasets,
    'data'=>$data
]);
$conn->close();
