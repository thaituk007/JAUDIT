<?php
session_start();
if (!($_SESSION['logged_in'] ?? false)) {
    http_response_code(403);
    exit('Access denied');
}

$txtFile = __DIR__ . '/export_cid.txt';
$zipFile = __DIR__ . '/export_cid.zip';

if (!file_exists($txtFile)) {
    http_response_code(404);
    exit('File not found');
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    exit('Failed to create ZIP');
}

$zip->addFile($txtFile, 'export_cid.txt');
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="export_cid.zip"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
exit;
