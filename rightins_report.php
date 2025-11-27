<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

// ---------------- Config DB ----------------
$config = include('config.php');
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ฟังก์ชันแปลงวันที่เป็น "2 ธันวาคม 2567"
function toThaiDateFull($date_ad) {
    if (!$date_ad) return null;
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน",
               "กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    $timestamp = strtotime($date_ad);
    $day = date("j",$timestamp);
    $month = date("n",$timestamp);
    $year = date("Y",$timestamp)+543;
    return "$day {$months[$month]} $year";
}

// ---------------- รับค่าจาก Form ----------------
$sdate = $_POST['sdate'] ?? date("Y-m-01");
$edate = $_POST['edate'] ?? date("Y-m-t");
$rightgroupcode = $_POST['rightgroupcode'] ?? 'all';

// ดึงกลุ่มสิทธิ
$groups = $pdo->query("SELECT rightgroupcode,rightgroupname FROM crightgroup ORDER BY rightgroupcode")
              ->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
$totalRows = 0;
$totalMoney = 0;
$selectedGroupName = 'ทั้งหมด';

// ---------------- ดึงข้อมูล ----------------
if($_SERVER['REQUEST_METHOD']=='POST'){
    $sql = "
        SELECT
            vi.visitno AS 'SEQ',
            vi.visitdate AS 'วันที่รับบริการ',
            CONCAT(c.titlename,p.fname,' ',p.lname) AS 'ชื่อ',
            p.idcard AS 'CID',
            p.birth AS 'วันเดือนปีเกิด_raw',
            cr.rightname AS 'สิทธิการรักษา',
            crg.rightgroupname AS 'กลุ่มสิทธิ',
            ch.hosname AS 'สถานบริการตามสิทธิ์',
            GROUP_CONCAT(DISTINCT vd.diagcode) AS 'ICD10',
            vi.diagnote AS 'คำวินิจฉัย',
            GROUP_CONCAT(DISTINCT cd.drugname) AS 'ยาและหัตถการ',
            vi.money1 AS 'ค่าใช้จ่ายรวม',
            vi.money2 AS 'รับจริง',
            vi.money3 AS 'ทุน'
        FROM person p
        INNER JOIN visit vi ON p.pid=vi.pid
        LEFT JOIN ctitle c ON p.prename=c.titlecode
        LEFT JOIN cright cr ON cr.rightcode=vi.rightcode
        LEFT JOIN crightgroup crg ON cr.rightgroup=crg.rightgroupcode
        LEFT JOIN chospital ch ON vi.hosmain=ch.hoscode
        LEFT JOIN visitdiag vd ON vi.visitno=vd.visitno
        LEFT JOIN visitdrug vsd ON vi.visitno=vsd.visitno
        LEFT JOIN cdrug cd ON vsd.drugcode=cd.drugcode
        WHERE vi.visitdate BETWEEN :sdate AND :edate
    ";
    $params = [':sdate'=>$sdate, ':edate'=>$edate];
    if($rightgroupcode !== 'all'){
        $sql .= " AND crg.rightgroupcode=:rightgroupcode";
        $params[':rightgroupcode']=$rightgroupcode;

        foreach($groups as $g){
            if($g['rightgroupcode'] === $rightgroupcode){
                $selectedGroupName = $g['rightgroupname'];
                break;
            }
        }
    }

    $sql .= " GROUP BY vi.visitno";
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalRows = count($rows);

    foreach($rows as $r){
        $totalMoney += (float)$r['ค่าใช้จ่ายรวม'];
    }

    // ---------------- Export Excel ----------------
    if(isset($_POST['export'])){
        ini_set('memory_limit','1G');
        set_time_limit(300);
        $ext = $_POST['export'];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('รายงานสิทธิการรักษา');
        $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(14);

        if(!empty($rows)){
            // Header info 4 แถว
            $sheet->setCellValue('A1', 'รายงานสิทธิการรักษา');
            $sheet->setCellValue('A2', 'ช่วงวันที่: '.toThaiDateFull($sdate).' - '.toThaiDateFull($edate));
            $sheet->setCellValue('A3', 'กลุ่มสิทธิการรักษา: '.$selectedGroupName);
            $sheet->setCellValue('A4', 'จำนวนข้อมูล: '.$totalRows.' แถว   |   ประมาณการค่าใช้จ่ายรวม: '.number_format($totalMoney,2).' บาท');

            $lastColIndex = count($rows[0]);
            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->mergeCells("A2:{$lastColLetter}2");
            $sheet->mergeCells("A3:{$lastColLetter}3");
            $sheet->mergeCells("A4:{$lastColLetter}4");
            $sheet->getStyle("A1:A4")->getFont()->setBold(true);
            $sheet->getStyle("A1:A4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Column header row 5
            $colIndex = 1;
            foreach(array_keys($rows[0]) as $key){
                $headerText = ($key=='วันเดือนปีเกิด_raw') ? 'วันเดือนปีเกิด' : $key;
                $sheet->setCellValueByColumnAndRow($colIndex,5,$headerText);
                $colIndex++;
            }
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex-1);
            $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'4CAF50']],
                'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                'borders'=>['allBorders'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
            ]);

            $rowNum = 6;
            foreach($rows as $r){
                $col = 1;
                foreach($r as $k=>$val){
                    if($k=='วันที่รับบริการ' || $k=='วันเดือนปีเกิด_raw'){
                        $val = toThaiDateFull($val);
                    }
                    $sheet->setCellValueByColumnAndRow($col,$rowNum,$val,true);
                    if(in_array($k,['ค่าใช้จ่ายรวม','รับจริง','ทุน'])){
                        $sheet->getStyleByColumnAndRow($col,$rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyleByColumnAndRow($col,$rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    }
                    if($k=='SEQ'){
                        $sheet->getStyleByColumnAndRow($col,$rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    }
                    $col++;
                }
                $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                if($rowNum % 2 == 0){
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->getFill()->getStartColor()->setRGB('F2F7F2');
                }
                $rowNum++;
            }

            for($i=1;$i<$colIndex;$i++){
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
            $sheet->freezePane('D6');
        }

        $filename = "Report_".date("Ymd_His").".".$ext;
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        $writer = $ext=='xlsx'? new Xlsx($spreadsheet): new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JHCIS Modern Dashboard Report</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --secondary: #8b5cf6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;
    --dark: #1e293b;
    --light: #f8fafc;
    --border: #e2e8f0;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Noto Sans Thai', 'Inter', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 24px;
    color: var(--dark);
    line-height: 1.6;
}

.container {
    max-width: 1600px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.card {
    background: white;
    padding: 32px;
    border-radius: 20px;
    box-shadow: var(--shadow-xl);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.8);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
}

.header-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    padding: 40px 32px;
}

.header-card h2 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.header-card p {
    opacity: 0.95;
    font-size: 16px;
    font-weight: 400;
}

.back-btn {
    text-align: right;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: white;
    color: var(--primary);
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.3s;
    box-shadow: var(--shadow);
    border: 2px solid transparent;
}

.btn-back:hover {
    background: var(--light);
    border-color: var(--primary);
    transform: translateX(-4px);
    box-shadow: var(--shadow-lg);
}

form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

label {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
    letter-spacing: -0.01em;
}

input, select {
    padding: 14px 16px;
    border-radius: 12px;
    border: 2px solid var(--border);
    font-size: 15px;
    font-family: inherit;
    transition: all 0.3s;
    background: white;
    color: var(--dark);
}

input:focus, select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

input:hover, select:hover {
    border-color: #cbd5e1;
}

.button-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.button-group button {
    flex: 1;
    min-width: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 24px;
    font-size: 15px;
    border-radius: 12px;
    font-weight: 600;
    border: none;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: var(--shadow);
    font-family: inherit;
}

.btn-submit {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-export {
    background: linear-gradient(135deg, var(--info), #06b6d4);
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-submit:active, .btn-export:active {
    transform: translateY(0);
}

.data-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 20px 24px;
    background: white;
    border-radius: 16px;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
}

.data-info > div {
    font-weight: 600;
    color: var(--dark);
    font-size: 15px;
}

.total-money {
    color: var(--success) !important;
    font-size: 18px !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

.total-money::before {
    content: '💰';
    font-size: 24px;
}

.table-container {
    overflow: auto;
    border-radius: 16px;
    border: 1px solid var(--border);
    max-height: 600px;
    box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
}

table {
    width: 100%;
    min-width: 1500px;
    border-collapse: separate;
    border-spacing: 0;
}

th, td {
    padding: 16px 14px;
    text-align: left;
    font-size: 14px;
    white-space: nowrap;
}

th {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.05em;
    border-bottom: 3px solid var(--primary-dark);
}

tbody tr {
    transition: all 0.2s;
    border-bottom: 1px solid var(--border);
}

tbody tr:nth-child(even) {
    background: #f8fafc;
}

tbody tr:hover {
    background: #e0e7ff !important;
    transform: scale(1.01);
    box-shadow: var(--shadow);
}

td {
    color: #475569;
}

.no-data {
    text-align: center;
    padding: 48px;
    color: #64748b;
    font-size: 16px;
    background: white;
    border-radius: 16px;
    box-shadow: var(--shadow);
}

.no-data::before {
    content: '📭';
    display: block;
    font-size: 48px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    body {
        padding: 16px;
    }

    .card {
        padding: 24px;
    }

    .header-card h2 {
        font-size: 24px;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .button-group {
        flex-direction: column;
    }

    .button-group button {
        min-width: 100%;
    }

    .data-info {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Scrollbar styling */
.table-container::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.table-container::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    border: 2px solid #f1f5f9;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
}
</style>
</head>
<body>
<div class="container">

    <!-- ปุ่มกลับหน้าแรก -->
    <div class="back-btn">
        <a href="index.php" class="btn-back">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            กลับหน้าหลัก
        </a>
    </div>

    <!-- Header -->
    <div class="card header-card">
        <h2>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 3v18h18"/>
                <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>
            </svg>
            รายงานตามสิทธิการรักษา
        </h2>
        <p>ระบบรายงานข้อมูลผู้ป่วยและสิทธิการรักษา JHCIS</p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <form method="post">
            <div class="form-row">
                <div class="form-group">
                    <label>📅 วันที่เริ่มต้น</label>
                    <input type="date" name="sdate" value="<?=htmlspecialchars($sdate)?>" required>
                </div>
                <div class="form-group">
                    <label>📅 วันที่สิ้นสุด</label>
                    <input type="date" name="edate" value="<?=htmlspecialchars($edate)?>" required>
                </div>
                <div class="form-group">
                    <label>🏥 กลุ่มสิทธิการรักษา</label>
                    <select name="rightgroupcode">
                        <option value="all">ทั้งหมด</option>
                        <?php foreach($groups as $g): ?>
                            <option value="<?=htmlspecialchars($g['rightgroupcode'])?>" <?=($rightgroupcode==$g['rightgroupcode'])?'selected':''?>><?=htmlspecialchars($g['rightgroupname'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="button-group">
                <button type="submit" class="btn-submit">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    แสดงรายงาน
                </button>
                <button type="submit" name="export" value="xls" class="btn-export">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export .xls
                </button>
                <button type="submit" name="export" value="xlsx" class="btn-export">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export .xlsx
                </button>
            </div>
        </form>
    </div>

    <?php if(!empty($rows)): ?>
        <!-- Data Info -->
        <div class="data-info">
            <div>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;margin-right:8px">
                    <path d="M9 11H3v2h6m5-10h6v2h-6m0 6h6v2h-6m-5 6H3v2h6"/>
                </svg>
                จำนวนข้อมูล: <strong><?=number_format($totalRows)?></strong> แถว
            </div>
            <div class="total-money">
                ประมาณการค่าใช้จ่ายรวม <?php if($rightgroupcode!='all') echo "($selectedGroupName)"; ?> : <strong><?=number_format($totalMoney,2)?></strong> บาท
            </div>
        </div>

        <!-- Table Card -->
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php foreach(array_keys($rows[0]) as $th):
                                if($th=='วันเดือนปีเกิด_raw') $th='วันเดือนปีเกิด';
                            ?>
                                <th><?=htmlspecialchars($th)?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <?php foreach($r as $k=>$v):
                                    if($k=='วันที่รับบริการ' || $k=='วันเดือนปีเกิด_raw') $v=toThaiDateFull($v);
                                ?>
                                    <td><?=htmlspecialchars($v)?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif($_SERVER['REQUEST_METHOD']=='POST'): ?>
        <div class="no-data">
            <strong>ไม่พบข้อมูลในช่วงวันที่เลือก</strong><br>
            <span style="font-size:14px;color:#94a3b8">กรุณาเลือกช่วงวันที่อื่นหรือเปลี่ยนเงื่อนไขการค้นหา</span>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
