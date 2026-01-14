@echo off
REM ========================================
REM MikroTik CHR Download and Setup Helper
REM For Easy-HotSpot Cafe WiFi System
REM ========================================

echo.
echo =====================================
echo  MikroTik CHR Setup Helper
echo =====================================
echo.

REM Check if VirtualBox is installed
echo Checking for VirtualBox...
where VBoxManage >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [!] VirtualBox NOT FOUND
    echo.
    echo Please download and install VirtualBox:
    echo https://www.virtualbox.org/wiki/Downloads
    echo.
    echo After installing, run this script again.
    pause
    exit /b 1
) else (
    echo [OK] VirtualBox found!
    VBoxManage --version
)

echo.
echo =====================================
echo.

REM Create VM folder
set "VM_FOLDER=%USERPROFILE%\VMs\MikroTik"
if not exist "%VM_FOLDER%" (
    echo Creating folder: %VM_FOLDER%
    mkdir "%VM_FOLDER%"
)

echo.
echo Download MikroTik CHR from:
echo https://mikrotik.com/download
echo.
echo Look for "Cloud Hosted Router" section
echo Download the VDI (VirtualBox) image
echo.
echo Save it to: %VM_FOLDER%
echo.
echo After downloading, extract the ZIP file.
echo.
pause

echo.
echo =====================================
echo  VirtualBox Network Check
echo =====================================
echo.

echo Listing available network adapters...
echo.
VBoxManage list bridgedifs

echo.
echo =====================================
echo.
echo Use one of the above adapters for "Bridged Adapter" mode.
echo This allows the MikroTik VM to get an IP on your network.
echo.

pause
