<?php
// da_export_pdf.php
require_once __DIR__ . '/vendor/autoload.php';
$config = include 'config.php';

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);
$mysqli->set_charset("utf8");

// query
$sql = "
SELECT
    IF(t.titlecode IS NULL OR titlename IS NULL OR TRIM(titlename) = '', '..', titlename) AS prename,
    p.fname, p.lname,
    pg.daterecord AS daterecord,
    cd.drugname,
    h.hno, v.villno, v.villname,
    p.pid
FROM personalergic pg
JOIN person p ON pg.pcucodeperson = p.pcucodeperson AND pg.pid = p.pid
LEFT JOIN persondeath pd ON p.pcucodeperson = pd.pcucodeperson AND pd.pid = pg.pid
LEFT JOIN ctitle t ON p.prename = t.titlecode
JOIN cdrug cd ON pg.drugcode = cd.drugcode
JOIN house h ON p.pcucodeperson = h.pcucode AND p.hcode = h.hcode
JOIN village v ON h.pcucode = v.pcucode AND h.villcode = v.villcode
WHERE pd.pid IS NULL
  AND LEFT(v.villcode, 2) <> '00'
ORDER BY pg.pid
";
$result = $mysqli->query($sql);

// mPDF config: เพิ่ม fontDir และ fontdata เพื่อฝัง Prompt
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'tempDir' => __DIR__ . '/tmp', // ถ้าจำเป็น สำหรับสิทธิ์เขียน
    'fontDir' => array_merge($fontDirs, [ __DIR__ . '/fonts' ]),
    'fontdata' => $fontData + [
        'prompt' => [
            'R' => 'Prompt-Regular.ttf',
            'B' => 'Prompt-Bold.ttf',
            'I' => 'Prompt-Italic.ttf',
        ]
    ],
    'default_font' => 'prompt',
    'default_font_size' => 12
]);

// สร้าง HTML (ระบุ charset)
$html = '<html><head><meta charset="utf-8" /></head><body>';
$html .= '<h2 style="text-align:center;">รายงานผู้แพ้ยา</h2>';
$html .= '<table border="1" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-size:12pt;">';
$html .= '<thead><tr style="background:#f0f0f0;"><th>ลำดับ</th><th>ชื่อ - สกุล</th><th>แพ้ยา</th><th>วันที่บันทึก</th><th>บ้าน/หมู่</th><th>PID</th></tr></thead><tbody>';

$idx = 1;
while ($row = $result->fetch_assoc()) {
    $name = $row['prename'] . $row['fname'] . ' ' . $row['lname'];
    $addr = $row['hno'] . ' / หมู่ ' . $row['villno'] . ' ' . $row['villname'];
    $html .= "<tr>";
    $html .= "<td style='text-align:center;'>".$idx++."</td>";
    $html .= "<td>".$mpdf->HTMLParser->xmlentities($name)."</td>";
    $html .= "<td>".$mpdf->HTMLParser->xmlentities($row['drugname'])."</td>";
    $html .= "<td>".$mpdf->HTMLParser->xmlentities($row['daterecord'])."</td>";
    $html .= "<td>".$mpdf->HTMLParser->xmlentities($addr)."</td>";
    $html .= "<td>".$mpdf->HTMLParser->xmlentities($row['pid'])."</td>";
    $html .= "</tr>";
}

$html .= '</tbody></table></body></html>';

// เขียน PDF และส่งไปยังผู้ใช้
$mpdf->WriteHTML($html);
$mpdf->Output('drug_allergy_' . date('Ymd_His') . '.pdf','I');
exit;
