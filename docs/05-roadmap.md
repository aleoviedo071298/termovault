# Roadmap — TermoVault

Estado al **2026-05-28**. Las tareas se agrupan por horizonte. Las prioridades reflejan los hallazgos de la auditoría del repo.

---

## Done (último mes)

- Auth Cognito JWT validada con JWKS + cache.
- Provisión automática de usuarios locales en primer login (`LocalUserProvisioner`).
- Scope por rol + yacimiento en `AccessScopeResolver`.
- Tres flujos de roles validados end-to-end: admin, supervisor PAE, supervisor contratista, técnico.
- Flujo de inspección `enviada → revisada → cerrada` con audit fields.
- Cierre automático de novedades al cerrar la inspección.
- Storage a S3/MinIO con keys legibles (`inspecciones/{id}/reports/{archivo}` y `…/images/{archivo}`).
- Importación bulk de 756 elementos del yacimiento PAE (reconectadores + seccionadores + bancos de capacitores + subestaciones).
- Cleanup de tablas y columnas no usadas (`comentarios`, `historial_cambios`, `sesiones`; campos `cuit`, `logo_url`, `legajo`, `telefono`, `ultimo_login`, `zona`, `descripcion`, métricas ambientales).
- Índices compuestos para queries de dashboard.
- CHECK constraints en estados de `inspecciones` y `novedades`.
- **Re-activar Cognito** (2026-05-28): `COGNITO_AUTH_REQUIRED=true` en backend/.env. Middleware valida JWT en endpoints protegidos; /health y /auth/login quedan públicos.

---

## Now — sprint actual (crítico, semana 1)

> Bloqueante para considerar el sistema "production-ready".

### Seguridad

- [x] **Re-activar Cognito**: `COGNITO_AUTH_REQUIRED=true` (2026-05-28). Middleware enforces JWT en protected endpoints.
- [ ] **Rotar `COGNITO_APP_CLIENT_SECRET`** en AWS Console; reemplazar en `.env` y mover a Secrets Manager. (Pospuesto: costo innecesario en dev)
- [x] **Throttle en `/api/auth/login`** (2026-05-28): `throttle:10,1` implementado. Limita a 10 intentos por minuto.
- [x] **Validar mime/extension** (2026-05-28): `InspeccionController::store` valida tipos
  - `reporte`: `mimes:doc,docx,xls,xlsx` (max 10MB)
  - `imagenes`: `mimes:zip` (max 50MB)
- [x] **CORS explícito** (2026-05-28): `backend/config/cors.php` creado con whitelist desde `.env`. Default: `http://localhost:5173`.
- [x] **Sacar `claims` completos** (2026-05-28): `GET /api/auth/me` devuelve solo `id`, `email`, `sub`, `groups`, `local_role`, `empresa_id`.
- [ ] **Verificar bucket público** en MinIO/S3: quitar `mc anonymous set download` o restringirlo a un prefix readonly específico.

### Infra

- [ ] **Schema consolidado**: decidir si `database/schema.sql` se elimina (todo por migraciones) o si se regenera con `pg_dump --schema-only`. Actualizar `postgres-init.sh` y workflow `validate-schema.yml`.
- [ ] **Drop tabla `users`** y `password_reset_tokens` del scaffold de Laravel. Ajustar `config/auth.php`.

---

## Next — 2–4 semanas

### Backend

- [ ] **Soft-delete de elementos**: cambiar `DELETE /api/elementos/{id}` a `UPDATE elementos SET activo=false` o bloquear si hay inspecciones cerradas.
- [ ] **Cleanup de archivos huérfanos**: comando `php artisan inspecciones:cleanup-orphan-files` que borre de S3 los blobs cuya inspección/archivo ya no existe.
- [x] **`password_hash` removido** (2026-05-28): Migración 2026_05_28_000011 dropea columna completamente. Seed actualizado. Nunca se genera en código (A1+A2 resueltos).
- [ ] **Notificación de revisión**: email al técnico cuando su informe pasa a `revisada` o `cerrada` con observaciones (queue + mailer).
- [ ] **Filtrado por criticidad** en `GET /api/dashboard/overview` (hoy se hace client-side).
- [ ] **Endpoints de stats** específicos por elemento (`GET /api/elementos/{id}/stats`) para evitar cargar todo el historial.

### Frontend

- [ ] **Migrar a `react-router-dom`** v6. App.tsx con `<Routes>` en lugar del ruteo manual.
- [ ] **Tipos compartidos**: completar `web/src/types/{inspeccion,usuario,api}.ts` (hoy vacíos).
- [ ] **Borrar el parche `normalizeCriticidad`** en `Dashboard.tsx:29` (mojibake ya resuelto en backend).
- [ ] **Pantalla `MisNovedades`** para técnico: ver hallazgos abiertos asignados a él.
- [ ] **Drag & drop** del Word + ZIP en `InspectionModal`.

### DX / Tooling

- [ ] **CI completo**: agregar `test-backend` (PHPUnit) y `test-web` (vitest/build) al workflow.
- [ ] **Pre-commit hook** que corra `php-cs-fixer` + `eslint --fix` en cambios staged.
- [ ] **`.env.example`** sincronizado con todas las claves en uso.
- [ ] **README de `backend/`** propio (no el scaffold de Laravel).

---

## Later — 1–3 meses

### Producto

- [ ] **Mobile (Flutter)**: arrancar implementación del módulo `mobile/`, hoy vacío. Carga offline de inspecciones + sync al volver con conexión.
- [ ] **Reportes**: PDF de un informe armado server-side (Word → PDF) descargable desde la UI.
- [ ] **Vista de tendencias**: gráfico de novedades por mes y por elemento para detectar patrones.
- [ ] **Búsqueda full-text** sobre `inspecciones.resumen` y `novedades.descripcion` (PG `tsvector`).
- [ ] **Importador genérico** de Words: extraer novedades del Word legacy automáticamente y proponer estructurarlas.

### Infra producción

- [ ] **Terraform**: completar módulos `infra/terraform/` (hoy vacíos):
  - `rds.tf` — RDS Postgres con backups automáticos.
  - `s3.tf` — bucket privado con lifecycle (mover a Glacier después de 1 año).
  - `cognito.tf` — User Pool + App Client.
  - `cloudfront.tf` — distribución para frontend estático.
  - `ec2.tf` o ECS — backend.
- [ ] **Pipeline CD**: GitHub Actions con build → push imagen → deploy a ECS.
- [ ] **Observabilidad**: CloudWatch logs centralizado + alarms básicos (error rate, latencia).
- [ ] **Backups**: cron de `pg_dump` a un bucket S3 con encriptación y retención 30 días.

### Seguridad avanzada

- [ ] **Row-level security** en Postgres como red de seguridad extra contra fugas de tenant.
- [ ] **MFA obligatorio** para `admin` y `supervisor` (configurar en Cognito).
- [ ] **Rotación automática** del Cognito app client secret cada 90 días.
- [ ] **Auditoría detallada**: re-introducir una tabla `auditoria_eventos` con solo los cambios sensibles (login, cambio de rol, alta/baja de usuario, cierre de inspección).

---

## Wishlist (sin commitment)

- Integración con sistemas SCADA del cliente para correlacionar temperaturas con carga eléctrica real.
- IA / visión: análisis automático de imágenes termográficas para sugerir criticidad.
- Alertas por umbral: si una novedad crítica no se resuelve en X días, escalado automático.
- Multi-idioma (inglés) — hoy todo está en español.
- Modo "campo" sin conexión en mobile con sincronización diferencial.

---

## Convenciones para el roadmap

- **Now** = en sprint actual, debería estar listo en 1 semana.
- **Next** = en el horizonte de 2–4 semanas, ya con definición.
- **Later** = identificado pero no priorizado, semanas/meses.
- **Wishlist** = ideas sin commitment.

Cuando se cierra una tarea, se mueve a la sección "Done" con la fecha aproximada en el changelog del repo.
