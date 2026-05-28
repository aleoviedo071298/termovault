# Roadmap - TermoVault

Estado al **2026-05-28**. Las tareas se agrupan por horizonte y reflejan los hallazgos de auditoria del repo.

## Done

- Auth Cognito JWT validada con JWKS + cache.
- Provision local de usuarios en primer login (`LocalUserProvisioner`).
- Scope por rol + yacimiento en `AccessScopeResolver`.
- Flujos de roles validados: admin, supervisor PAE, supervisor contratista y tecnico.
- Flujo de inspeccion `enviada -> revisada -> cerrada` con audit fields.
- Cierre automatico de novedades al cerrar inspeccion.
- Storage a S3/MinIO con keys legibles.
- Descargas por API autorizada, sin exponer la URL real del bucket.
- Importacion bulk de elementos del yacimiento PAE.
- Cleanup de tablas/columnas sin uso en DB actual.
- Indices compuestos para queries de dashboard.
- CHECK constraints en estados de `inspecciones`, `novedades` y bucket de `archivos`.
- `COGNITO_AUTH_REQUIRED=true` por defecto.
- Routing frontend migrado a `react-router-dom`.
- Scaffold publico de Laravel removido.
- `database/schema.sql` regenerado desde la DB local actual.

## Now - Sprint Actual

### Seguridad

- [x] **Reactivar Cognito**: `COGNITO_AUTH_REQUIRED=true`.
- [ ] **Rotar `COGNITO_APP_CLIENT_SECRET`** en AWS Console antes de produccion.
- [x] **Throttle en `/api/auth/login`**: `throttle:10,1`.
- [x] **Validar MIME/extension** en uploads:
  - `reporte`: `doc`, `docx`, `xls`, `xlsx`, max 10 MB.
  - `imagenes`: `zip`, max 50 MB.
- [x] **CORS explicito** desde `backend/config/cors.php`.
- [x] **Sacar claims completos** de `/api/auth/me`.
- [x] **Eliminar magic string `local` para bucket**.
- [x] **Rol local domina sobre Cognito stale**.
- [x] **Provisioning sin fallback a primera empresa**.
- [ ] **Verificar bucket publico** en MinIO/S3: quitar `mc anonymous set download` si ya no se usa para el flujo principal.

### Infra / DB

- [x] **Schema consolidado**: `database/schema.sql` es snapshot generado; DB real + migraciones aplicadas son la fuente de verdad.
- [x] **Drop tabla `users`**: modelo `User.php`, `UserFactory.php` y tabla `users` no existen en el arbol/DB actual.
- [x] **`password_hash` removido**: no existe en la migracion base actual, en `database/schema.sql` ni en la DB local actual.

## Next - 2 a 4 Semanas

### Backend

- [ ] **Soft-delete de elementos**: reemplazar hard-delete por `activo=false` o bloquear si hay historial.
- [ ] **Cleanup de archivos huerfanos**: comando que borre de S3/MinIO blobs sin fila `archivos`.
- [ ] **Notificacion de revision**: email al tecnico cuando un informe pasa a `revisada` o `cerrada`.
- [ ] **Filtrado por criticidad** en `GET /api/dashboard/overview`.
- [ ] **Endpoints de stats por elemento** para evitar cargar todo el historial.

### Frontend

- [ ] **Tipos compartidos**: completar `web/src/types/{inspeccion,usuario,api}.ts`.
- [ ] **Borrar parches legacy de normalizacion** cuando backend y datos queden totalmente limpios.
- [ ] **Pantalla `MisNovedades`** para tecnico.
- [ ] **Drag & drop** del Word + ZIP en `InspectionModal`.

### DX / Tooling

- [ ] **CI completo**: backend tests + frontend build.
- [ ] **Pre-commit hook** con formatter/linter.
- [ ] **`.env.example`** sincronizado con claves reales en uso.
- [ ] **README de `backend/`** propio.

## Later

- [ ] Mobile offline con sync.
- [ ] PDF server-side de informes.
- [ ] Vista de tendencias.
- [ ] Busqueda full-text.
- [ ] Importador generico de Words legacy.
- [ ] Terraform para RDS, S3, Cognito, frontend y backend.
- [ ] Pipeline CD.
- [ ] Observabilidad y backups.
- [ ] Row-level security como defensa extra.
- [ ] MFA obligatorio para admin/supervisor.
- [ ] Auditoria detallada en tabla nueva `auditoria_eventos`.

## Convenciones

- **Now**: sprint actual.
- **Next**: 2 a 4 semanas.
- **Later**: identificado pero no priorizado.

Cuando se cierra una tarea, se mueve a `Done` con fecha aproximada en el changelog del repo.
