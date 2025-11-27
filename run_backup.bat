@echo off
"C:\AppServ\www\JHCISAUDITS\php.exe" "C:\AppServ\www\JHCISAUDITS\backup.php"

@echo off
:: ตั้งค่าพาธของ PHP และ backup.php
set PHP_PATH=C:\AppServ\php7\php.exe
set SCRIPT_PATH=C:\AppServ\www\JAUDIT\backup.php

:: สร้าง path log โดยใช้วันที่ปัจจุบัน
for /f %%i in ('powershell -command "Get-Date -Format yyyy-MM-dd"') do set LOG_DATE=%%i
set LOG_DIR=C:\AppServ\www\logs
set LOG_FILE=%LOG_DIR%\backup_%LOG_DATE%.log

:: สร้างโฟลเดอร์ logs ถ้ายังไม่มี
if not exist %LOG_DIR% (
    mkdir %LOG_DIR%
)

:: รัน backup.php แล้วบันทึก log
"%PHP_PATH%" "%SCRIPT_PATH%" >> "%LOG_FILE%" 2>&1
