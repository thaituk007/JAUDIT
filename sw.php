<?php
error_reporting(0);

$conf = "C:/AppServ/Apache24/conf/httpd.conf";

$php_versions = [
    'PHP 5.6' => ['ver'=>'5.6','path'=>'C:/AppServ/php5/'],
    'PHP 7.0' => ['ver'=>'7.0','path'=>'C:/AppServ/php7/'],
    'PHP 8.0' => ['ver'=>'8.0','path'=>'C:/AppServ/php8/'],
];

echo "\n     Change PHP version for AppServ\n\n";

$choices = array_keys($php_versions);

// ฟังก์ชัน CLI menu แบบลูกศร
function chooseOption($options) {
    $selected = 0;

    system(''); // Enable ANSI escape codes in Windows

    while (true) {
        // แสดงเมนู
        foreach ($options as $i => $option) {
            if ($i == $selected) {
                echo "\033[30;47m> $option \033[0m\n"; // ไฮไลต์แถวที่เลือก
            } else {
                echo "  $option\n";
            }
        }

        // อ่าน key input
        $key = trim(fgets(STDIN));

        // แปลง key เป็น index
        if ($key == 'w' && $selected > 0) $selected--;    // w = up
        if ($key == 's' && $selected < count($options)-1) $selected++; // s = down
        if ($key == "\n" || $key == "\r" || $key == "") break;

        // เคลียร์หน้าจอ
        echo chr(27)."[H".chr(27)."[2J";
    }

    return $options[$selected];
}

$chosen = chooseOption($choices);

$ver = $php_versions[$chosen]['ver'];
$php_path = $php_versions[$chosen]['path'];

// ตรวจสอบโฟลเดอร์ PHP
if (!is_dir($php_path)) {
    echo "\n Error: PHP path not found -> $php_path\n";
    exit;
}

echo "\n Changing to PHP version: $ver\n";

exec("net stop apache24");

$file = file($conf);
$data = '';

foreach ($file as $line) {
    if (preg_match("/LoadModule php/", $line)) {
        $line = preg_replace('/php\d+/', "php$ver", $line);
    }
    if (preg_match("/PHPIniDir/", $line)) {
        $line = "PHPIniDir \"$php_path\"\r\n";
    }
    $data .= $line;
}

file_put_contents($conf, $data);

exec("net start apache24");

echo "\n ########## Completed ##########\n";
?>
