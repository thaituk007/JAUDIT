<?php
$username = getenv('USERNAME');
echo "USER: $username<br>";
$path1 = "C:\\Users\\{$username}\\SRM Smart Card Single Sign-On\\token.txt";
$path2 = __DIR__ . '\\token.txt';
echo "Check path1: " . (file_exists($path1) ? "Found" : "Not found") . "<br>";
echo "Check path2: " . (file_exists($path2) ? "Found" : "Not found") . "<br>";

?>
