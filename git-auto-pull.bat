@echo off
echo =========================================
echo  Nile Center - Git Auto Pull
echo =========================================
echo.

REM مسار المشروع - عدل المسار لو المشروع في مكان تاني
set PROJECT_PATH=C:\xampp\htdocs\nile-center-system
set LOG_FILE=%PROJECT_PATH%\git-pull.log

echo [%date% %time%] Starting auto-pull... >> "%LOG_FILE%"

cd /d "%PROJECT_PATH%"

REM Check if git is available
where git >nul 2>&1
if errorlevel 1 (
    echo [%date% %time%] ERROR: Git not found! >> "%LOG_FILE%"
    echo ERROR: Git not found! Install Git first.
    pause
    exit /b 1
)

REM Fetch latest changes
git fetch origin main >> "%LOG_FILE%" 2>&1
if errorlevel 1 (
    echo [%date% %time%] ERROR: Git fetch failed! >> "%LOG_FILE%"
    echo ERROR: Git fetch failed! Check internet connection.
    pause
    exit /b 1
)

REM Check if there are new changes
for /f "tokens=*" %%a in ('git rev-list HEAD...origin/main --count') do set CHANGES=%%a

if "%CHANGES%"=="0" (
    echo [%date% %time%] No changes. Already up to date. >> "%LOG_FILE%"
    echo =========================================
    echo  No new updates found.
    echo  You are on the latest version!
    echo =========================================
    goto END
)

echo [%date% %time%] Found %CHANGES% new commit(s). Pulling... >> "%LOG_FILE%"
echo =========================================
echo  Found %CHANGES% new update(s)!
echo  Downloading now...
echo =========================================
echo.

git pull origin main >> "%LOG_FILE%" 2>&1
if errorlevel 1 (
    echo [%date% %time%] ERROR: Git pull failed! >> "%LOG_FILE%"
    echo ERROR: Git pull failed! Check for conflicts.
    pause
    exit /b 1
)

echo [%date% %time%] Pull completed successfully! >> "%LOG_FILE%"
echo =========================================
echo  Update completed successfully!
echo  %CHANGES% new commit(s) downloaded.
echo =========================================

:END
echo.
echo Log file: %LOG_FILE%
echo.
echo Press any key to close...
pause >nul
