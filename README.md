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

**Note on schema.sql:** The real DB reference in this project is the root `database/` folder (especially `database/schema.sql` + `seed-*.sql`). `backend/database/` is kept for Laravel runtime/testing support and migration history, but operational decisions should follow the root `database/` state.

## Restore and backup (quick)

- Fast operational restore from root SQL baseline:
  - `.\scripts\db-restore.ps1 -Mode sql-base`
- Restore via Laravel migrations/seeds:
  - `.\scripts\db-restore.ps1 -Mode migrations`
- Create backup before risky changes:
  - `.\scripts\db-backup.ps1`

Detailed notes: `scripts/DB_RESTORE.md`.

## Sync de schema despues de migraciones

Cuando una migracion cambia estructura (tablas, FK, indices o constraints), sincronizar el snapshot operativo:

```powershell
cd backend
php artisan migrate --force
cd ..
docker compose exec -T postgres pg_dump --schema-only --no-owner --no-privileges -U termovault -d termovault |
  Set-Content -Encoding utf8 database\schema.sql
```

No editar `database/schema.sql` a mano.

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

## Auditoria de descargas de archivos

Las descargas ahora quedan registradas en `auditoria_descargas_archivos` con:

- `archivo_id`
- `usuario_id`
- `ip_address`
- `user_agent`
- `descargado_en`

Esto mejora trazabilidad operativa/compliance sin cambiar el flujo funcional de descarga.

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
- Se eliminaron tablas legacy no usadas (`comentarios`, `historial_cambios`, `sesiones`).
- Se eliminaron columnas no usadas de `inspecciones`, `empresas`, `yacimientos`, `usuarios` y `novedades`.
- Se unifico la autoprovision de usuario Cognito en `App\Services\Auth\LocalUserProvisioner`.
- Se guardaron respaldos de auditoria en `backups/`.

---

## Optimización de Rendimiento y Caching (Etapa 6 - 2026-05-29)

Se implementaron estrategias avanzadas para garantizar tiempos de respuesta rápidos y menor carga de base de datos:
- **Indexación de Base de Datos**: Creación e inserción de índices clave en PostgreSQL (`idx_usuarios_rol`, `idx_elementos_criticidad`, `idx_inspecciones_estado`, `idx_inspecciones_revisada_por`, `idx_inspecciones_cerrada_por`, `idx_archivos_subido_por`) para optimizar accesos frecuentes.
- **Evitado de N+1 Queries**: Auditoría y carga ansiosa selectiva (`with`) de relaciones complejas en listados de elementos y dashboard.
- **Result Caching (Catálogos)**: Los catálogos estáticos de criticidades, niveles de tensión y tipos se cachean globalmente por 1 hora con invalidación inmediata al editar.
- **Cache de Dashboard por Versión**: Se cachea la agregación JSON del dashboard por 5 minutos usando un timestamp de alta resolución en RAM. La clave se invalida de forma atómica en O(1) ante cualquier guardado o borrado en inspecciones, elementos o novedades.
- **Pruebas de Performance**: Validadas mediante pruebas integradas en [PerformanceTest.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/tests/Feature/PerformanceTest.php).

---

## Hardening de Seguridad del Frontend (Etapa 7 - 2026-05-29)

Se endureció la seguridad de la interfaz frente a ataques de inyección y Clickjacking:
- **Content Security Policy (CSP)**: Implementación de cabeceras estrictas de CSP tanto en meta-tags de index.html como desde el backend Laravel, permitiendo orígenes seguros de API/Cognito y bloqueando inyección de scripts/marcos no confiables.
- **Manejo de Tokens en Memoria RAM**: Se eliminó la persistencia de tokens JWT en `localStorage`. Ahora se gestionan de forma segura en memoria mediante [TokenManager.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/auth/TokenManager.ts), provocando logout automático al cerrar/refrescar la pestaña.
- **Sanitización de Contenido (XSS)**: Integración de **DOMPurify** en utilidades de frontend para sanitizar marcado HTML dinámico y sanear entradas mediante los componentes reutilizables `<SanitizedInput>` y `<SafeHtmlContent>`.
- **CORS de Servidor**: Desactivación del CORS predeterminado y manejo robusto y manual de whitelistings de origen y respuestas preflight `OPTIONS` con código 204.
- **Pruebas Unitarias y de Integración**: Pruebas de seguridad del frontend implementadas con Vitest y JSDOM en `web/src/__tests__/security/`.

