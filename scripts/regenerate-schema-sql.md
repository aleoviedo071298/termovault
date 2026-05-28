# Regenerating database/schema.sql

## Context

The `database/schema.sql` file is a **generated artifact** that provides a DDL snapshot of the current database schema. It is used by `docker-compose` to initialize a fresh Postgres container.

However, the canonical source of truth for the schema is:
- **Laravel migrations** in `backend/database/migrations/` (primary)
- Applied in order, they define the actual schema

## Why Two Sources Exist

1. **Docker initialization**: `postgres-init.sh` applies `schema.sql` for fast setup
2. **Development evolution**: Migrations can be applied post-init to update the schema
3. **Migration-based droppers**: Migration `2026_05_25_000000_create_termovault_tables.php` drops and recreates all tables in `local|testing` environments

## Keeping Them in Sync

### When to Regenerate

After running a migration that changes the schema structure (not just data), regenerate the snapshot:

```bash
# 1. Start fresh database with migrations applied
docker compose up -d
cd backend
php artisan migrate
php artisan seed

# 2. From a separate terminal, export the current schema (NO data)
docker compose exec postgres pg_dump \
  --schema-only \
  --no-owner \
  --no-privileges \
  -U termovault \
  -d termovault > database/schema.sql

# 3. Commit the updated schema.sql
git add database/schema.sql
git commit -m "chore(db): regenerate schema.sql after migrations"
```

### Automated Regeneration (Future)

Ideally, this would be automated in CI/CD:
```yaml
# .github/workflows/db-validate.yml
- name: Regenerate schema.sql
  run: |
    docker compose up -d postgres
    docker compose exec -T postgres pg_dump --schema-only ... > database/schema.sql
    git diff database/schema.sql  # Ensure no changes (migrations should be self-sufficient)
```

## Important Notes

- **Do NOT edit `schema.sql` manually** — it's generated, not maintained by hand
- Migrations are the canonical source; always update via `php artisan make:migration`
- The seed SQL files (`seed-*.sql`) are applied **after** schema.sql and handle data initialization
- In production, migrations are applied as part of the deployment process, not docker-compose

## Migration Behavior by Environment

| Environment | Behavior |
|-------------|----------|
| `local` | `2026_05_25_000000` drops all tables, recreates them. Data regenerated from seeds. |
| `testing` | Same as local (fresh state for each test run) |
| `production` | Migrations applied without drop (preserves data) |

This ensures dev/test environments are always in a clean state, while production evolves gradually.
