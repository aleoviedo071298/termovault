# TermoVault

Plataforma interna multi-tenant para gestion de informes de termografia (Oil and Gas).

## Estado actual (2026-05-27)

- Backend Laravel 12 con autenticacion Cognito JWT.
- Frontend React + Vite con dashboards por rol.
- Roles activos: `admin`, `supervisor`, `tecnico`.
- Flujo operativo:
  1. Tecnico crea informe (`enviada`).
  2. Supervisor PAE o Admin revisa (`revisada`).
  3. Supervisor PAE o Admin cierra (`cerrada`).
- Trazabilidad de revision/cierre:
  - `revisada_por` + `fecha_revision`
  - `cerrada_por` + `fecha_cierre`
- Bloqueo de acceso por usuario inactivo (`usuarios.activo = false`).

## Stack

- Backend: Laravel 12
- Frontend: React 19 + Vite 7 + TypeScript
- DB: PostgreSQL 16
- Infra local: Docker Compose (Postgres, MinIO, Adminer)
- Auth: AWS Cognito

## Estructura del repo

```txt
termovault/
|-- backend/                 # API Laravel
|-- web/                     # Frontend React/Vite
|-- database/                # SQL base + seeds de referencia
|-- docker-compose.yml
|-- .githooks/
|-- README.md
`-- NEXT_SESSION.md
```

## Setup rapido

### 1) Infra

```bash
docker compose up -d
```

Servicios:
- Postgres: `localhost:5433`
- Adminer: `http://localhost:8080`
- MinIO API: `http://localhost:9000`
- MinIO Console: `http://localhost:9001`

**Note on schema.sql:** The `database/schema.sql` is a generated snapshot used for fast Docker initialization. The canonical source of truth is Laravel migrations (`backend/database/migrations/`). See `scripts/regenerate-schema-sql.md` for details on keeping them in sync.

### 2) Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8000
```

Los adjuntos de inspecciones se guardan en MinIO/S3. No hace falta `php artisan storage:link` para el flujo principal.

### 3) Frontend

```bash
cd web
npm install
npm run dev
```

Frontend: `http://localhost:5173`  
API: `http://localhost:8000/api`

## Reglas de seguridad por rol (backend)

- **Admin**
  - Ve y edita todo.
  - Gestiona usuarios, empresas, yacimientos, elementos.
- **Tecnico**
  - Ve solo sus informes.
  - Carga nuevos informes para elementos habilitados en su alcance.
- **Supervisor contratista**
  - Ve informes de tecnicos de su empresa.
  - No puede cerrar/revisar informes fuera de su alcance.
  - No administra elementos de yacimiento.
- **Supervisor PAE (dueno de yacimiento)**
  - Ve informes del yacimiento asignado (por `usuario_yacimientos`).
  - Puede revisar/cerrar.
  - Puede administrar elementos del yacimiento asignado.

Todas las restricciones criticas se validan en backend. El frontend solo refleja permisos.

## Endpoints principales

- `POST /api/auth/login`
- `GET /api/auth/me`
- `GET /api/dashboard/overview`
- `GET /api/elementos`
- `POST /api/elementos`
- `PUT /api/elementos/{id}`
- `DELETE /api/elementos/{id}`
- `POST /api/inspecciones`
- `GET /api/inspecciones/{id}`
- `PATCH /api/inspecciones/{id}/estado`
- `GET /api/admin/usuarios`
- `POST /api/admin/usuarios`
- `PATCH /api/admin/usuarios/{id}`
- `POST /api/admin/empresas`
- `POST /api/admin/yacimientos`

## Frontend UX actual

- Dashboard por rol con:
  - KPIs relevantes
  - tabla de ultimos informes
  - filtros por texto/estado/fechas
  - acciones rapidas por permisos
- Vista de detalle de informe:
  - metadata operativa
  - hallazgos (novedades)
  - archivos y fotos con descarga
  - acciones de revision/cierre segun rol y estado
- Gestion de elementos en pagina separada (no embebida en dashboard).

## Archivos clave tocados en esta etapa

Backend:
- `backend/app/Http/Controllers/AuthController.php`
- `backend/app/Http/Controllers/CatalogController.php`
- `backend/app/Http/Controllers/DashboardController.php`
- `backend/app/Http/Controllers/InspeccionController.php`
- `backend/app/Http/Controllers/AdminUserController.php`
- `backend/app/Http/Controllers/AdminOrganizationController.php`
- `backend/app/Http/Middleware/EnsureCognitoJwt.php`
- `backend/app/Http/Middleware/EnsureRoleFromClaims.php`
- `backend/app/Services/Auth/AccessScopeResolver.php`
- `backend/routes/api.php`

Frontend:
- `web/src/App.tsx`
- `web/src/pages/Dashboard.tsx`
- `web/src/pages/AdminUsuariosPage.tsx`
- `web/src/pages/Login.tsx`
- `web/src/components/ElementModal.tsx`
- `web/src/components/InspectionDetailModal.tsx`
- `web/src/api/dashboard.ts`
- `web/src/api/adminUsuarios.ts`
- `web/src/auth/AuthContext.tsx`
- `web/src/auth/cognito.ts`
- `web/src/index.css`

## Validaciones recomendadas antes de merge/publish

```bash
cd web && npm run build
cd backend && php artisan test
```

Pruebas funcionales minimas:
- Login Cognito con usuario nuevo + rol activo.
- Tecnico crea informe.
- Supervisor PAE revisa y cierra.
- Supervisor contratista solo ve su empresa.
- Admin ve todo y gestiona usuarios/organizacion.

## Continuidad con otra IA

Usar `NEXT_SESSION.md` como prompt base.

## Limpieza y hardening (2026-05-27)

- Se eliminaron migraciones SQL legacy vacias en `database/migrations/`.
- Se movio helper sensible de Cognito a `backend/scripts/internal/auth_login.php` (fuera de `public/`).
- Se eliminaron tablas legacy no usadas:
  - `comentarios`
  - `historial_cambios`
  - `sesiones`
- Se eliminaron columnas no usadas:
  - `inspecciones`: `temperatura_ambiente`, `humedad_relativa`, `carga_pct`
  - `empresas`: `cuit`, `logo_url`
  - `yacimientos`: `zona`, `descripcion`
  - `usuarios`: `legajo`, `telefono`, `ultimo_login`
  - `novedades`: `fecha_resolucion`, `resuelta_en_inspeccion_id`
- Se unifico la autoprovision de usuario Cognito en un unico servicio:
  - `App\Services\Auth\LocalUserProvisioner`
- Se agregaron indices y constraints de integridad para mejorar performance/consistencia.
- Se realizo backfill de auditoria historica en `inspecciones`:
  - `created_by`, `updated_by`, `cerrada_por`, `fecha_cierre`
- Se guardaron respaldos de auditoria en:
  - `backups/termovault_2026-05-27.dump`
  - `backups/termovault_schema_2026-05-27.sql`
  - `backups/audit/*`
