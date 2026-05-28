param(
  [ValidateSet("sql-base", "migrations")]
  [string]$Mode = "sql-base",
  [string]$DbName = "termovault",
  [string]$DbUser = "termovault"
)

$ErrorActionPreference = "Stop"

$repo = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$dbDir = Join-Path $repo "database"
$backendDir = Join-Path $repo "backend"

Write-Host "-> Restore mode: $Mode"

if ($Mode -eq "sql-base") {
  Write-Host "-> Recreating schema from root database/schema.sql"
  docker compose exec -T postgres psql -U $DbUser -d $DbName -f /sql/schema.sql

  $seedFiles = @(
    "seed-roles.sql",
    "seed-tipos-elemento.sql",
    "seed-niveles-tension.sql",
    "seed-criticidades.sql",
    "seed-empresas.sql",
    "seed-elementos.sql"
  )

  foreach ($file in $seedFiles) {
    Write-Host "-> Applying /sql/$file"
    docker compose exec -T postgres psql -U $DbUser -d $DbName -f ("/sql/" + $file)
  }

  Write-Host "-> SQL-base restore complete."
  exit 0
}

if ($Mode -eq "migrations") {
  Push-Location $backendDir
  try {
    Write-Host "-> Running Laravel migrate --force"
    php artisan migrate --force
    Write-Host "-> Running Laravel db:seed --force"
    php artisan db:seed --force
  } finally {
    Pop-Location
  }

  Write-Host "-> Migration-based restore complete."
}

