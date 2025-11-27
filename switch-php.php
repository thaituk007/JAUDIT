<?php
$phpVersions = [
    '5.6' => 'C:\\AppServ\\php5',
    '7.0' => 'C:\\AppServ\\php7',
    '8.0' => 'C:\\AppServ\\php8',
];

$webService = "Apache2.4"; // Apache service name
$batFile = "C:\\AppServ\\www\\JAUDIT\\switch-php-system.bat";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['version'])) {
    $version = $_POST['version'];

    if (!isset($phpVersions[$version])) {
        $message = ['text' => "Invalid PHP version selected!", 'type' => 'error'];
    } elseif (!file_exists($batFile)) {
        $message = ['text' => "Batch file not found!", 'type' => 'error'];
    } else {
        $phpPath = $phpVersions[$version];

        // --- Step 1: Switch CLI PHP version via batch file ---
        $cmdPHP = "powershell -Command \"Start-Process cmd -ArgumentList '/c \\\"$batFile $version\\\"' -Verb runAs\"";
        exec($cmdPHP, $outputPHP, $returnPHP);

        $msg = '';
        if ($returnPHP === 0) {
            $msg .= "CLI PHP version switched to $version successfully!<br>";
        } else {
            $msg .= "Failed to switch CLI PHP version.<br>";
        }

        // --- Step 2: Update php.ini for Apache ---
        $apacheConf = "C:\\AppServ\\Apache24\\conf\\httpd.conf";
        if(file_exists($apacheConf)){
            $confContent = file_get_contents($apacheConf);
            $confContent = preg_replace('/PHPIniDir\s+"[^"]+"/i', 'PHPIniDir "'.$phpPath.'"', $confContent);
            file_put_contents($apacheConf, $confContent);
            $msg .= "Apache php.ini updated to PHP $version.<br>";
        } else {
            $msg .= "Apache config not found, please check path.<br>";
        }

        // --- Step 3: Restart Apache ---
        $cmdWeb = "powershell -Command \"Start-Process powershell -ArgumentList 'Restart-Service -Name $webService -Force' -Verb runAs\"";
        exec($cmdWeb, $outputWeb, $returnWeb);

        if($returnWeb === 0){
            $msg .= "Web server ($webService) restarted successfully.";
            $message = ['text' => $msg, 'type' => 'success'];
        } else {
            $msg .= "Failed to restart web server ($webService).";
            $message = ['text' => $msg, 'type' => 'error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Switch PHP Version + Restart Web Server</title>
<style>
@import url('https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap');
body{font-family:'Roboto',sans-serif;background:#121212;color:#f0f0f0;margin:0;padding:50px;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;}
.container{background:#1e1e1e;padding:40px 30px;border-radius:12px;box-shadow:0 12px 25px rgba(0,0,0,0.5);max-width:450px;width:100%;transition: transform 0.2s ease;}
.container:hover{transform: translateY(-3px);}
h1{text-align:center;color:#fff;margin-bottom:25px;font-weight:500;}
.message{padding:12px 15px;border-radius:8px;margin-bottom:20px;text-align:center;font-weight:500;}
.message.success{background:#4CAF50;color:#fff;}
.message.error{background:#f44336;color:#fff;}
form label{display:block;margin-bottom:8px;color:#ccc;font-weight:500;}
select{width:100%;padding:12px;border-radius:8px;border:1px solid #555;background:#222;color:#fff;font-size:16px;margin-bottom:20px;transition: all 0.2s ease;}
select:focus{border-color:#007BFF;box-shadow:0 0 5px rgba(0,123,255,0.3);outline:none;}
button{width:100%;padding:12px;border-radius:8px;border:none;background:linear-gradient(45deg,#ff6b6b,#f06595);color:#fff;font-size:16px;font-weight:500;cursor:pointer;transition: all 0.2s ease;}
button:hover{background:linear-gradient(45deg,#f06595,#ff6b6b);transform:translateY(-2px);}
button:active{transform:translateY(0);}
@media (max-width:480px){body{padding:20px;}.container{padding:30px 20px;}}
</style>
</head>
<body>
<div class="container">
<h1>Switch PHP Version + Restart Server</h1>
<?php
if($message){
    echo '<div class="message '.$message['type'].'">'.htmlspecialchars($message['text']).'</div>';
}
?>
<form method="post">
<label for="version">Select PHP Version:</label>
<select name="version" id="version" required>
<option value="">-- Select --</option>
<?php
foreach($phpVersions as $v=>$path){
    echo '<option value="'.htmlspecialchars($v).'">PHP '.htmlspecialchars($v).'</option>';
}
?>
</select>
<button type="submit">Switch & Restart</button>
</form>
</div>
</body>
</html>
