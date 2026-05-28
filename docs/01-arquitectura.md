# Arquitectura — TermoVault

> Plataforma interna multi-tenant para gestión de informes de termografía en Oil & Gas.

## Visión de alto nivel

TermoVault es un sistema cliente-servidor:

- **Web app (React)** que consume una **API REST (Laravel)**.
- **PostgreSQL** como sistema de registro.
- **S3/MinIO** como storage de archivos pesados (Word, Excel, ZIP de imágenes).
- **AWS Cognito** como proveedor de identidad. La app **no almacena contraseñas**.

```
          ┌──────────────┐         JWT          ┌──────────────┐
          │   Browser    │ ───── Bearer ──────▶ │ Backend API  │
          │  (React app) │                      │  (Laravel)   │
          └──────┬───────┘                      └──────┬───────┘
                 │                                     │
                 │ Login (email+password)              │ Verifica JWT
                 ▼                                     │ vía JWKS
          ┌──────────────┐ ◀──── id_token ──────┐      ▼
          │ AWS Cognito  │                      │  ┌─────────┐
          │  user pool   │                      │  │   DB    │
          └──────────────┘                      │  │ Postgres│
                                                │  └─────────┘
                                                │
                                                │  ┌─────────┐
                                                └─▶│  S3 /   │
                                                   │  MinIO  │
                                                   └─────────┘
```

## Componentes

### Backend (`backend/`)

- **Framework**: Laravel 12 (PHP 8.3+).
- **Auth**: middleware `EnsureCognitoJwt` valida el JWT contra JWKS de Cognito. `EnsureRoleFromClaims` valida que el rol del token (o del usuario local como fallback) esté entre los permitidos por la ruta.
- **Storage**: filesystem `s3` apuntando a MinIO en local, a S3 real en producción.
- **DB**: PostgreSQL 16, conexión `pgsql`.
- **Tests**: PHPUnit; suite `tests/Feature/` cubre middlewares + endpoints principales.

Capas:

```
routes/api.php
   └─▶ Middleware (cognito.auth, role.claim)
        └─▶ Controllers (Http/Controllers/*)
             ├─▶ Services (Services/Auth/AccessScopeResolver, etc.)
             ├─▶ Models (Eloquent) ──▶ PostgreSQL
             └─▶ Storage Facade   ──▶ S3 / MinIO
```

### Frontend (`web/`)

- **Stack**: React 19 + Vite 7 + TypeScript.
- **Estado de auth**: `AuthContext` guarda `access_token` e `id_token` en `localStorage`.
- **HTTP**: cliente fetch envuelto en `api/client.ts` que inyecta el `Authorization: Bearer …` automáticamente.
- **Ruteo**: manejo manual con `history.pushState` y `popstate` (pendiente migrar a `react-router-dom`).
- **Vistas activas**:
  - `Login` — login Cognito + challenge de NEW_PASSWORD_REQUIRED.
  - `Dashboard` — KPIs y tabla de informes según rol.
  - `ElementosGestion` — CRUD de elementos (solo admin / supervisor PAE).
  - `AdminUsuariosPage` — gestión de usuarios, empresas y yacimientos (solo admin).

### Database (`database/`)

- `schema.sql` — DDL de referencia para arranque rápido del container Postgres.
- `seed-*.sql` — datos mínimos: roles, tipos, niveles de tensión, criticidades, empresa PAE y usuarios pivote.
- Las **migraciones de Laravel** (`backend/database/migrations/`) son la fuente de verdad. `schema.sql` se regenera desde ahí.

### Infra local (`docker-compose.yml`)

Tres servicios + un sidecar:

- `postgres` — Postgres 16 en `localhost:5433`.
- `adminer` — UI DB en `localhost:8080`.
- `minio` — S3 compatible en `localhost:9000` (consola en `9001`).
- `minio-init` — sidecar que crea el bucket inicial y se apaga.

### Infra producción

Pensada para AWS:

- Backend: EC2 o ECS detrás de ALB.
- Frontend: build estático servido por S3 + CloudFront.
- DB: RDS Postgres.
- Storage: S3 con CloudFront opcional.
- Identidad: Cognito User Pool real (la app ya está integrada).

Ver `docs/07-deploy-aws.md`.

## Flujo de una request autenticada

```
1. Frontend hace fetch a /api/elementos con Authorization: Bearer <access_token>
2. Middleware EnsureCognitoJwt:
     a) Si COGNITO_AUTH_REQUIRED=false y no hay token → continúa (modo dev)
     b) Si hay token:
        - Verifica firma RSA contra JWKS cacheado (6h)
        - Verifica iss, aud, exp, iat
        - Busca/provisiona Usuario local con LocalUserProvisioner
        - Bloquea si usuario está inactivo
        - Setea en request.attributes:
            auth.claims, auth.empresa_id, auth.user_id
3. Middleware EnsureRoleFromClaims:role1,role2:
     - Extrae roles de cognito:groups o custom:role
     - Fallback: consulta tabla usuarios + roles si no vinieron en el token
     - Valida que el usuario tenga al menos uno de los roles requeridos
4. Controller resuelve el AccessScopeResolver para limitar la query por:
     - empresa_id del usuario
     - yacimientos asignados en usuario_yacimientos
     - flags is_admin / is_supervisor / is_pae_supervisor / is_tecnico
5. Eloquent ejecuta la query con el scope aplicado
6. Response JSON
```

## Convenciones de código

### Backend

- Controllers delgados; lógica de scope va en `Services/Auth/AccessScopeResolver`.
- Models con `scope*` para filtros reutilizables (`forEmpresa`).
- Migraciones reversibles cuando es razonable (los `dropColumn` lo son; los backfill no).
- Validación en cada `store/update` con `$request->validate(...)`.

### Frontend

- Tipos compartidos en `types/` (cuando estén implementados; hoy hay placeholders).
- Llamadas API agrupadas en `api/*.ts` por dominio (`elementos`, `inspecciones`, `dashboard`, `adminUsuarios`).
- Componentes "tontos" en `components/dashboard/`; vistas con estado en `pages/`.

## Decisiones arquitectónicas relevantes

Ver `docs/04-decisiones.md` para el detalle de cada ADR:

- **ADR-001** — Multi-tenant por columna `empresa_id`.
- **ADR-002** — Auth con Cognito como SSO, no contraseñas locales.
- **ADR-003** — Una sola tabla `elementos` con tipo + función.
- **ADR-004** — Archivos en S3/MinIO, no en DB.
- **ADR-005** — Migraciones Laravel como fuente de verdad del schema.
- **ADR-006** — Audit fields en `inspecciones` (created_by/updated_by/cerrada_por/fecha_cierre).
