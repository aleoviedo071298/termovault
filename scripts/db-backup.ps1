param(
  [string]$DbName = "termovault",
  [string]$DbUser = "termovault",
  [string]$Container = "termovault-postgres"
)

$ErrorActionPreference = "Stop"

$repo = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$backupDir = Join-Path $repo "backups"
if (-not (Test-Path -LiteralPath $backupDir)) {
  New-Item -ItemType Directory -Path $backupDir | Out-Null
}

$stamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
$dumpPath = Join-Path $backupDir "$DbName`_$stamp.sql"
$schemaPath = Join-Path $backupDir "$DbName`_schema_$stamp.sql"

Write-Host "-> Exporting full dump to $dumpPath"
docker compose exec -T postgres pg_dump -U $DbUser -d $DbName --no-owner --no-privileges | Set-Content -Encoding utf8 -Path $dumpPath

Write-Host "-> Exporting schema-only SQL to $schemaPath"
docker compose exec -T postgres pg_dump -U $DbUser -d $DbName --schema-only --no-owner --no-privileges | Set-Content -Encoding utf8 -Path $schemaPath

Write-Host ""
Write-Host "Backup finished:"
Write-Host "  dump   : $dumpPath"
Write-Host "  schema : $schemaPath"
