# Regenerating `database/schema.sql`

## Context

`database/schema.sql` is a generated DDL snapshot of the current Postgres schema. It is useful for Docker initialization, but it is not the authority for cleanup decisions if it contradicts the real DB.

For DB-related work, use this order of confidence:

1. Current database structure and data.
2. Root `database/` files as operational reference.
3. Laravel migrations in `backend/database/migrations/` as framework history/support.
4. Older docs only as historical notes.

## When to Regenerate

Regenerate after a migration changes table structure, indexes, constraints or FK relationships.

## Command

From the repository root:

```bash
docker compose exec -T postgres pg_dump \
  --schema-only \
  --no-owner \
  --no-privileges \
  -U termovault \
  -d termovault > database/schema.sql
```

On PowerShell, prefer UTF-8 output:

```powershell
docker compose exec -T postgres pg_dump --schema-only --no-owner --no-privileges -U termovault -d termovault |
  Set-Content -Encoding utf8 database\schema.sql
```

## Notes

- Do not hand-edit `database/schema.sql`.
- Do not infer drops from this file alone; inspect the current DB first.
- Do not include data dumps in Git.
- Production should evolve through migrations/backups, not by replaying local init SQL over real data.
