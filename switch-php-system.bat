@echo off
REM --------------------------------------
REM Batch Script สลับ PHP เวอร์ชันทั้งระบบ
REM --------------------------------------

SETLOCAL

IF "%1"=="" (
    echo Usage: switch-php-system.bat [5|7|8.4]
    echo Example: switch-php-system.bat 7
    exit /b 1
)

REM กำหนดโฟลเดอร์ PHP ตามเวอร์ชัน
IF "%1"=="5" (
    SET "PHP_PATH=C:\AppServ\php5"
) ELSE IF "%1"=="7" (
    SET "PHP_PATH=C:\AppServ\php7"
) ELSE IF "%1"=="8.4" (
    SET "PHP_PATH=C:\php8.4"
) ELSE (
    echo Invalid version! Use 5, 7, or 8.4
    exit /b 1
)

REM --------------------------------------
REM แก้ PATH ของ SYSTEM ผ่าน Registry
REM --------------------------------------
echo Updating SYSTEM PATH...
REM ดึง PATH เก่าของระบบ
FOR /F "tokens=2*" %%A IN ('REG QUERY "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path') DO SET "OLD_PATH=%%B"

REM ลบ PHP เดิมออกจาก PATH (ถ้ามี)
SET "NEW_PATH=%OLD_PATH%"
FOR %%V IN (C:\AppServ\php5 C:\AppServ\php7 C:\php8.4) DO (
    SET "NEW_PATH=!NEW_PATH:%%V;=!"
    SET "NEW_PATH=!NEW_PATH:%%V=!"
)

REM เพิ่ม PHP ใหม่ขึ้นต้น PATH
SET "NEW_PATH=%PHP_PATH%;%NEW_PATH%"

REM อัปเดต Registry
REG ADD "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path /t REG_EXPAND_SZ /d "%NEW_PATH%" /f

echo SYSTEM PATH updated to PHP version in: %PHP_PATH%
echo You may need to restart CMD or log off/log on to see the change.

ENDLOCAL
pause
@echo off
REM --------------------------------------
REM Batch Script สลับ PHP เวอร์ชันทั้งระบบ
REM --------------------------------------

SETLOCAL

IF "%1"=="" (
    echo Usage: switch-php-system.bat [5|7|8.4]
    echo Example: switch-php-system.bat 7
    exit /b 1
)

REM กำหนดโฟลเดอร์ PHP ตามเวอร์ชัน
IF "%1"=="5" (
    SET "PHP_PATH=C:\AppServ\php5"
) ELSE IF "%1"=="7" (
    SET "PHP_PATH=C:\AppServ\php7"
) ELSE IF "%1"=="8.4" (
    SET "PHP_PATH=C:\php8.4"
) ELSE (
    echo Invalid version! Use 5, 7, or 8.4
    exit /b 1
)

REM --------------------------------------
REM แก้ PATH ของ SYSTEM ผ่าน Registry
REM --------------------------------------
echo Updating SYSTEM PATH...
REM ดึง PATH เก่าของระบบ
FOR /F "tokens=2*" %%A IN ('REG QUERY "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path') DO SET "OLD_PATH=%%B"

REM ลบ PHP เดิมออกจาก PATH (ถ้ามี)
SET "NEW_PATH=%OLD_PATH%"
FOR %%V IN (C:\AppServ\php5 C:\AppServ\php7 C:\php8.4) DO (
    SET "NEW_PATH=!NEW_PATH:%%V;=!"
    SET "NEW_PATH=!NEW_PATH:%%V=!"
)

REM เพิ่ม PHP ใหม่ขึ้นต้น PATH
SET "NEW_PATH=%PHP_PATH%;%NEW_PATH%"

REM อัปเดต Registry
REG ADD "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path /t REG_EXPAND_SZ /d "%NEW_PATH%" /f

echo SYSTEM PATH updated to PHP version in: %PHP_PATH%
echo You may need to restart CMD or log off/log on to see the change.

ENDLOCAL
pause
