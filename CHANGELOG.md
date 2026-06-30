# Changelog

Todas las novedades relevantes de este proyecto se documentan aca.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y este proyecto usa [Semantic Versioning](https://semver.org/lang/es/).

## [Unreleased]

### Added
- Optimización de rendimiento y caching (Etapa 6): índices clave en PostgreSQL (`idx_usuarios_rol`, `idx_elementos_criticidad`, `idx_inspecciones_estado`, `idx_inspecciones_revisada_por`, `idx_inspecciones_cerrada_por`, `idx_archivos_subido_por`); eager loading selectivo (`with`) para evitar N+1; cache global de catálogos estáticos (1h, invalidación inmediata); cache del dashboard por versión (5 min, invalidación atómica O(1)). Validado con `backend/tests/Feature/PerformanceTest.php`.
- Hardening de seguridad del frontend (Etapa 7): Content Security Policy estricta (meta-tags + headers Laravel); tokens JWT en memoria RAM (`web/src/auth/TokenManager.ts`, sin `localStorage`, logout automático al refrescar); sanitización XSS con DOMPurify (`<SanitizedInput>`, `<SafeHtmlContent>`); CORS manual con whitelist de origen y `OPTIONS` 204; tests de seguridad con Vitest/JSDOM en `web/src/__tests__/security/`.
- Carga de múltiples archivos térmicos por inspección (`.is2` y/o `.zip`), con drag & drop y lista de archivos seleccionados (nombre, tamaño y quitar).
- Toggle "Incluir informe formal" en la carga de inspección: el informe (PDF / Word / Excel) ahora es opcional; el caso de campo (seccionadores, transformadores) puede guardarse solo con termografías y hallazgos.
- Nombre de descarga normalizado y legible, calculado desde la DB: `{id}_{elemento}_{dd-mm-aaaa}_{tipo}.{ext}` (aplica también a archivos ya cargados, sin renombrar nada en MinIO).
- Rediseño UX/UI premium industrial para dashboard, gestion de elementos, admin usuarios y login.
- Componentes reutilizables de dashboard: `DashboardHero`, `ActionToolbar`, `RoleBadge`, `KPIGrid`, `StatsCard`, `FilterBar`, `ReportTable`.
- Filtro por criticidad en dashboard.
- Filtro por tipo en gestion de elementos.
- URLs de archivo generadas por API para descargas desde MinIO/S3.
- Soporte S3 Laravel con `league/flysystem-aws-s3-v3`.
- Estructura inicial del proyecto (backend Laravel, web React, mobile Flutter, infra Terraform).
- Laravel 12 backend with health endpoint (`/api/health`).
- Eloquent models with multi-tenant relationships and `forEmpresa` query scopes.
- Laravel migrations and seeders that replicate the PostgreSQL schema and 65 seeded elements.
- `GET /api/elementos` endpoint listing seeded elements.
- React/Vite frontend with Elementos list view backed by the API.
- Modelo de datos PostgreSQL v2 con multi-tenant por `empresa_id`.
- Seeds iniciales con 65 elementos extraidos de los Words actuales del cliente.
- Catalogos: tipos de elemento, niveles de tension, criticidades y roles.
- Documentacion: arquitectura, modelo de datos, decisiones, roadmap.
- Configuracion de Git: `.gitattributes`, `.editorconfig`, `.gitignore`.
- Plantillas de GitHub: PR, issues, CODEOWNERS, Dependabot.
- CI basico: validacion de schema SQL contra Postgres en pipeline.
- Hooks locales de Git: `pre-commit`, `commit-msg`, `pre-push`.
- Entorno local con `docker-compose`: Postgres 16 + MinIO + Adminer.
- Script de init que aplica schema + seeds automaticamente al primer arranque.
- `Makefile` con comandos comunes (`make up`, `make psql`, `make reset`, etc.).

### Changed
- `POST /api/inspecciones` acepta `termografias[]` (múltiples archivos) y `reporte` opcional; las termografías `.is2` se validan por extensión (formato propietario sin MIME confiable) y el informe admite PDF/Word/Excel.
- Detalle de inspección e historial de elemento ahora separan "Informe formal" de "Archivos térmicos", mostrando "Sin informe formal" cuando no hay informe (compatible con archivos viejos).
- Nuevos `tipo` de archivo reutilizando la tabla `archivos` existente: `termografia_is2`, `termografia_zip`, `informe_pdf` (sin migración ni cambios destructivos).
- Copy de títulos simplificado en dashboard, elementos, administración y login (tono más sobrio, menos grandilocuente).
- `SecurityHeaders` expone `Access-Control-Expose-Headers: Content-Disposition` para que el frontend pueda leer el nombre de archivo en descargas cross-origin.
- Inspecciones ahora guardan adjuntos en MinIO/S3 en vez de `storage/app/public`.
- Keys de archivos migradas a formato legible: `inspecciones/{inspeccion_id}/{reports|images}/{archivo_id}-{nombre-original}`.
- Gestion de elementos ya no muestra criticidad en tabla principal; queda como dato extra en detalle/formulario.
- Header del dashboard usa identidad y alcance reales del usuario autenticado, no datos derivados del primer informe.
- Login separa el primer ingreso/cambio de contraseña en un panel dedicado.

### Fixed
- Mensajes de error de Cognito normalizados al español.
- Caso Axel Elgueta/PAE: `YAC-PAE` asociado a empresa `PAE` y no `PECOM`.
- Flicker de rol en dashboard al volver desde gestion de elementos.

### Removed
- Badge "Portal Técnico" del login; el texto del panel quedó centrado.
- Descargas simuladas del panel de detalle de elementos.

### Security
- Auditoría de seguridad defensiva completa documentada en `SECURITY_AUDIT_REPORT.md` (0 críticos; hallazgos altos/medios/bajos con plan de remediación).
- M3: los intentos de descarga fuera de scope ahora se registran (`auth.scope.violation` / `archivo.download.denied.out_of_scope`) en vez de devolver 404 en silencio.
- M4: scope del técnico ahora es fail-closed — un técnico sin yacimiento asignado no puede leer elementos ni crear inspecciones hasta que un admin lo asigne (antes tenía acceso a nivel empresa). Lectura y escritura quedaron alineadas.

---

## Convenciones

- `[Unreleased]` agrupa los cambios pendientes de release.
- Al hacer release, mover los cambios a una nueva seccion `[X.Y.Z] - YYYY-MM-DD`.
- Las subcategorias son: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.

## Ejemplo de release futuro

```md
## [0.1.0] - 2026-08-15

### Added
- Endpoint POST /api/inspecciones para crear inspecciones.
- Login con AWS Cognito en frontend web.

### Fixed
- Validacion de tamaño maximo en upload de ZIP.
```
