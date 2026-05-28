# Seguridad — TermoVault

> Modelo de seguridad, roles, scope y checklist de hardening.

## Modelo de auth

### Identidad

- **AWS Cognito User Pool** es el único proveedor de identidad. El backend no almacena contraseñas.
- El usuario hace login contra `POST /api/auth/login`, que delega a Cognito vía `InitiateAuth` con `USER_PASSWORD_AUTH`.
- Cognito devuelve tres tokens:
  - **`access_token`** (JWT firmado RSA) — el que viaja en el header `Authorization: Bearer …` a la API.
  - **`id_token`** (JWT con claims del usuario) — el frontend lo usa para extraer `email`, `cognito:groups`, etc.
  - **`refresh_token`** — guardado en `localStorage` (frontend), permite renovar `access_token`.

### Verificación de JWT (backend)

Implementación en `App\Services\CognitoJwtVerifier`:

1. Split del JWT en `header.payload.signature`.
2. Validar `exp`, `iat`, `iss` con leeway configurable (default 60s).
3. Validar `aud` (id_token) o `client_id` (access_token).
4. Descargar JWKS de Cognito (`https://cognito-idp.{region}.amazonaws.com/{pool}/.well-known/jwks.json`), cachear 6h.
5. Verificar firma RSA-SHA256 con `openssl_verify`.

Si cualquier paso falla → `401 Invalid token`.

### Provisioning local de usuarios

En el primer login válido de un usuario que existe en Cognito pero no en la tabla local `usuarios`, `LocalUserProvisioner::findOrProvisionFromClaims` crea la fila con:

- Email del claim (normalizado lowercase).
- Rol derivado del primer grupo en `cognito:groups`; default `tecnico` si no hay grupo.
- Empresa del claim `custom:empresa_id`; default a la primera empresa de la DB si no hay claim.
- `nombre` / `apellido` desde `given_name` / `family_name`.

Si el usuario en `usuarios` está marcado `activo=false`, el middleware devuelve `403 Usuario inactivo`.

### Roles y matriz de permisos

Tres roles globales declarados en `roles.codigo`:

| Codigo | Nombre | Alcance |
|---|---|---|
| `admin` | Administrador | Global, sin restricciones. |
| `supervisor` | Supervisor | Ver `Tipos de supervisor` debajo. |
| `tecnico` | Técnico | Solo sus propias inspecciones, en yacimientos asignados. |

### Tipos de supervisor

El rol `supervisor` se interpreta en runtime según contexto:

- **Supervisor PAE** = supervisor cuya empresa es PAE (o contiene "PAE") y que tiene `YAC-PAE` entre sus yacimientos asignados.
  - Puede revisar/cerrar inspecciones.
  - Puede crear/editar/borrar elementos.
- **Supervisor contratista** = cualquier otro supervisor.
  - Solo ve informes de técnicos de su empresa.
  - No puede revisar/cerrar.
  - No puede mutar elementos.

Implementado en `App\Services\Auth\AccessScopeResolver`.

### Matriz de capacidades

| Acción | admin | supervisor PAE | supervisor contratista | tecnico |
|---|:---:|:---:|:---:|:---:|
| Ver dashboard | ✓ global | ✓ yacimiento | ✓ su empresa | ✓ propias |
| Ver elementos | ✓ | ✓ scope | ✓ scope | ✓ scope |
| Crear/editar elemento | ✓ | ✓ scope | ✗ | ✗ |
| Borrar elemento | ✓ | ✓ scope | ✗ | ✗ |
| Crear inspección | ✓ | ✓ scope | ✓ scope | ✓ scope |
| Ver inspección | ✓ | ✓ scope | ✓ scope | ✓ propias |
| Revisar / cerrar inspección | ✓ | ✓ | ✗ | ✗ |
| Gestionar usuarios / empresas / yacimientos | ✓ | ✗ | ✗ | ✗ |

"Scope" = limitado a yacimientos asignados al usuario en `usuario_yacimientos`.

---

## Defensas implementadas

### En backend

- ✅ **Middleware obligatorio** `cognito.auth` en todas las rutas privadas.
- ✅ **Middleware de roles** `role.claim:admin,supervisor,...` parametrizable por endpoint.
- ✅ **Scope de tenant** automático vía `AccessScopeResolver` antes de ejecutar queries.
- ✅ **Validación de input** con `$request->validate(...)` en cada `store/update`.
- ✅ **CHECK constraints** en Postgres para estados de inspecciones y novedades.
- ✅ **UNIQUE constraints**: `usuarios.email`, `yacimientos(empresa_id, codigo)`, `elementos(yacimiento_id, codigo)`.
- ✅ **Email case-insensitive** vía índice funcional `LOWER(email)`.
- ✅ **Bloqueo de usuarios inactivos** en middleware.
- ✅ **Sanitización de nombres de archivo** (`safeStorageFileName`) antes de guardar en S3.
- ✅ **Modelos esconden `password_hash`** (`Usuario::$hidden`).

### En frontend

- ✅ **Token storage** en `localStorage` (aceptable para SaaS interno; en consumer apps preferir HttpOnly cookies).
- ✅ **Expiración chequeada al bootstrap**: si `exp < now`, se borra el token.
- ✅ **Renovación automática** vía `id_token` claims al recargar.
- ✅ **ProtectedRoute** valida que el usuario tenga al menos uno de los grupos requeridos.

---

## Riesgos conocidos / pendientes (al 2026-05-28)

Ver `docs/05-roadmap.md` para detalle. Los más importantes:

### Críticos

1. ✅ **`COGNITO_AUTH_REQUIRED=true`** (RESUELTO 2026-05-28) — Middleware enforces JWT en endpoints protegidos. `/api/health` y `/api/auth/login` permanecen públicos.
2. **`COGNITO_APP_CLIENT_SECRET` en filesystem local** — rotar y mantener solo placeholder en `.env.example`. (POSPUESTO: costo innecesario en fase de desarrollo)
3. ✅ **Validación de mime/extensión en uploads** (RESUELTO 2026-05-28) — `InspeccionController` valida tipos:
   - `reporte`: `doc,docx,xls,xlsx` (max 10MB)
   - `imagenes`: `zip` (max 50MB)
4. ✅ **`schema.sql` desincronizada** (RESUELTO 2026-05-28) — Documentado flujo: schema.sql es snapshot para Docker init, migraciones son canonical source. Ver `scripts/regenerate-schema-sql.md`.

### Altos

4. **Bucket MinIO con `mc anonymous set download` sobre prefix `public`** — revisar en docker-compose.yml.
5. ✅ **Throttle en `/api/auth/login`** (RESUELTO 2026-05-28) — `throttle:10,1` implementado. Limita a 10 intentos por minuto para mitigar fuerza bruta.
6. ✅ **CORS explícito** (RESUELTO 2026-05-28) — `backend/config/cors.php` configurable por `.env`. En prod requiere whitelist explícita.
7. **`DELETE /api/elementos/{id}` hard-delete cascada** — borra evidencia histórica. Cambiar a soft-delete.
8. ✅ **`/api/auth/me` filtra claims** (RESUELTO 2026-05-28) — Devuelve solo `id`, `email`, `sub`, `groups`, `local_role`, `empresa_id`. Eliminado payload completo de JWT.

### Medios

9. ✅ **Modelo `User.php` + tabla `users` duplicados** (RESUELTO 2026-05-28) — Eliminado modelo User.php, UserFactory.php, migración scaffold. Config auth.php ahora usa Usuario. Tabla users será dropeada por migración 2026_05_28_000012.
10. ✅ **`password_hash` removido** (RESUELTO 2026-05-28) — Columna dropeda por migración 2026_05_28_000011. Seed actualizado. Código (LocalUserProvisioner, AdminUserController) nunca la genera.

---

## Checklist de hardening pre-deploy

Antes de cualquier despliegue a un entorno que no sea local:

### Variables de entorno

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` regenerado para el entorno (no reusar el de dev)
- [ ] `LOG_LEVEL=info` o `warning` (no `debug`)
- [ ] `COGNITO_AUTH_REQUIRED=true`
- [ ] `COGNITO_APP_CLIENT_SECRET` cargado desde un secret manager (AWS Secrets Manager / Parameter Store), no commiteado
- [ ] `DB_PASSWORD` fuerte y diferente al de dev
- [ ] `AWS_*` apuntan a buckets reales, no MinIO

### Backend

- [ ] `php artisan migrate --force` aplicado contra la DB target
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Sin `dd()`, `var_dump`, `dump()` en código
- [ ] Sin `Log::debug` con info sensible
- [ ] HTTPS obligatorio (TLS termination en ALB)

### Frontend

- [ ] `npm run build` produce bundle minified
- [ ] `VITE_API_URL` apunta al dominio de prod (HTTPS)
- [ ] Sourcemaps NO publicados (`build.sourcemap=false` en `vite.config.js`)
- [ ] CSP header configurado en CloudFront

### AWS

- [ ] Cognito User Pool con password policy fuerte (min 12 chars, mayúsculas, números, símbolos)
- [ ] Cognito con MFA opcional habilitada (obligatoria para admin)
- [ ] RDS con backup automático diario, retención 7 días
- [ ] RDS publicly accessible = false
- [ ] S3 bucket sin acceso público (excepto rutas explícitas con presigned URLs)
- [ ] CloudFront con WAF (mínimo rate limit y bad bots)
- [ ] IAM roles con least privilege (la EC2/ECS solo puede leer/escribir el bucket TermoVault, nada más)

### Operacional

- [ ] Backups verificados restaurables
- [ ] Plan de rotación de secretos documentado
- [ ] Runbook de incident response básico
- [ ] Monitoring de error rate y latencia
- [ ] Alertas a Slack/email para 5xx > umbral

---

## Procedimientos

### Resetear contraseña de un usuario

Cognito maneja el flujo. Admin de AWS:

1. Consola de Cognito → Users → seleccionar usuario → Reset password.
2. El usuario recibe un mail de Cognito con un código.
3. Próximo login solicita el nuevo password vía challenge `NEW_PASSWORD_REQUIRED`.

### Inactivar a un usuario

- Frontend admin: editar usuario en `Admin Usuarios`, marcar `activo=false`.
- Effect: el middleware `EnsureCognitoJwt` devuelve `403 Usuario inactivo` aunque el JWT siga válido.
- **No** deshabilita la cuenta en Cognito. Para eso ir a la consola de AWS y deshabilitar/eliminar el usuario.

### Cambiar el rol de un usuario

- **En Cognito**: editar grupos del usuario (`cognito:groups`). El próximo token reflejará el cambio.
- **En backend**: editar el usuario en `Admin Usuarios` y cambiar `rol_codigo`. Funciona como fallback si el JWT no trae `cognito:groups`.

### Investigar un acceso sospechoso

1. Revisar `storage/logs/laravel.log` filtrado por IP/email.
2. Cross-check con Cognito CloudWatch logs (eventos `InitiateAuth`, `SignIn`, `ForgotPassword`).
3. Si confirmado: invalidar la sesión del usuario en Cognito (Sign out user), forzar reset de password, marcar `activo=false` en backend si es interno.
