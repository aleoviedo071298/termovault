# TermoVault

Internal multi-tenant platform for managing thermographic inspection reports in the
Oil & Gas industry.

## Status

Active development. Core workflow is live: technicians submit inspection reports,
supervisors review and close them, with full role-based access control and audit
trails.

- **Roles**: `admin`, `supervisor` (contractor), `supervisor PAE` (site owner), `tecnico`.
- **Operational flow**: technician submits a report (`enviada`) → supervisor reviews
  it (`revisada`) → supervisor or admin closes it (`cerrada`).
- **Traceability**: every review/close action records `revisada_por` + `fecha_revision`
  and `cerrada_por` + `fecha_cierre`. File downloads are logged with user, IP, and
  user agent.
- Inactive users (`usuarios.activo = false`) are blocked at the API level.

## Tech Stack

| Layer | Stack |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 19 + Vite 8 + TypeScript |
| Database | PostgreSQL 16 |
| Storage | MinIO / S3 (`league/flysystem-aws-s3-v3`) |
| Auth | AWS Cognito (JWT) |
| Local infra | Docker Compose (Postgres, MinIO, Adminer) |

## Project Structure

```
termovault/
├── backend/        Laravel API
├── web/            React + Vite frontend
├── database/       SQL schema baseline + reference seeds
├── docker-compose.yml
├── .githooks/
├── CHANGELOG.md
└── README.md
```

> **Note on `database/schema.sql`**: the source of truth for the schema is the root
> `database/` folder. `backend/database/` is kept for Laravel migrations/testing
> support, but operational decisions follow the root `database/` state.

## Local Setup

### 1) Infra

```bash
docker compose up -d
```

| Service | URL |
|---|---|
| Postgres | `localhost:5433` |
| Adminer | `http://localhost:8080` |
| MinIO API | `http://localhost:9000` |
| MinIO Console | `http://localhost:9001` |

### 2) Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8000
```

Inspection attachments are stored in MinIO/S3 — `php artisan storage:link` is not
needed for the main flow.

### 3) Frontend

```bash
cd web
npm install
npm run dev
```

- Frontend: `http://localhost:5173`
- API: `http://localhost:8000/api`

### Restore & backup

```powershell
.\scripts\db-restore.ps1 -Mode sql-base     # restore from the root SQL baseline
.\scripts\db-restore.ps1 -Mode migrations   # restore via Laravel migrations/seeds
.\scripts\db-backup.ps1                     # back up before risky changes
```

Details in [`scripts/DB_RESTORE.md`](./scripts/DB_RESTORE.md).

### Syncing the schema after migrations

Whenever a migration changes structure (tables, FKs, indexes, constraints), refresh
the operational snapshot:

```bash
cd backend && php artisan migrate --force && cd ..
docker compose exec -T postgres pg_dump --schema-only --no-owner --no-privileges \
  -U termovault -d termovault > database/schema.sql
```

Do not hand-edit `database/schema.sql`.

## Role-Based Access (backend)

- **Admin** — full read/write access; manages users, companies, sites, and elements.
- **Tecnico** — sees only their own reports; submits new reports for elements within their assigned scope.
- **Supervisor (contractor)** — sees reports from technicians at their own company; cannot review/close reports outside their scope or manage site elements.
- **Supervisor PAE (site owner)** — sees reports for their assigned site (via `usuario_yacimientos`); can review/close reports and manage that site's elements.

All critical restrictions are enforced server-side; the frontend only reflects permissions.

## Key API Endpoints

```
POST   /api/auth/login
GET    /api/auth/me
GET    /api/dashboard/overview
GET    /api/elementos
POST   /api/elementos
PUT    /api/elementos/{id}
DELETE /api/elementos/{id}
POST   /api/inspecciones
GET    /api/inspecciones/{id}
PATCH  /api/inspecciones/{id}/estado
GET    /api/admin/usuarios
POST   /api/admin/usuarios
PATCH  /api/admin/usuarios/{id}
POST   /api/admin/empresas
POST   /api/admin/yacimientos
```

## Frontend UX

- **Role-based dashboard**: relevant KPIs, recent reports table, text/status/date filters, permission-gated quick actions.
- **Report detail view**: operational metadata, findings ("novedades"), files/photos with download, review/close actions gated by role and status.
- **Element management**: a dedicated page, not embedded in the dashboard.

## Pre-merge Checks

```bash
cd web && npm run build
cd backend && php artisan test
```

Minimum functional checks:
- Cognito login with a new user and an active role.
- Technician creates a report.
- Site-owner supervisor reviews and closes it.
- Contractor supervisor only sees their own company's reports.
- Admin sees everything and manages users/organizations.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for the full history of changes.
