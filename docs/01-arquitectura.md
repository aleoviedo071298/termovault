# Arquitectura - TermoVault

Plataforma interna multi-tenant para gestion de informes de termografia en Oil and Gas.

## Vision de alto nivel

TermoVault es un sistema cliente-servidor:

- Web app (React) que consume una API REST (Laravel).
- PostgreSQL como sistema de registro.
- S3/MinIO como storage de archivos pesados (Word, Excel, ZIP de imagenes).
- AWS Cognito como proveedor de identidad. La app no almacena contrasenas.

## Componentes

### Backend (`backend/`)

- Framework: Laravel 12 (PHP 8.3+).
- Auth: middleware JWT Cognito + validacion de rol.
- Storage: filesystem `s3` (MinIO en local, S3 en produccion).
- DB: PostgreSQL.
- Tests: PHPUnit (`tests/Feature`).

Capas:

```txt
routes/api.php
  -> Middleware (cognito.auth, role.claim)
    -> Controllers
      -> Services (scope y reglas)
      -> Models (Eloquent) -> PostgreSQL
      -> Storage Facade -> S3/MinIO
```

### Frontend (`web/`)

- Stack: React + Vite + TypeScript.
- Auth: tokens Cognito en `AuthContext`.
- HTTP: cliente API con `Authorization: Bearer`.
- Rutas: dashboard, elementos, admin usuarios.

### Database (`database/`)

- `schema.sql`: snapshot DDL generado desde la DB real local.
- `seed-*.sql`: catalogos y datos base operativos.
- Regla: para decisiones de datos manda la estructura real de DB y la carpeta `database/` de raiz.

### Infra local (`docker-compose.yml`)

- `postgres` en `localhost:5433`
- `adminer` en `localhost:8080`
- `minio` en `localhost:9000` (consola `9001`)
- `minio-init` para crear bucket inicial

## Infra produccion (decision vigente)

Primera etapa en AWS con costo controlado:

- Una sola instancia EC2 con Docker Compose:
  - backend Laravel
  - frontend build estatico
  - Nginx reverse proxy
  - PostgreSQL
- Adjuntos en S3 privado.
- Identidad con Cognito User Pool.
- DNS con Route53 o proveedor externo (Route53 no es obligatorio).
- TLS con Nginx + Let's Encrypt (ACM opcional para migraciones futuras a ALB/CloudFront).

Ver `docs/07-deploy-aws.md`.

## Flujo de request autenticada

```txt
1) Frontend llama /api/* con Bearer token.
2) Backend valida JWT Cognito (firma, iss, aud, exp).
3) Backend resuelve usuario local y scope por rol/empresa/yacimiento.
4) Controller ejecuta query con restricciones de scope.
5) Respuesta JSON.
```

## ADRs relacionadas

- ADR-001: multi-tenant por `empresa_id`.
- ADR-002: auth con Cognito (sin password local).
- ADR-003: modelo unificado de elementos.
- ADR-004: archivos en S3/MinIO.
- ADR-005: `database/` raiz como referencia operativa.
- ADR-006: campos de auditoria en inspecciones.
