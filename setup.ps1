#!/usr/bin/env pwsh
# =============================================================
# ERP BCS Backend - Setup Script (v2)
# Jalankan: powershell -ExecutionPolicy Bypass -File setup.ps1
# =============================================================

Write-Host "ERP BCS Backend Setup Script" -ForegroundColor Cyan
Write-Host "=============================" -ForegroundColor Cyan

# Step 1: Check pdo_pgsql
Write-Host "`n[1/4] Checking PHP pdo_pgsql..." -ForegroundColor Yellow
$check = php -r "echo extension_loaded('pdo_pgsql') ? 'OK' : 'MISSING';"
Write-Host "  pdo_pgsql: $check"
if ($check -eq 'MISSING') {
    Write-Host "  Enabling pdo_pgsql..." -ForegroundColor Yellow
    (Get-Content 'E:\xampp8\php\php.ini') -replace ';extension=pdo_pgsql','extension=pdo_pgsql' | Set-Content 'E:\xampp8\php\php.ini'
    (Get-Content 'E:\xampp8\php\php.ini') -replace ';extension=pgsql','extension=pgsql' | Set-Content 'E:\xampp8\php\php.ini'
    Write-Host "  Done. Please re-run this script." -ForegroundColor Green
    exit
}

# Step 2: Create PostgreSQL schema
Write-Host "`n[2/4] Creating 'erp' schema in master_db..." -ForegroundColor Yellow
$result = php create_schema.php
Write-Host "  $result"
if ($result -match "ERROR") {
    Write-Host "  Schema creation failed! Is PostgreSQL running?" -ForegroundColor Red
    exit 1
}

# Step 3: Run migrations
Write-Host "`n[3/4] Running migrations..." -ForegroundColor Yellow
php artisan config:clear
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Migration failed!" -ForegroundColor Red
    exit 1
}
Write-Host "  Migrations done!" -ForegroundColor Green

# Step 4: Run seeders
Write-Host "`n[4/4] Seeding sample data..." -ForegroundColor Yellow
php artisan db:seed --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Seeding failed!" -ForegroundColor Red
    exit 1
}

Write-Host "`n=============================" -ForegroundColor Green
Write-Host "ERP BCS Backend READY!" -ForegroundColor Green
Write-Host "=============================" -ForegroundColor Green
Write-Host "Start : php artisan serve --port=8001" -ForegroundColor Cyan
Write-Host "Login : admin@bcs-logistics.co.id / password123" -ForegroundColor Cyan
Write-Host "URL   : http://127.0.0.1:8001/api/v1" -ForegroundColor Cyan
