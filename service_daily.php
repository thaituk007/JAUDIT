<?php
// ----------------------------------------------------
// 0. ตั้งค่าเริ่มต้น และ ป้องกัน Error
// ----------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ----------------------------------------------------
// 1. LOAD LIBRARY (PDF & EXCEL)
// ----------------------------------------------------
$hasLibraries = file_exists('vendor/autoload.php');
if ($hasLibraries) {
    require 'vendor/autoload.php';
}

// ----------------------------------------------------
// 2. LOAD CONFIG & DATABASE
// ----------------------------------------------------
// ตรวจสอบว่ามีไฟล์ config.php หรือไม่ ถ้าไม่มีให้กำหนดค่าตรงนี้แทน
if (file_exists('config.php')) {
    $config = include 'config.php';
    $host = $config['db_host'];
    $port = $config['db_port'];
    $db   = $config['db_name'];
    $user = $config['db_user'];
    $pass = $config['db_pass'];
} else {
    // กรณีไม่มีไฟล์ config ให้แก้ค่าตรงนี้
    $host = "localhost";
    $port = "3306";
    $db   = "my_hospital_db";
    $user = "root";
    $pass = "";
}

$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset("utf8");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ----------------------------------------------------
// 3. HELPER FUNCTIONS
// ----------------------------------------------------
function thaiDateFull($date) {
    if (!$date) return "";
    $days = ["อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์"];
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน",
               "กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    $ts = strtotime($date);
    return "วัน" . $days[date("w",$ts)] . "ที่ " . date("j",$ts) . " " .
           $months[date("n",$ts)] . " " . (date("Y",$ts)+543);
}

function thaiMonthYear($date) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน",
               "กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    $ts = strtotime($date);
    return $months[date('n',$ts)] . " พ.ศ. " . (date("Y",$ts)+543);
}

// ----------------------------------------------------
// 4. LOAD OFFICE INFO
// ----------------------------------------------------
$sqlOffice = "
SELECT
chospital.hosname AS office_name
FROM chospital
JOIN office ON chospital.hoscode = office.offid
JOIN csubdistrict ON chospital.provcode = csubdistrict.provcode
    AND chospital.distcode = csubdistrict.distcode
    AND chospital.subdistcode = csubdistrict.subdistcode
JOIN cdistrict ON csubdistrict.distcode = cdistrict.distcode
JOIN cprovince ON cdistrict.provcode = cprovince.provcode
JOIN person ON chospital.hoscode=person.pcucodeperson
LIMIT 1
";
$office = $conn->query($sqlOffice)->fetch_assoc();
$office_name = $office['office_name'] ?? "โรงพยาบาลส่งเสริมสุขภาพตำบล (ทดสอบ)";

// ----------------------------------------------------
// 5. GET DATE PARAMETERS
// ----------------------------------------------------
$start = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_STRING) ?: date("Y-m-01");
$end   = filter_input(INPUT_GET, 'end', FILTER_SANITIZE_STRING)   ?: date("Y-m-t");

// ----------------------------------------------------
// 6. SQL QUERY (Used by all parts)
// ----------------------------------------------------
$sqlMain = "
    SELECT
        v.visitdate AS service_date,
        SUM(CASE WHEN v.flagservice='03' AND v.timeservice='1' THEN 1 ELSE 0 END) AS in_office,
        SUM(CASE WHEN v.flagservice='03' AND v.timeservice='2' THEN 1 ELSE 0 END) AS out_office
    FROM visit v
    LEFT JOIN visitdiag vd ON v.visitno = vd.visitno
    WHERE v.visitdate BETWEEN ? AND ?
    GROUP BY v.visitdate
    ORDER BY v.visitdate
";

// ----------------------------------------------------
// PART A: PDF EXPORT (แก้ไขภาษาไทย)
// ----------------------------------------------------
if (isset($_GET['pdf'])) {
    if (!$hasLibraries) {
        die('<script>alert("กรุณาติดตั้ง Dompdf (composer require dompdf/dompdf)"); window.history.back();</script>');
    }

    // เตรียม Path ฟอนต์
    $fontDir = __DIR__ . '/fonts/';
    $fontRegular = $fontDir . 'THSarabunNew.ttf';
    $fontBold    = $fontDir . 'THSarabunNew Bold.ttf';

    // เช็คว่ามีฟอนต์จริงไหม (เพื่อป้องกัน Error งงๆ)
    if (!file_exists($fontRegular)) {
        die("<div style='font-family:sans-serif; color:red; padding:20px; border:1px solid red;'>
             <h3>ไม่พบไฟล์ฟอนต์!</h3>
             กรุณาสร้างโฟลเดอร์ <b>fonts</b> และนำไฟล์ <b>THSarabunNew.ttf</b> มาใส่<br>
             Path ที่ระบบมองหาคือ: $fontRegular
             </div>");
    }

    $stmt = $conn->prepare($sqlMain);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    // สร้าง HTML สำหรับ PDF
    // หมายเหตุ: ใช้ @font-face เพื่อชี้ไปที่ไฟล์โดยตรง
    $html = '
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <style>
            @font-face {
                font-family: "THSarabunNew";
                font-style: normal;
                font-weight: normal;
                src: url("' . $fontRegular . '") format("truetype");
            }
            @font-face {
                font-family: "THSarabunNew";
                font-style: normal;
                font-weight: bold;
                src: url("' . (file_exists($fontBold) ? $fontBold : $fontRegular) . '") format("truetype");
            }

            body {
                font-family: "THSarabunNew", sans-serif;
                font-size: 16pt;
                line-height: 1.1;
            }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            table, th, td { border: 1px solid #000; }
            th { background-color: #f0f0f0; padding: 5px; font-weight: bold; }
            td { padding: 5px; text-align: center; }
            .text-left { text-align: left; }
            .header-box { text-align: center; margin-bottom: 10px; }
            h3 { margin: 0; padding: 0; font-weight: bold; font-size: 20pt; }
        </style>
    </head>
    <body>
        <div class="header-box">
            <h3>รายงานจำนวนผู้รับบริการรายวัน (ทั้งในเวลาและนอกเวลาราชการ)</h3>
            <div style="font-size: 16pt;">
                <b>ชื่อสถานบริการ :</b> '.$office_name.'<br>
                <b>ประจำเดือน :</b> '.thaiMonthYear($start).'
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="35%">วันที่</th>
                    <th width="20%">ในเวลา</th>
                    <th width="20%">นอกเวลา</th>
                    <th width="25%">รวม</th>
                </tr>
            </thead>
            <tbody>
    ';

    while ($r = $result->fetch_assoc()) {
        $total = $r['in_office'] + $r['out_office'];
        $html .= "
        <tr>
            <td class='text-left'>&nbsp; ".thaiDateFull($r['service_date'])."</td>
            <td>{$r['in_office']}</td>
            <td>{$r['out_office']}</td>
            <td>{$total}</td>
        </tr>";
    }

    $html .= "</tbody></table></body></html>";

    // ล้าง Buffer ก่อนสร้าง PDF
    if (ob_get_length()) ob_clean();

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->set_option('isRemoteEnabled', true); // อนุญาตให้โหลดไฟล์ local
    $dompdf->set_option('defaultFont', 'THSarabunNew');
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("service_daily_".date("Ymd").".pdf", ["Attachment" => 1]);
    exit;
}

// ----------------------------------------------------
// PART B: EXCEL EXPORT
// ----------------------------------------------------
if (isset($_GET['excel'])) {
    if (!$hasLibraries) {
        die('<script>alert("กรุณาติดตั้ง PhpSpreadsheet"); window.history.back();</script>');
    }

    $stmt = $conn->prepare($sqlMain);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header
    $sheet->setCellValue('A1','วันที่');
    $sheet->setCellValue('B1','ในเวลา');
    $sheet->setCellValue('C1','นอกเวลา');
    $sheet->setCellValue('D1','รวม');

    // Style Header
    $sheet->getStyle('A1:D1')->getFont()->setBold(true);

    $rowNum = 2;
    while ($r = $result->fetch_assoc()) {
        $sheet->setCellValue('A'.$rowNum, thaiDateFull($r['service_date']));
        $sheet->setCellValue('B'.$rowNum, $r['in_office']);
        $sheet->setCellValue('C'.$rowNum, $r['out_office']);
        $sheet->setCellValue('D'.$rowNum, $r['in_office'] + $r['out_office']);
        $rowNum++;
    }

    // Auto width
    foreach(range('A','D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Clear buffer
    if (ob_get_length()) ob_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="service_daily.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ----------------------------------------------------
// PART C: HTML PAGE DISPLAY
// ----------------------------------------------------
$stmt = $conn->prepare($sqlMain);
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
$inData = [];
$outData = [];
$totalData = [];

while ($row = $result->fetch_assoc()) {
    $dates[] = thaiDateFull($row['service_date']);
    $inData[] = (int)$row['in_office'];
    $outData[] = (int)$row['out_office'];
    $totalData[] = $row['in_office'] + $row['out_office'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงานจำนวนผู้รับบริการรายวัน</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<style>
/* ... CSS เดิมทั้งหมดของคุณ ... */
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Prompt', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 30px 20px; }
.container { max-width: 1200px; margin: 0 auto; }
.card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; animation: slideUp 0.5s ease; }
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 40px; border-bottom: 4px solid #f59e0b; }
.header h1 { font-size: 28px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; }
.header-info { font-size: 16px; opacity: 0.95; font-weight: 300; line-height: 1.8; }
.content { padding: 40px; }
.filter-section { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 2px solid #e2e8f0; }
.filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 14px; font-weight: 500; color: #475569; }
.form-group .thai-datepicker { padding: 12px 15px; border: 2px solid #cbd5e1; border-radius: 10px; font-family: 'Prompt', sans-serif; font-size: 15px; transition: all 0.3s ease; background: white; cursor: pointer; width: 100%; min-width: 200px; }
.form-group .thai-datepicker:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
.btn { padding: 12px 25px; border: none; border-radius: 10px; font-family: 'Prompt', sans-serif; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; margin-top: auto; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); }
.export-section { display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
.btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
.btn-success:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4); }
.btn-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
.btn-warning:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4); }
.btn-home { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; }
.btn-home:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4); }
.table-container { overflow-x: auto; border-radius: 15px; border: 2px solid #e2e8f0; }
table { width: 100%; border-collapse: collapse; background: white; }
thead { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
th { padding: 18px 15px; text-align: center; font-weight: 600; font-size: 16px; border-bottom: 3px solid #f59e0b; }
td { padding: 15px; text-align: center; border-bottom: 1px solid #e2e8f0; font-size: 15px; color: #334155; }
tbody tr:hover { background: #f8fafc; transform: scale(1.01); }
tbody tr:nth-child(even) { background: #fafafa; }
.number-cell { font-weight: 600; color: #667eea; }
.total-cell { font-weight: 700; color: #764ba2; font-size: 16px; }
.stats-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 20px; border-radius: 15px; border: 2px solid #e2e8f0; text-align: center; }
.stat-card .stat-value { font-size: 32px; font-weight: 700; color: #667eea; margin-bottom: 5px; }
.stat-card .stat-label { font-size: 14px; color: #64748b; font-weight: 500; }
@media (max-width: 768px) { body { padding: 15px 10px; } .header { padding: 20px 25px; } .header h1 { font-size: 22px; } .content { padding: 25px; } .filter-form { flex-direction: column; align-items: stretch; } .btn { width: 100%; justify-content: center; } table { font-size: 14px; } th, td { padding: 12px 8px; } }
</style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> รายงานจำนวนผู้รับบริการรายวัน</h1>
            <div class="header-info">
                <div><i class="fas fa-hospital"></i> <?php echo $office_name; ?></div>
                <div><i class="fas fa-calendar-alt"></i> ประจำเดือน <?php echo thaiMonthYear($start); ?></div>
            </div>
        </div>

        <div class="content">
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> เริ่มวันที่</label>
                        <input type="text" class="thai-datepicker" name="start" value="<?php echo $start; ?>" placeholder="เลือกวันที่" required readonly>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-check"></i> ถึงวันที่</label>
                        <input type="text" class="thai-datepicker" name="end" value="<?php echo $end; ?>" placeholder="เลือกวันที่" required readonly>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงผล
                    </button>
                </form>
            </div>

            <?php if(count($dates) > 0):
                $totalIn = array_sum($inData);
                $totalOut = array_sum($outData);
                $grandTotal = array_sum($totalData);
            ?>
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($totalIn); ?></div>
                    <div class="stat-label">ในเวลาราชการ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($totalOut); ?></div>
                    <div class="stat-label">นอกเวลาราชการ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($grandTotal); ?></div>
                    <div class="stat-label">รวมทั้งหมด</div>
                </div>
            </div>
            <?php endif; ?>

            <div class="export-section">
                <a class="btn btn-home" href="index.php"><i class="fas fa-home"></i> กลับหน้าแรก</a>
                <a class="btn btn-warning" href="service_daily.php?excel=1&start=<?php echo $start; ?>&end=<?php echo $end; ?>" target="_blank">
                    <i class="fas fa-file-excel"></i> ดาวน์โหลด Excel
                </a>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> วันที่</th>
                            <th><i class="fas fa-clock"></i> ในเวลา</th>
                            <th><i class="fas fa-moon"></i> นอกเวลา</th>
                            <th><i class="fas fa-calculator"></i> รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($i=0; $i<count($dates); $i++): ?>
                        <tr>
                            <td><?php echo $dates[$i]; ?></td>
                            <td class="number-cell"><?php echo number_format($inData[$i]); ?></td>
                            <td class="number-cell"><?php echo number_format($outData[$i]); ?></td>
                            <td class="total-cell"><?php echo number_format($totalData[$i]); ?></td>
                        </tr>
                        <?php endfor; ?>

                        <?php if(count($dates) == 0): ?>
                        <tr>
                            <td colspan="4" style="padding: 40px; color: #94a3b8;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                ไม่พบข้อมูลในช่วงเวลาที่เลือก
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
flatpickr(".thai-datepicker", {
    dateFormat: "Y-m-d",
    locale: "th",
    altInput: true,
    altFormat: "j F Y",
    onReady: function(selectedDates, dateStr, instance) {
        const yearInput = instance.currentYearElement;
        if (yearInput && selectedDates.length > 0) {
            yearInput.value = selectedDates[0].getFullYear() + 543;
        }
    },
    onYearChange: function(selectedDates, dateStr, instance) {
        const yearInput = instance.currentYearElement;
        if (yearInput) {
            const currentYear = parseInt(yearInput.value);
            if (currentYear < 2400) yearInput.value = currentYear + 543;
        }
    }
});
</script>

</body>
</html>
