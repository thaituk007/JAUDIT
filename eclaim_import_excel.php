<?php
// eclaim_import_excel.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date; // ใช้สำหรับแปลงวันที่ Excel

// โหลด config.php
$config = include('config.php');
$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
if ($mysqli->connect_errno) {
    die("❌ Database connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// กำหนดแถวเริ่มอ่านข้อมูล (หัวตารางหลายบรรทัด 6,7,8)
define('START_ROW', 8);

// Helper function to format Excel date/time to MySQL date string (YYYY-MM-DD)
function excel_date_to_sql($excel_date) {
    if (empty($excel_date) || !is_numeric($excel_date)) {
        return null;
    }
    // Check if it's an Excel date (integer/float)
    if (Date::isExcelDate($excel_date)) {
        // Use PHP's built-in date formatting after conversion
        return Date::excelToDateTimeObject($excel_date)->format('Y-m-d');
    }
    // Return null if not a valid Excel date
    return null;
}


// Mapping Excel Header -> SQL Column
$headerToColumn = [
    'REP_No_' => 'rep_no',
    'ลำดับที่' => 'seq_no',
    'TRAN_ID' => 'tran_id',
    'HN' => 'hn',
    'AN' => 'an',
    'PID' => 'pid',
    'ชื่อ_สกุล' => 'patient_name',
    'ประเภทผู้ป่วย' => 'patient_type',
    'วันเข้ารักษา' => 'admit_date',
    'วันจำหน่าย' => 'discharge_date',
    'ชดเชยสุทธิ_สปสช_' => 'nhso_net',
    'ต้นสังกัด' => 'department',
    'ชดเชยจาก' => 'reimbursement_source',
    'Error_Code' => 'error_code',
    'กองทุนหลัก' => 'main_fund',
    'กองทุนย่อย' => 'sub_fund',
    'ประเภทบริการ' => 'service_type',
    'การรับส่งต่อ' => 'referral',
    'การมีสิทธิ' => 'has_right',
    'การใช้สิทธิ' => 'use_right',
    'CHK' => 'chk',
    'สิทธิหลัก' => 'main_right',
    'สิทธิย่อย' => 'sub_right',
    'HREF' => 'href',
    'HCODE' => 'hcode',
    'HMAIN' => 'hmain',
    'PROV1' => 'prov1',
    'RG1' => 'rg1',
    'HMAIN2' => 'hmain2',
    'PROV2' => 'prov2',
    'RG2' => 'rg2',
    'DMIS__HMAIN3' => 'dmis_hmain3',
    'DA' => 'da',
    'PROJ' => 'proj',
    'PA' => 'pa',
    'DRG' => 'drg',
    'RW' => 'rw',
    'CA_TYPE' => 'ca_type',
    'เรียกเก็บ__1__กลุ่มที่ไม่ใช่กลุ่มค่ารถ_ค่ายา_ค่าอุปกรณ์__1_1_' => 'claim_non_vehicle_drug',
    'กลุ่มที่เป็น_ค่ารถ_ค่ายา__ค่าอุปกรณ์__1_2_' => 'claim_vehicle_drug',
    'รวมยอดเรียกเก็บ__1_3_____1_1___1_2_' => 'claim_total',
    'เรียกเก็บ_central_reimburse__2_' => 'claim_central',
    'ชำระเอง__3_' => 'self_pay',
    'อัตราจ่าย_Point__4_' => 'pay_point',
    'ล่าช้า__PS___5_' => 'late_ps',
    'col46' => 'col46',
    'CCUF___6_' => 'ccuf',
    'AdjRW_NHSO__7_' => 'adj_rw_nhso',
    'AdjRW2__8___6x7_' => 'adj_rw2',
    'จ่ายชดเชย__9___4x5x8_' => 'reimbursed',
    'ค่าพรบ___10_' => 'porb',
    'เงินเดือน_ร้อยละ' => 'salary_percent',
    'จำนวนเงิน__11_' => 'salary_amount',
    'ยอดชดเชยหลังหักเงินเดือน__12___9_10_11_' => 'reimbursed_after_salary',
    'ค่าใช้จ่ายสูง__HC__IPHC' => 'high_cost',
    'OPHC' => 'ophc',
    'อุบัติเหตุฉุกเฉิน__AE__OPAE__1_1_4_5_' => 'emergency_ae',
    'IPNB' => 'ipnb',
    'IPUC' => 'ipuc',
    'IP3SSS' => 'ip3sss',
    'IP7SSS' => 'ip7sss',
    'CARAE' => 'careae',
    'CAREF' => 'caref',
    'CAREF_PUC' => 'caref_puc',
    'อวัยวะเทียม_อุปกรณ์บำบัดรักษา__INST__OPINST' => 'prosthesis_inst',
    'INST' => 'inst',
    'ผู้ป่วยใน__IP__IPAEC' => 'inpatient_ip',
    'IPAER' => 'ipaer',
    'IPINRGC' => 'ipinrgc',
    'IPINRGR' => 'ipinrgr',
    'IPINSPSN' => 'ipinspsn',
    'IPPRCC' => 'ipprcc',
    'IPPRCC_PUC' => 'ipprcc_puc',
    'IPBKK_INST' => 'ipbkk_inst',
    'IP_ONTOP' => 'ip_ontop',
    'โรคเฉพาะ__DMIS__CATARACT_CATARACT' => 'dm_cataract',
    'ค่าภาระงาน_สสจ__' => 'workcost_prov',
    'ค่าภาระงาน_รพ__' => 'workcost_hosp',
    'CATINST' => 'catinst',
    'DMISRC_DMISRC' => 'dmisrc',
    'ค่าภาระงาน_RCUHOSC_RCUHOSC' => 'workcost_rcuhosc',
    'ค่าภาระงาน_RCUHOSR_RCUHOSR' => 'workcost_rcuhosr',
    'ค่าภาระงาน_LLOP' => 'workcost_llop',
    'LLRGC' => 'llrgc',
    'LLRGR' => 'llrgr',
    'LP' => 'lp',
    'STROKE_STEMI_DRUG' => 'stroke_stemi_drug',
    'DMIDML' => 'dmidml',
    'PP' => 'pp',
    'DMISHD' => 'dmishd',
    'DMICNT' => 'dmicnt',
    'Paliative_Care' => 'palliative_care',
    'DM' => 'dm',
    'DRUG_1' => 'drug',
    'OPBKK_HC' => 'opbkk_hc',
    'DENT' => 'dent',
    'DRUG_2' => 'drug2',
    'FS_1' => 'fs',
    'OTHERS' => 'others',
    'HSUB' => 'hsub',
    'NHSO' => 'nhso',
    'Deny_HC' => 'deny_hc',
    'AE' => 'ae',
    'INST_2' => 'inst2',
    'IP' => 'ip',
    'DMIS_2' => 'dmis2',
    'base_rate_เดิม' => 'base_rate_old',
    'base_rate_ที่ได้รับเพิ่ม' => 'base_rate_added',
    'base_rate_สุทธิ' => 'base_rate_net',
    'FS_2' => 'fs2',
    'VA' => 'va',
    'Remark' => 'remark',
    'AUDIT_RESULTS' => 'audit_results',
    'รูปแบบการจ่าย' => 'pay_method',
    'SEQ_NO' => 'seq_invoice',
    'INVOICE_NO' => 'invoice_no',
    'INVOICE_LT' => 'invoice_lt'
];

// Mapping Type (s=string/varchar/date/text, i=integer, d=double/decimal)
$columnType = [
    'rep_no' => 'i',
    'seq_no' => 'i',
    'tran_id' => 's',
    'hn' => 's',
    'an' => 's',
    'pid' => 's',
    'patient_name' => 's',
    'patient_type' => 's',
    'admit_date' => 's',
    'discharge_date' => 's',
    'nhso_net' => 'd',
    'department' => 's',
    'reimbursement_source' => 's',
    'error_code' => 's',
    'main_fund' => 's',
    'sub_fund' => 's',
    'service_type' => 's',
    'referral' => 's',
    'has_right' => 's',
    'use_right' => 's',
    'chk' => 's',
    'main_right' => 's',
    'sub_right' => 's',
    'href' => 's',
    'hcode' => 's',
    'hmain' => 's',
    'prov1' => 's',
    'rg1' => 's',
    'hmain2' => 's',
    'prov2' => 's',
    'rg2' => 's',
    'dmis_hmain3' => 's',
    'da' => 's',
    'proj' => 's',
    'pa' => 's',
    'drg' => 's',
    'rw' => 'd',
    'ca_type' => 's',
    'claim_non_vehicle_drug' => 'd',
    'claim_vehicle_drug' => 'd',
    'claim_total' => 'd',
    'claim_central' => 'd',
    'self_pay' => 'd',
    'pay_point' => 'd',
    'late_ps' => 'd',
    'col46' => 's',
    'ccuf' => 'd',
    'adj_rw_nhso' => 'd',
    'adj_rw2' => 'd',
    'reimbursed' => 'd',
    'porb' => 'd',
    'salary_percent' => 'd',
    'salary_amount' => 'd',
    'reimbursed_after_salary' => 'd',
    'high_cost' => 'd',
    'ophc' => 'd',
    'emergency_ae' => 'd',
    'ipnb' => 'd',
    'ipuc' => 'd',
    'ip3sss' => 'd',
    'ip7sss' => 'd',
    'careae' => 'd',
    'caref' => 'd',
    'caref_puc' => 'd',
    'prosthesis_inst' => 'd',
    'inst' => 'd',
    'inpatient_ip' => 'd',
    'ipaer' => 'd',
    'ipinrgc' => 'd',
    'ipinrgr' => 'd',
    'ipinspsn' => 'd',
    'ipprcc' => 'd',
    'ipprcc_puc' => 'd',
    'ipbkk_inst' => 'd',
    'ip_ontop' => 'd',
    'dm_cataract' => 'd',
    'workcost_prov' => 'd',
    'workcost_hosp' => 'd',
    'catinst' => 'd',
    'dmisrc' => 'd',
    'workcost_rcuhosc' => 'd',
    'workcost_rcuhosr' => 'd',
    'workcost_llop' => 'd',
    'llrgc' => 'd',
    'llrgr' => 'd',
    'lp' => 'd',
    'stroke_stemi_drug' => 'd',
    'dmidml' => 'd',
    'pp' => 'd',
    'dmishd' => 'd',
    'dmicnt' => 'd',
    'palliative_care' => 'd',
    'dm' => 'd',
    'drug' => 'd',
    'opbkk_hc' => 'd',
    'dent' => 'd',
    'drug2' => 'd',
    'fs' => 'd',
    'others' => 'd',
    'hsub' => 'd',
    'nhso' => 'd',
    'deny_hc' => 'd',
    'ae' => 'd',
    'inst2' => 'd',
    'ip' => 'd',
    'dmis2' => 'd',
    'base_rate_old' => 'd',
    'base_rate_added' => 'd',
    'base_rate_net' => 'd',
    'fs2' => 'd',
    'va' => 'd',
    'remark' => 's',
    'audit_results' => 's',
    'pay_method' => 's',
    'seq_invoice' => 'i',
    'invoice_no' => 's',
    'invoice_lt' => 's'
];


// --- ขั้นตอน Import ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel'])) {
    $originalName = $_FILES['excel']['name'];
    $tmpFile = $_FILES['excel']['tmp_name'];

    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
    $filePath = 'uploads/' . uniqid() . '_' . basename($originalName);
    if (!move_uploaded_file($tmpFile, $filePath)) {
        die("❌ ไม่สามารถอัปโหลดไฟล์ไปยังเซิร์ฟเวอร์ได้");
    }

    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // รวมหัวตาราง 3 บรรทัด
        $excelHeaders = [];
        for ($i = 6; $i <= 8; $i++) {
            foreach ($rows[$i] as $key => $value) {
                if (!isset($excelHeaders[$key])) $excelHeaders[$key] = '';
                $excelHeaders[$key] .= trim($value) . '_';
            }
        }
        // ✅ FIX: แก้ไข Parse Error
        $excelHeaders = array_map(function($v) {
            return rtrim($v,'_');
        }, $excelHeaders);

        // แสดง preview
        $previewRows = array_slice($rows, START_ROW, 5, true);
        echo "<h2>ตัวอย่างข้อมูล 5 แถวแรก</h2><table border=1><tr>";
        foreach ($excelHeaders as $h) echo "<th>$h</th>";
        echo "</tr>";
        foreach ($previewRows as $row) {
            echo "<tr>";
            foreach ($excelHeaders as $k => $h) echo "<td>" . ($row[$k] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<form method='post' enctype='multipart/form-data'>
            <input type='hidden' name='confirmed' value='1'>
            <input type='hidden' name='filePath' value='$filePath'>
            <button type='submit' style='background-color: #28a745;'>✅ นำเข้าข้อมูลทั้งหมด</button>
        </form>";
        exit;

    } catch (Exception $e) {
        // ลบไฟล์ที่อัปโหลดถ้าเกิดข้อผิดพลาด
        if (file_exists($filePath)) unlink($filePath);
        die("❌ Error: ".$e->getMessage());
    }
}

// --- ขั้นตอนยืนยันนำเข้า ---
if (isset($_POST['confirmed'])) {
    $filePath = $_POST['filePath'];
    $imported = 0;
    $skipped = 0;

    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // รวมหัวตาราง 3 บรรทัด
        $excelHeaders = [];
        for ($i = 6; $i <= 8; $i++) {
            foreach ($rows[$i] as $key => $value) {
                if (!isset($excelHeaders[$key])) $excelHeaders[$key] = '';
                $excelHeaders[$key] .= trim($value) . '_';
            }
        }
        // ✅ FIX: แก้ไข Parse Error
        $excelHeaders = array_map(function($v) {
            return rtrim($v,'_');
        }, $excelHeaders);

        // Prepare SQL
        $sqlColumns = implode(',', array_values($headerToColumn));
        $placeholders = implode(',', array_fill(0, count($headerToColumn), '?'));

        $stmt = $mysqli->prepare("INSERT INTO eclaim_data ($sqlColumns) VALUES ($placeholders)");
        if(!$stmt) {
             if (file_exists($filePath)) unlink($filePath);
             die("❌ Prepare failed: " . $mysqli->error);
        }

        $total_rows = count($rows);
        $error_messages = [];

        for ($i = START_ROW + 1; $i <= $total_rows; $i++) {
            $data = [];
            $types = '';
            $is_empty_row = true;
            $rep_no_value = null;

            // Loop ผ่านคอลัมน์ที่ต้องการทั้งหมด
            foreach ($headerToColumn as $excelHeader => $sqlCol) {
                // ค้นหา Excel Column Index
                $excelKey = array_search($excelHeader, $excelHeaders);
                $value = $excelKey !== false ? ($rows[$i][$excelKey] ?? null) : null;

                // ตรวจสอบว่าแถวนี้มีข้อมูลหรือไม่
                if ($value !== null && $value !== '') {
                    $is_empty_row = false;
                }

                if ($sqlCol === 'rep_no') {
                    $rep_no_value = $value;
                }

                // แปลง type และ format
                $type = $columnType[$sqlCol];

                if ($value === null || $value === '') {
                    $value = null;
                } else {
                    switch ($type) {
                        case 'i': // integer
                            $value = (int)$value;
                            break;
                        case 'd': // decimal/double
                            $value = (float)str_replace(',','',$value);
                            break;
                        case 's': // string, varchar, text, date
                            if ($sqlCol === 'admit_date' || $sqlCol === 'discharge_date') {
                                // แปลงค่าวันที่ Excel เป็น format 'YYYY-MM-DD'
                                $value = excel_date_to_sql($value);
                            } else {
                                $value = (string)$value;
                            }
                            break;
                    }
                }

                $data[] = $value;
                $types .= $type;
            }

            // ข้ามแถวที่ว่างเปล่า
            if ($is_empty_row) continue;

            $stmt->bind_param($types, ...$data);

            if (!$stmt->execute()) {
                 $error_messages[] = "Row $i (REP_NO: " . ($rep_no_value ?? 'N/A') . ") Error: " . $stmt->error;
                 $skipped++;
            } else {
                 $imported++;
            }
        }

        $stmt->close();

        // ลบไฟล์ที่อัปโหลดหลังจากนำเข้าสำเร็จ
        if (file_exists($filePath)) unlink($filePath);

        echo "<h2>✅ นำเข้าข้อมูลเสร็จสิ้น</h2>";
        echo "<p>จำนวนแถวที่นำเข้าสำเร็จ: <b>$imported</b></p>";

        if ($skipped > 0) {
            echo "<p style='color:red;'>⚠️ **พบข้อผิดพลาด** (นำเข้าไม่สำเร็จ): <b>$skipped</b> แถว</p>";
            echo "<h3>รายละเอียดข้อผิดพลาด:</h3><ul style='color:red;'>";
            foreach($error_messages as $err) {
                echo "<li>" . htmlspecialchars($err) . "</li>";
            }
            echo "</ul>";
            echo "<p>❌ **สาเหตุที่พบบ่อย**: 1. ค่า REP_No_ ซ้ำซ้อน (Primary Key) 2. ข้อมูลยาวเกินขนาดฟิลด์ (VARCHAR/DECIMAL) 3. ข้อมูลวันที่ไม่ถูกต้อง</p>";
        }

        echo "<a href='eclaim_import_excel.php'>⬅ กลับไปหน้าแรก</a>";

    } catch (Exception $e) {
        if (file_exists($filePath)) unlink($filePath);
        die("❌ ผิดพลาดรุนแรง: ".$e->getMessage());
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Import E-Claim Excel</title>
    <style>
        /* (Style code is omitted for brevity but included in the previous response) */
        body { font-family: Tahoma, sans-serif; }
        h1 { color: #007bff; }
        h2 { color: #28a745; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 5px; font-size: 16px; }
        button[type="submit"] { background-color: #28a745; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .upload-form { border: 1px solid #ccc; padding: 20px; border-radius: 5px; background-color: #f9f9f9; }
        a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <?php if (!isset($_POST['confirmed']) && !isset($_FILES['excel'])): ?>
    <div class="container">
        <h1>นำเข้าข้อมูล E-Claim จาก Excel</h1>
        <p>⚠️ **คำแนะนำ:** ตรวจสอบโครงสร้างไฟล์ Excel ให้แน่ใจว่า **หัวตารางอยู่ที่บรรทัด 6, 7, 8** และ **ข้อมูลเริ่มที่บรรทัด 9**</p>
        <form class="upload-form" method="post" enctype="multipart/form-data">
            <label for="excel">เลือกไฟล์ Excel (.xlsx, .xls):</label>
            <input type="file" name="excel" id="excel" accept=".xlsx, .xls" required>
            <button type="submit">อัปโหลดและพรีวิว</button>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>
