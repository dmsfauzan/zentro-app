#Requires -Version 5.1
<#
.SYNOPSIS
    Restore database MySQL ZENTRO (dan opsional storage) dari hasil backup.ps1.

.DESCRIPTION
    PERINGATAN: operasi ini menimpa data pada database tujuan. Gunakan hanya
    pada lingkungan lokal/staging. Konfirmasi interaktif diperlukan kecuali
    `-Force` diberikan.

.EXAMPLE
    pwsh scripts/restore.ps1 -SqlFile backups/zentro-db-20261110-120000.sql
    pwsh scripts/restore.ps1 -SqlFile ... -StorageZip backups/zentro-storage-....zip -Force
#>
param(
    [Parameter(Mandatory = $true)]
    [string]$SqlFile,
    [string]$StorageZip,
    [string]$Database,
    [string]$User,
    [string]$Password,
    [string]$HostName,
    [int]$Port = 0,
    [switch]$Force
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

if (-not (Test-Path $SqlFile)) { throw "File SQL tidak ditemukan: $SqlFile" }
if (-not (Get-Command mysql -ErrorAction SilentlyContinue)) {
    throw "mysql client tidak ditemukan di PATH."
}

if (-not $Force) {
    $answer = Read-Host "Timpa database '$Database' di $HostName`:$Port dengan $SqlFile ? (ketik 'yes')"
    if ($answer -ne "yes") { throw "Dibatalkan." }
}

$previousPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = $Password
try {
    $args = @("--host=$HostName", "--port=$Port", "--user=$User", "--default-character-set=utf8mb4", $Database)
    $quoted = $args | ForEach-Object { '"' + $_ + '"' }
    $cmd = "mysql $($quoted -join ' ') < `"$SqlFile`""
    cmd /c $cmd
    if ($LASTEXITCODE -ne 0) { throw "mysql restore gagal (exit $LASTEXITCODE)." }
}
finally {
    $env:MYSQL_PWD = $previousPassword
}

Write-Host "Database direstore dari: $SqlFile" -ForegroundColor Green

if ($StorageZip) {
    if (-not (Test-Path $StorageZip)) { throw "Zip storage tidak ditemukan: $StorageZip" }
    $storage = Join-Path $root "backend\storage\app"
    New-Item -ItemType Directory -Force -Path $storage | Out-Null
    Expand-Archive -Path $StorageZip -DestinationPath $storage -Force
    Write-Host "Storage direstore dari: $StorageZip" -ForegroundColor Green
}

Write-Host "Restore selesai. Jalankan 'php artisan migrate --force' bila skema berubah." -ForegroundColor Green
