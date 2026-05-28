# backend/database

Purpose of this folder:

- Laravel migration history (`migrations/`)
- Factories for tests (`factories/`)
- Local sqlite file for framework/dev convenience (`database.sqlite`)

Operational DB restore source is **not** this folder.

For fast recovery and baseline rebuild, use root `database/` and:

```powershell
.\scripts\db-restore.ps1 -Mode sql-base
```

See `scripts/DB_RESTORE.md` for full restore/backup flow.

