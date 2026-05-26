# TermoVault

Plataforma interna multi-tenant para gestión de informes termográficos (Oil & Gas / energía).

## Estado actual (2026-05-26)

- Backend Laravel 12 funcional con JWT Cognito.
- Frontend React + Vite funcional con dashboard por rol.
- Roles activos: `admin`, `supervisor`, `tecnico`.
- Flujo operativo implementado:
  - Técnico carga informe (`enviada`).
  - Supervisor/Admin revisa (`revisada`).
  - Supervisor/Admin cierra (`cerrada`).

## Stack real del repo

- Backend: Laravel 12 + PostgreSQL 16
- Frontend: React 19 + Vite 7 + TypeScript
- Infra local: Docker Compose (Postgres, MinIO, Adminer)
- Auth: AWS Cognito (JWT)

## Estructura

```txt
termovault/
├── backend/                  # API Laravel
├── web/                      # Frontend React/Vite
├── database/                 # schema.sql + seeds
├── docker-compose.yml
├── .githooks/
└── README.md
```

## Setup local rápido

### 1) Levantar servicios

```bash
docker compose up -d
```

Servicios:
- Postgres: `localhost:5433` (interno contenedor: 5432)
- Adminer: `http://localhost:8080`
- MinIO API: `http://localhost:9000`
- MinIO Console: `http://localhost:9001`

### 2) Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

### 3) Frontend

```bash
cd web
npm install
npm run dev
```

Frontend: `http://localhost:5173`  
API base: `http://localhost:8000/api`

## Modelo de datos (núcleo)

Tablas principales:
- `roles`
- `usuarios`
- `empresas`
- `yacimientos`
- `usuario_yacimientos`
- `elementos`
- `inspecciones`
- `archivos`
- `novedades`

Relación clave:
- Una `inspeccion` pertenece a un `elemento`.
- Un `elemento` pertenece a un `yacimiento`.
- Un `usuario` técnico crea inspecciones.
- Los archivos de informe/fotos viven en `archivos` asociados a `inspecciones`.

## Seguridad y alcance por rol

Se aplica en backend (no depende del frontend):

- **Admin**
  - Ve y edita todo.
- **Técnico**
  - Ve solo sus informes.
  - Puede cargar nuevas inspecciones.
- **Supervisor contratista**
  - Ve informes de técnicos de su empresa.
  - Revisa/cierra dentro de su alcance.
  - No administra elementos.
- **Supervisor PAE**
  - Ve por yacimientos asignados (`usuario_yacimientos`, ej. `YAC-PAE`).
  - Puede administrar elementos solo dentro de esos yacimientos.

## Endpoints importantes

- `POST /api/auth/login`
- `GET /api/auth/me`
- `GET /api/dashboard/overview`
- `GET /api/elementos`
- `GET /api/elementos/{id}`
- `POST /api/elementos` *(admin/supervisor PAE con alcance)*
- `PUT /api/elementos/{id}` *(admin/supervisor PAE con alcance)*
- `DELETE /api/elementos/{id}` *(admin/supervisor PAE con alcance)*
- `POST /api/inspecciones`
- `GET /api/inspecciones/{id}`
- `PATCH /api/inspecciones/{id}/estado` *(admin/supervisor)*

Estados de inspección en uso:
- `enviada`
- `revisada`
- `cerrada`

## UX implementada

### Dashboard por rol
- Cards de métricas.
- Tabla “Últimos informes”.
- Filtros por texto/estado/rango de fechas.
- Acción principal visible: “Registrar nueva termografía”.

### Detalle de informe
- Fecha, técnico, empresa, yacimiento, subestación/elemento, estado.
- Resumen y observaciones.
- Archivos/fotos con descarga.
- Botones de revisión/cierre solo si corresponde por estado y rol.

### Gestión de elementos
- Pantalla separada: `/elementos/gestion`
- No queda enterrada en el homepage.
- Admin y Supervisor PAE pueden gestionar.

## Archivos clave agregados/actualizados recientemente

Backend:
- `backend/app/Services/Auth/AccessScopeResolver.php`
- `backend/app/Http/Controllers/DashboardController.php`
- `backend/app/Http/Controllers/InspeccionController.php`
- `backend/app/Http/Controllers/ElementoController.php`
- `backend/app/Http/Middleware/EnsureCognitoJwt.php`
- `backend/app/Http/Controllers/CatalogController.php`
- `backend/routes/api.php`

Frontend:
- `web/src/pages/Dashboard.tsx`
- `web/src/pages/ElementosGestion.tsx`
- `web/src/components/InspectionDetailModal.tsx`
- `web/src/api/dashboard.ts`
- `web/src/api/inspecciones.ts`
- `web/src/api/client.ts`
- `web/src/App.tsx`

## Datos de prueba actuales

Se limpiaron inspecciones históricas y se cargaron 3 informes demo `enviada` para:
- `marijo006@gmail.com`

Esto permite testear flujo técnico/supervisor/admin de punta a punta.

## Guía para continuar con otra IA

Si retomás con otro agente, pasale:
1. Este `README.md`.
2. Rama actual + `git status`.
3. Objetivo puntual (ej. “mejorar módulo de revisión”).

Prompt sugerido corto:

```txt
Leé README.md completo y sincronizate con el estado real del repo.
No inventes estructura ni permisos. Mantener seguridad por rol en backend.
Primero analizá, luego implementá y validá build/test.
```

## Checklist antes de merge

- `php -l` en controladores/middleware tocados.
- `npm run build` en `web/`.
- Probar flujo:
  - Técnico crea informe.
  - Supervisor revisa.
  - Supervisor/Admin cierra.
  - Descargas de archivos desde detalle.

