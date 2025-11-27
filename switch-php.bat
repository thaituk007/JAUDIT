@echo off
SETLOCAL ENABLEDELAYEDEXPANSION

IF "%1"=="" (
    echo Usage: switch-php-system.bat [5|7|8.4]
    exit /b 1
)

REM --- PHP folder mapping ---
IF "%1"=="5" SET "PHP_PATH=C:\AppServ\php5"
IF "%1"=="7" SET "PHP_PATH=C:\AppServ\php7"
IF "%1"=="8.4" SET "PHP_PATH=C:\php8.4"

REM --- Update SYSTEM PATH ---
FOR /F "tokens=2*" %%A IN ('REG QUERY "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path') DO SET "OLD_PATH=%%B"
SET "NEW_PATH=!OLD_PATH!"

FOR %%V IN (C:\AppServ\php5 C:\AppServ\php7 C:\php8.4) DO (
    SET "NEW_PATH=!NEW_PATH:%%V;=!"
    SET "NEW_PATH=!NEW_PATH:%%V=!"
)
SET "NEW_PATH=%PHP_PATH%;%NEW_PATH%"
REG ADD "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment" /v Path /t REG_EXPAND_SZ /d "%NEW_PATH%" /f

echo PHP PATH updated to %PHP_PATH%
ENDLOCAL
