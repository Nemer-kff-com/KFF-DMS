@echo off
if not exist "C:\backup\dms" mkdir "C:\backup\dms"
"C:\xampp\php\php.exe" -q "C:\xampp\htdocs\dms\tools\backup-maintenance\cron_scheduler.php" >> "C:\backup\dms\scheduler.log" 2>&1
