# Changelog

Todas las novedades relevantes de este proyecto se documentan acá.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y este proyecto usa [Semantic Versioning](https://semver.org/lang/es/).

## [Unreleased]

### Added
- Estructura inicial del proyecto (backend Laravel, web React, mobile Flutter, infra Terraform).
- Laravel 12 backend with health endpoint (`/api/health`).
- Eloquent models with multi-tenant relationships and `forEmpresa` query scopes.
- Laravel migrations and seeders that replicate the PostgreSQL schema and 65 seeded elements.
- `GET /api/elementos` endpoint listing seeded elements.
- React/Vite frontend with Elementos list view backed by the API.
- Modelo de datos PostgreSQL v2 con multi-tenant por `empresa_id`.
- Seeds iniciales con 65 elementos extraídos de los Words actuales del cliente.
- Catálogos: tipos de elemento (subestación, ETR, banco capacitores, seccionador, reconectador), niveles de tensión (6.6/13.2/33/132 kV), criticidades, roles.
- Documentación: arquitectura, modelo de datos, decisiones, roadmap.
- Configuración de Git: `.gitattributes`, `.editorconfig`, `.gitignore`.
- Plantillas de GitHub: PR, issues, CODEOWNERS, Dependabot.
- CI básico: validación de schema SQL contra Postgres en pipeline.
- Hooks locales de Git: `pre-commit`, `commit-msg`, `pre-push` (suple branch protection en plan Free).
- Entorno local con `docker-compose`: Postgres 16 + MinIO + Adminer.
- Script de init que aplica schema + seeds automáticamente al primer arranque.
- `Makefile` con comandos comunes (`make up`, `make psql`, `make reset`, etc.).

### Changed
— (nada todavía)

### Removed
— (nada todavía)

---

## Convenciones

- `[Unreleased]` agrupa los cambios pendientes de release.
- Al hacer release, mover los cambios a una nueva sección `[X.Y.Z] - YYYY-MM-DD`.
- Las subcategorías son: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.

## Ejemplo de release futuro

```
## [0.1.0] - 2026-08-15

### Added
- Endpoint POST /api/inspecciones para crear inspecciones.
- Login con AWS Cognito en frontend web.

### Fixed
- Validación de tamaño máximo en upload de ZIP.
```
