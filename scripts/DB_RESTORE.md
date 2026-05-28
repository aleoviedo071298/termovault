# DB Restore Playbook

This repository has two DB-related folders with different roles:

- `database/` (repo root): operational SQL baseline used for fast recovery.
- `backend/database/`: Laravel migrations/factories used by framework workflows and tests.

Use one restore path at a time.

## 1) Fast Operational Restore (recommended)

Rebuild from root SQL files:

```powershell
.\scripts\db-restore.ps1 -Mode sql-base
```

This applies:
- `database/schema.sql`
- all `database/seed-*.sql` files in fixed order

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

