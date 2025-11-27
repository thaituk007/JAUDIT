<?php
session_save_path(sys_get_temp_dir());
session_start();
date_default_timezone_set("Asia/Bangkok");
$config = include('config.php');

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
$mysqli->set_charset("utf8");

if ($mysqli->connect_errno) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $mysqli->connect_error);
}

// --- SQL Query ---
$sql = "
SELECT
    p.pid AS HN,
    CONCAT(c.titlename,p.fname,' ',p.lname) AS ชื่อ,
    p.birth AS วันเกิด,
    p.idcard AS เลขบัตรประชาชน,
    CONCAT(SUBSTR(p.birth,9,2),'/',SUBSTR(p.birth,6,2),'/',SUBSTR(p.birth,1,4)+543) AS วันเดือนปีเกิด,
    FLOOR((TO_DAYS(NOW())-TO_DAYS(p.birth))/365.25) AS 'อายุ(ปี)',
    CASE
        WHEN (pht.chroniccode IS NOT NULL AND pdm.chroniccode IS NULL) THEN 'ความดันโลหิตสูง'
        WHEN (pdm.chroniccode IS NOT NULL AND pht.chroniccode IS NULL) THEN 'เบาหวาน'
        WHEN (pht.chroniccode IS NOT NULL AND pdm.chroniccode IS NOT NULL) THEN 'ความดันโลหิตสูง,เบาหวาน'
        ELSE 'อื่นๆ'
    END AS โรคประจำตัว,
    h.hno AS บ้านเลขที่,
    v.villno AS หมู่,
    v.villname AS บ้าน,
    v.villcode AS รหัสหมู่บ้าน,
    h.xgis AS Latitude,
    h.ygis AS Longitude
FROM person p
LEFT JOIN persontype pt
    ON pt.pcucodeperson=p.pcucodeperson AND pt.pid=p.pid
LEFT JOIN house h
    ON p.hcode=h.hcode AND p.pcucodeperson=h.pcucodeperson
LEFT JOIN village v
    ON v.villcode=h.villcode AND h.pcucode = v.pcucode
LEFT JOIN cright cr
    ON p.rightcode=cr.rightcode
LEFT JOIN ctitle c
    ON c.titlecode=p.prename
LEFT JOIN persondeath pd
    ON p.pid = pd.pid AND p.pcucodeperson = pd.pcucodeperson
LEFT JOIN personchronic
    ON personchronic.pcucodeperson=p.pcucodeperson AND personchronic.pid=p.pid
LEFT JOIN personchronic pht
    ON p.pid = pht.pid AND p.pcucodeperson = pht.pcucodeperson AND TRIM(pht.chroniccode) LIKE 'I1%'
LEFT JOIN personchronic pdm
    ON p.pid = pdm.pid AND p.pcucodeperson = pdm.pcucodeperson AND TRIM(pdm.chroniccode) LIKE 'E1%'
WHERE p.dischargetype=9
  AND SUBSTRING(h.villcode,7,2)<>'00'
  AND pd.pid IS NULL
  AND personchronic.chroniccode IS NOT NULL
  AND p.nation=99
  AND p.typelive IN ('1','3')
GROUP BY p.idcard
ORDER BY v.villno ASC
";

$result = $mysqli->query($sql);
if (!$result) {
    die("ผิดพลาดในการ Query: " . $mysqli->error);
}

// --- เตรียมข้อมูล Map + DataTables ---
$patients = [];
$diseaseMap = [
    'ความดันโลหิตสูง' => ['emoji'=>'💓','color'=>'red'],
    'เบาหวาน' => ['emoji'=>'💉','color'=>'blue'],
    'ความดันโลหิตสูง,เบาหวาน' => ['emoji'=>'💓‍💉','color'=>'purple'],
    'อื่นๆ' => ['emoji'=>'❔','color'=>'gray']
];

foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
    $disease = $row['โรคประจำตัว'];
    $row['โรคEmojiIcon'] = $diseaseMap[$disease]['emoji'] ?? '❔';
    $row['โรคColor'] = $diseaseMap[$disease]['color'] ?? 'gray';
    $row['โรคText'] = $disease;
    $patients[] = $row;
}

// สร้างรายการโรคจากข้อมูลจริง
$uniqueDiseases = [];
foreach ($patients as $p) {
    if(!in_array($p['โรคText'], $uniqueDiseases)){
        $uniqueDiseases[] = $p['โรคText'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายงานผู้ป่วยเรื้อรัง NCDs</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />

<style>
body { font-family:"Segoe UI","Prompt",Tahoma,sans-serif; background:#f7f9fb; margin:20px;}
h2 { text-align:center; background: linear-gradient(90deg,#4facfe,#00f2fe); color:white; padding:15px; border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.1); margin-bottom:15px;}
#map { height: 500px; margin-bottom:10px; border:2px solid #ddd; border-radius:12px;}
#reportTable td, #reportTable th { font-size: 0.85rem; }
.dataTables_wrapper .dt-buttons { margin-bottom: 15px; }
.legend {background:white; padding:10px 15px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.2); font-size:0.9rem; display:flex; flex-wrap:wrap; gap:15px;}
.legend-item { display:flex; align-items:center; gap:5px; font-weight:500; }
.legend-item span { font-size:20px; }
.dropdown-menu { max-height:250px; overflow-y:auto; min-width:250px;}
.dropdown-menu hr { margin:5px 0; }
.dropdown-menu .form-check { margin-bottom:5px; }
.dropdown-menu button { font-size:0.8rem; padding:2px 6px; }
.header-flex { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px; }
.header-flex .flex-grow { flex-grow:1; text-align:center; font-weight:500; font-size:1.1rem; }
</style>
</head>
<body>

<h2>รายงานผู้ป่วยเรื้อรัง (NCDs)</h2>

<div class="container header-flex">
    <a href="index.php" class="btn btn-outline-secondary">🏠 กลับหน้าแรก</a>
    <div id="thaiDatetime" class="flex-grow"></div>
</div>

<div class="container mb-2">
    <div class="dropdown">
        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">กรองโรค</button>
        <ul class="dropdown-menu p-3">
            <li>
                <div class="form-check">
                    <input class="form-check-input disease-option" type="checkbox" value="all" id="disease_all" checked>
                    <label class="form-check-label" for="disease_all">ทั้งหมด</label>
                </div>
            </li>
            <hr>
            <?php foreach($uniqueDiseases as $d): ?>
            <li>
                <div class="form-check">
                    <input class="form-check-input disease-option" type="checkbox" value="<?= $d ?>" id="disease_<?= $d ?>">
                    <label class="form-check-label" for="disease_<?= $d ?>"><?= $d ?></label>
                </div>
            </li>
            <?php endforeach; ?>
            <li><hr></li>
            <li>
                <button id="selectAll" class="btn btn-sm btn-outline-success">เลือกทั้งหมด</button>
                <button id="deselectAll" class="btn btn-sm btn-outline-danger">ยกเลิกทั้งหมด</button>
            </li>
        </ul>
    </div>
</div>

<div id="map"></div>

<div class="container mb-3">
    <div class="legend">
        <?php foreach($diseaseMap as $d=>$val): ?>
            <div class="legend-item"><span style="color:<?= $val['color'] ?>"><?= $val['emoji'] ?></span> <?= $d ?></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container-fluid">
    <table id="reportTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>HN</th>
                <th>ชื่อ</th>
                <th>เลขบัตรประชาชน</th>
                <th>วันเดือนปีเกิด</th>
                <th>อายุ(ปี)</th>
                <th>โรคประจำตัว</th>
                <th>บ้านเลขที่</th>
                <th>หมู่</th>
                <th>บ้าน</th>
                <th>รหัสหมู่บ้าน</th>
                <th>Latitude</th>
                <th>Longitude</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($patients as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['HN']) ?></td>
                <td><?= htmlspecialchars($row['ชื่อ']) ?></td>
                <td><?= htmlspecialchars($row['เลขบัตรประชาชน']) ?></td>
                <td><?= htmlspecialchars($row['วันเดือนปีเกิด']) ?></td>
                <td><?= htmlspecialchars($row['อายุ(ปี)']) ?></td>
                <td><?= htmlspecialchars($row['โรคText']) ?></td>
                <td><?= htmlspecialchars($row['บ้านเลขที่']) ?></td>
                <td><?= htmlspecialchars($row['หมู่']) ?></td>
                <td><?= htmlspecialchars($row['บ้าน']) ?></td>
                <td><?= htmlspecialchars($row['รหัสหมู่บ้าน']) ?></td>
                <td><?= htmlspecialchars($row['Latitude']) ?></td>
                <td><?= htmlspecialchars($row['Longitude']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<script>
// --- Real-time วันเวลาไทย ---
function updateThaiDatetimeCustom(format="วัน{dayName} ที่ {day} {month} พ.ศ.{year} เวลา {hours}:{minutes}:{seconds}") {
    const monthsThaiFull = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน",
                            "กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    const daysThai = ["อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์"];
    let now = new Date();
    let values = {
        dayName: daysThai[now.getDay()],
        day: now.getDate(),
        month: monthsThaiFull[now.getMonth()],
        year: now.getFullYear() + 543,
        hours: String(now.getHours()).padStart(2,'0'),
        minutes: String(now.getMinutes()).padStart(2,'0'),
        seconds: String(now.getSeconds()).padStart(2,'0')
    };
    document.getElementById('thaiDatetime').textContent = format.replace(/\{(\w+)\}/g, (_, key) => values[key] || '');
}
setInterval(updateThaiDatetimeCustom, 1000);
updateThaiDatetimeCustom();

$(document).ready(function(){
    var patients = <?php echo json_encode($patients, JSON_UNESCAPED_UNICODE); ?>;

    // --- DataTable ---
    var table = $('#reportTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            { extend:'excel', text:'📊 ส่งออก Excel' },
            { extend:'csv', text:'📄 ส่งออก CSV' },
            { extend:'pdf', text:'📕 ส่งออก PDF' },
            { extend:'print', text:'🖨 พิมพ์' }
        ],
        pageLength:25,
        scrollX:true,
        fixedHeader:true,
        responsive:true,
        language: {
            search:"🔍 ค้นหา:",
            lengthMenu:"แสดง _MENU_ รายการ",
            info:"แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
            paginate:{ first:"หน้าแรก", last:"หน้าสุดท้าย", next:"ถัดไป", previous:"ก่อนหน้า"},
            zeroRecords:"ไม่พบข้อมูล"
        }
    });

    // --- Leaflet Map ---
    var map = L.map('map').setView([15.8700, 100.9925], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19 }).addTo(map);
    var markers = L.markerClusterGroup();
    map.addLayer(markers);

    function createEmojiMarker(patient){
        var emojiIcon = L.divIcon({
            html: `<div style="font-size:28px; text-align:center; color:${patient.โรคColor}">${patient.โรคEmojiIcon}</div>`,
            className: '',
            iconSize: [30,30]
        });
        var mapsUrl = `https://www.google.com/maps?q=${patient.Latitude},${patient.Longitude}`;
        return L.marker([patient.Latitude, patient.Longitude], {icon: emojiIcon})
            .bindPopup(`<b>${patient.ชื่อ}</b><br>HN: ${patient.HN}<br>โรค: ${patient.โรคText}<br>บ้านเลขที่ ${patient.บ้านเลขที่} หมู่ ${patient.หมู่}<br>พิกัด: ${patient.Latitude}, ${patient.Longitude}<br><a href="${mapsUrl}" target="_blank" style="text-decoration:none;color:#007bff;">🧭 นำทาง</a>`)
            .bindTooltip(`HN: ${patient.HN} | ${patient.โรคText}`, {permanent:false, direction:'top'});
    }

    function getSelectedDiseases(){
        var selected = [];
        $('.disease-option').each(function(){
            if($(this).is(':checked') && $(this).val() !== 'all'){
                selected.push($(this).val());
            }
        });
        return selected;
    }

    // --- DataTable Custom Filter ---
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            var selected = getSelectedDiseases();
            var disease = data[5]; // Column โรคประจำตัว
            if(selected.length === 0) return true; // "ทั้งหมด"
            return selected.includes(disease);
        }
    );

    function filterAndRender(){
        var selected = getSelectedDiseases();
        table.draw();
        renderMarkers(selected);
    }

    function renderMarkers(selected){
        markers.clearLayers();
        var filtered = patients.filter(p => p.Latitude && p.Longitude &&
            (selected.length === 0 || selected.includes(p.โรคText))
        );
        filtered.forEach(p => markers.addLayer(createEmojiMarker(p)));
        if(filtered.length > 0){
            var group = new L.featureGroup(markers.getLayers());
            map.fitBounds(group.getBounds().pad(0.1));
        } else {
            map.setView([15.8700, 100.9925], 6);
        }
    }

    // --- Event Handlers ---
    $('.disease-option').on('change', function(){
        if($('#disease_all').is(':checked')){
            $('.disease-option').not('#disease_all').prop('checked', false);
        } else { $('#disease_all').prop('checked', false); }
        filterAndRender();
    });

    $('#selectAll').on('click', function(e){ e.preventDefault(); $('.disease-option').prop('checked', true); $('#disease_all').prop('checked', false); filterAndRender(); });
    $('#deselectAll').on('click', function(e){ e.preventDefault(); $('.disease-option').prop('checked', false); $('#disease_all').prop('checked', true); filterAndRender(); });

    // initial render
    filterAndRender();
});
</script>
</body>
<?php $mysqli->close(); ?>
</html>
