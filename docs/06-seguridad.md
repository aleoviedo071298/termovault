# Seguridad - TermoVault

> Modelo de seguridad, roles, scope y checklist de hardening.

## Fuente de verdad para DB

Para cualquier decision relacionada con datos, la fuente de verdad es la estructura actual de la DB y los archivos de `database/` en la raiz del repo.

`database/schema.sql` es un snapshot generado para inicializacion local con Docker. No debe editarse a mano ni usarse como autoridad si contradice a la DB real. Cuando cambie la estructura, regenerarlo desde Postgres siguiendo `scripts/regenerate-schema-sql.md`.

## Modelo de auth

### Identidad

- AWS Cognito User Pool es el proveedor de identidad.
- El backend no almacena contrasenas locales.
- El login usa `POST /api/auth/login`, que delega a Cognito con `InitiateAuth`.
- El frontend usa el `access_token` como `Authorization: Bearer ...` para la API.
- `/api/auth/me` devuelve un perfil filtrado y el rol efectivo local.

### Verificacion de JWT

Implementacion en `App\Services\CognitoJwtVerifier`:

1. Split del JWT en `header.payload.signature`.
2. Validacion de `exp`, `iat`, `iss` con leeway configurable.
3. Validacion de `aud` o `client_id`.
4. Descarga y cache de JWKS de Cognito.
5. Verificacion de firma RSA-SHA256 con `openssl_verify`.

Si falla, la API responde `401 Invalid token` sin exponer detalles internos del verificador.

### Provisioning local

En el primer login valido de un usuario que existe en Cognito pero no en `usuarios`, `LocalUserProvisioner::findOrProvisionFromClaims` crea la fila local con:

- Email normalizado a lowercase.
- Rol derivado del primer grupo de `cognito:groups`; default `tecnico` si no hay grupo.
- Empresa desde `custom:empresa_id` o `empresa_id`.
- Nombre y apellido desde `given_name` y `family_name`.

Si el claim de empresa falta o apunta a una empresa inexistente, el provisioning falla con `403 Usuario no provisionado`. No hay fallback a la primera empresa de la DB.

Si el usuario local esta `activo=false`, el middleware devuelve `403 Usuario inactivo`.

## Roles y permisos

Los roles globales viven en `roles.codigo`:

| Codigo | Nombre | Alcance |
|---|---|---|
| `admin` | Administrador | Global, sin restricciones. |
| `supervisor` | Supervisor | Segun empresa/yacimientos asignados. |
| `tecnico` | Tecnico | Sus propias inspecciones, dentro de su scope. |

El rol local en DB domina sobre grupos stale de Cognito. Los grupos de Cognito sirven para provisioning inicial o fallback cuando todavia no hay rol local.

### Tipos de supervisor

- Supervisor PAE: supervisor de empresa PAE con `YAC-PAE` asignado. Puede operar con alcance global donde corresponde.
- Supervisor contratista: supervisor no PAE. Ve informes de tecnicos de su empresa y no revisa/cierra.

Implementado en `App\Services\Auth\AccessScopeResolver`.

### Matriz de capacidades

| Accion | admin | supervisor PAE | supervisor contratista | tecnico |
|---|:---:|:---:|:---:|:---:|
| Ver dashboard | Si, global | Si, scope | Si, empresa | Si, propias |
| Ver elementos | Si | Si, scope | Si, scope | Si, scope |
| Crear/editar elemento | Si | Si, scope | No | No |
| Borrar elemento | Si | Si, scope | No | No |
| Crear inspeccion | Si | Si, scope | Si, scope | Si, scope |
| Ver inspeccion | Si | Si, scope | Si, scope | Si, propias |
| Revisar/cerrar inspeccion | Si | Si | No | No |
| Gestionar usuarios | Si | No | No | No |

## Defensas implementadas

- Middleware `cognito.auth` en rutas privadas.
- Middleware `role.claim:*` parametrizable por endpoint.
- Scope por rol/tenant mediante `AccessScopeResolver`.
- Validacion de input en controladores.
- Constraints y FK en Postgres.
- `usuarios.email` unico.
- Indice funcional `LOWER(email)`.
- Bloqueo de usuarios inactivos.
- Sanitizacion de nombres de archivo antes de guardar.
- Descargas por endpoint backend autorizado: `/api/archivos/{id}/download`.
- Bucket privado compatible con descargas desde plataforma.
- Auditoria de descargas en DB (`auditoria_descargas_archivos`) con `archivo_id`, `usuario_id`, `ip_address`, `user_agent` y `descargado_en`.
- CORS configurable por entorno.
- Rate limit en `/api/auth/login` (throttle, 10 req/min).

## Rate limiting

- `POST /api/auth/login` usa middleware throttle (`10 req/min`).
- Eventos de abuso se deben monitorear tambien a nivel app y reverse-proxy.

## CSRF model

- La API es JWT-based y stateless.
- No se usan tokens CSRF para llamadas JSON autenticadas por bearer token.
- Los clientes browser deben mantener controles estrictos de origin/CORS.

## Headers hardening

- Headers de seguridad globales aplicados via middleware.
- Respuestas de API usan `Cache-Control: no-store, ...`, `Pragma: no-cache`, `Expires: 0`.
- Respuestas no-API del backend pueden usar cache policy publica estatica.

## Logging y auditoria

- Intentos de auth fallidos se loguean con metadata del request.
- Violaciones de scope se loguean como eventos estructurados.
- Operaciones que cambian estado se loguean via `AuditTrail`.
- Secrets: `.env` es local-only y gitignored; en produccion se gestionan fuera del repo (ver `../SECURITY.md` para el detalle operativo).

## Riesgos conocidos / pendientes

### Criticos

1. ✅ `COGNITO_AUTH_REQUIRED=true` por defecto. `/api/health` y `/api/auth/login` permanecen publicos.
2. `COGNITO_APP_CLIENT_SECRET` local: mantener fuera de Git y rotar antes de produccion.
3. ✅ Validacion de MIME/extension en uploads:
   - `reporte`: `doc`, `docx`, `xls`, `xlsx`, max 10 MB.
   - `imagenes`: `zip`, max 50 MB.
4. ✅ `database/schema.sql` regenerado desde la DB local actual el 2026-05-28.

### Altos

1. Revisar `mc anonymous set download` en `docker-compose.yml`; con descargas por API ya no deberia hacer falta para el bucket principal.
2. `DELETE /api/elementos/{id}` hace hard-delete; conviene migrar a soft-delete o bloqueo si existe historial.

### Medios

1. Modelo `User.php`, `UserFactory.php` y tabla `users` no existen en el arbol/DB actual.
2. `password_hash` no existe en la migracion base actual, en `database/schema.sql` ni en la DB local actual.
3. Trazabilidad de descargas implementada por API (tabla `auditoria_descargas_archivos`).
4. Pendiente: comando de limpieza de archivos huerfanos en S3/MinIO.

## Checklist pre-deploy

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_KEY` unico del entorno.
- [ ] `LOG_LEVEL=info` o `warning`.
- [ ] `COGNITO_AUTH_REQUIRED=true`.
- [ ] Secretos Cognito/AWS desde secret manager, no desde archivos versionados.
- [ ] `DB_PASSWORD` fuerte y distinto a dev.
- [ ] Bucket S3 privado.
- [ ] `php artisan migrate --force` aplicado contra la DB target.
- [ ] `php artisan config:cache && php artisan route:cache`.
- [ ] Sin `dd()`, `var_dump`, `dump()` ni logs sensibles.
- [ ] Frontend con `VITE_API_URL` HTTPS de prod.
