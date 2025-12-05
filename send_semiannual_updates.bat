@echo off
echo Starting Semiannual Update Notifications...
echo %date% %time%

REM Change to your PHP installation directory
REM If you're using XAMPP, PHP is usually here:
C:\xampp\php\php.exe "C:\xampp\htdocs\YourProjectFolder\api\notification\run_semiannual_updates.php"

echo Completed.
pause