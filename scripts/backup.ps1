#Requires -Version 5.1
<#
.SYNOPSIS
    Backup database MySQL + storage ZENTRO ke folder `backups/`.

.DESCRIPTION
    Membaca konfigurasi DB dari `backend/.env` (bila ada) atau parameter.
    Menghasilkan:
      - zentro-db-<timestamp>.sql
      - zentro-storage-<timestamp>.zip  (storage/app: dokumen seller, gambar, dsb.)

.PARAMETER OutputDir
    Folder tujuan. Default: <root>/backups

.EXAMPLE
    pwsh scripts/backup.ps1
    pwsh scripts/backup.ps1 -Database zentro -User root -Password "secret"
#>
param(
    [string]$Database,
    [string]$User,
    [string]$Password,
    [string]$HostName,
    [int]$Port = 0,
    [string]$OutputDir,
    [switch]$SkipStorage
)

$ErrorActionPreference = "Stop"

function Read-DotEnv([string]$path) {
    $values = @{}
    if (-not (Test-Path $path)) { return $values }
    foreach ($line in Get-Content $path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#")) { continue }
        $idx = $trimmed.IndexOf("=")
        if ($idx -lt 1) { continue }
        $key = $trimmed.Substring(0, $idx).Trim()
        $val = $trimmed.Substring($idx + 1).Trim().Trim('"')
        $values[$key] = $val
    }
    return $values
}

$root = Split-Path -Parent $PSScriptRoot
$env = Read-DotEnv (Join-Path $root "backend\.env")

if (-not $Database) { $Database = if ($env["DB_DATABASE"]) { $env["DB_DATABASE"] } else { "zentro" } }
if (-not $User) { $User = if ($env["DB_USERNAME"]) { $env["DB_USERNAME"] } else { "root" } }
if (-not $Password) { $Password = $env["DB_PASSWORD"] }
if (-not $HostName) { $HostName = if ($env["DB_HOST"]) { $env["DB_HOST"] } else { "127.0.0.1" } }
if ($Port -le 0) { $Port = if ($env["DB_PORT"]) { [int]$env["DB_PORT"] } else { 3306 } }

if (-not (Get-Command mysqldump -ErrorAction SilentlyContinue)) {
    throw "mysqldump tidak ditemukan di PATH. Pasang MySQL client / Laragon lalu coba lagi."
}

if (-not $OutputDir) { $OutputDir = Join-Path $root "backups" }
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$sqlFile = Join-Path $OutputDir "zentro-db-$timestamp.sql"

$previousPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = $Password
try {
    $args = @(
        "--host=$HostName",
        "--port=$Port",
        "--user=$User",
        "--single-transaction",
        "--routines",
        "--triggers",
        "--default-character-set=utf8mb4",
        $Database
    )
    $quoted = $args | ForEach-Object { '"' + $_ + '"' }
    $cmd = "mysqldump $($quoted -join ' ') > `"$sqlFile`""
    cmd /c $cmd
    if ($LASTEXITCODE -ne 0) { throw "mysqldump gagal (exit $LASTEXITCODE)." }
}
finally {
    $env:MYSQL_PWD = $previousPassword
}

Write-Host "Database  : $sqlFile"

if (-not $SkipStorage) {
    $storage = Join-Path $root "backend\storage\app"
    if (Test-Path $storage) {
        $zip = Join-Path $OutputDir "zentro-storage-$timestamp.zip"
        Compress-Archive -Path (Join-Path $storage "*") -DestinationPath $zip -Force
        Write-Host "Storage   : $zip"
    }
    else {
        Write-Host "Storage   : dilewati (backend/storage/app tidak ada)."
    }
}

Write-Host "Backup selesai: $timestamp" -ForegroundColor Green
