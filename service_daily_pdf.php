<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;

$config = include 'config.php';

$host = $config['db_host'];
$port = $config['db_port'];
$db   = $config['db_name'];
$user = $config['db_user'];
$pass = $config['db_pass'];

$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset("utf8");

$start = $_GET['start'] ?? date("Y-m-01");
$end   = $_GET['end']   ?? date("Y-m-t");

$sql = "
SELECT
    v.visitdate AS service_date,
    SUM(CASE WHEN v.flagservice='03' AND v.timeservice='1' THEN 1 ELSE 0 END) AS in_office,
    SUM(CASE WHEN v.flagservice='03' AND v.timeservice='2' THEN 1 ELSE 0 END) AS out_office
FROM visit v
LEFT JOIN visitdiag vd ON v.visitno = vd.visitno
WHERE v.visitdate BETWEEN '$start' AND '$end'
GROUP BY v.visitdate
ORDER BY v.visitdate
";

$result = $conn->query($sql);

$html = '
<style>
body { font-family:"TH Sarabun New"; font-size:18px; }
table { width:100%; border-collapse:collapse; }
table, th, td { border:1px solid #333; }
th, td { padding:5px; text-align:center; }
th { background:#ddd; }
</style>

<h3>รายงานจำนวนผู้รับบริการระหว่างวันที่ '.$start.' ถึง '.$end.'</h3>

<table>
<tr>
    <th>วันที่</th>
    <th>ในเวลา</th>
    <th>นอกเวลา</th>
    <th>รวม</th>
</tr>';

while ($r = $result->fetch_assoc()) {
    $total = $r['in_office'] + $r['out_office'];

    $html .= "
    <tr>
        <td>{$r['service_date']}</td>
        <td>{$r['in_office']}</td>
        <td>{$r['out_office']}</td>
        <td>{$total}</td>
    </tr>";
}

$html .= "</table>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();
$dompdf->stream("service_daily.pdf", ["Attachment" => 1]);
