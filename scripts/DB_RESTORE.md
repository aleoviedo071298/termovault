# DB Restore Playbook

This repository has two DB-related folders with different roles:

- `database/` (repo root): operational SQL baseline used for fast recovery.
- `backend/database/`: Laravel migrations/factories used by framework workflows and tests.

Use one restore path at a time.

## Stable Restore Order (official)

Always restore in this exact order:

1. Schema (`database/schema.sql`)
2. Catalogs (`seed-roles`, `seed-tipos-elemento`, `seed-niveles-tension`, `seed-criticidades`)
3. Operational data (`seed-empresas`, `seed-elementos`)

This is the safest path to recover quickly without breaking FK relationships.

## 1) Fast Operational Restore (recommended)

Rebuild from root SQL files:

```powershell
.\scripts\db-restore.ps1 -Mode sql-base
```

The script already applies files in the official order above.

Use this when you need to recover quickly to the known baseline.

## 2) Laravel Migration Restore

Rebuild through Laravel migrations/seeds:

```powershell
.\scripts\db-restore.ps1 -Mode migrations
```

Use this when validating framework evolution or migration compatibility.

## 3) Create Backup Before Risky Changes

```powershell
.\scripts\db-backup.ps1
```

Outputs are written to `backups/`:
- full SQL dump
- schema-only SQL dump

## 4) Post-Restore Validation Checklist

Run these quick checks after restore:

```powershell
docker compose exec -T postgres psql -U termovault -d termovault -c "SELECT COUNT(*) AS empresas FROM empresas;"
docker compose exec -T postgres psql -U termovault -d termovault -c "SELECT COUNT(*) AS yacimientos FROM yacimientos;"
docker compose exec -T postgres psql -U termovault -d termovault -c "SELECT COUNT(*) AS elementos FROM elementos;"
docker compose exec -T postgres psql -U termovault -d termovault -c "SELECT column_name FROM information_schema.columns WHERE table_name='yacimientos' AND column_name='permite_supervisor_elementos';"
```

Expected:
- `empresas`, `yacimientos`, and `elementos` return values greater than zero in your baseline.
- `permite_supervisor_elementos` exists in `yacimientos`.
