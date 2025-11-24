@echo off
REM NPanel Development Update Script (Windows)
REM Usage: update-dev.bat

echo ==========================================
echo NPanel Development Update
echo ==========================================
echo.

echo ^>^>^> Pulling latest code...
git pull origin main
if errorlevel 1 (
    echo Error pulling from git
    pause
    exit /b 1
)

echo.
echo ^>^>^> Installing Composer dependencies...
composer install --no-interaction
if errorlevel 1 (
    echo Error installing composer dependencies
    pause
    exit /b 1
)

echo.
echo ^>^>^> Installing NPM dependencies...
call npm install --legacy-peer-deps
if errorlevel 1 (
    echo Warning: NPM install had issues
)

echo.
echo ^>^>^> Building frontend assets...
call npm run build
if errorlevel 1 (
    echo Error building assets
    pause
    exit /b 1
)

echo.
echo ^>^>^> Running database migrations...
php artisan migrate
if errorlevel 1 (
    echo Warning: Migration had issues
)

echo.
echo ^>^>^> Clearing caches...
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo.
echo ==========================================
echo Development update completed!
echo ==========================================
echo.

php artisan about

pause
