@echo off
REM Menjalankan server PHP di http://localhost:8080
REM Endpoint: http://localhost:8080/uts/api/mahasiswa.php
REM           http://localhost:8080/uts/api/jurusan.php
cd /d "%~dp0.."
php -S localhost:8080 -t .
