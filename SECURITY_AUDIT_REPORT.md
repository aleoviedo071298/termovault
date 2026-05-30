# TermoVault — Informe de Auditoría de Seguridad

**Fecha:** 2026-05-29
**Alcance:** Backend Laravel 12, Frontend React/Vite, PostgreSQL, AWS Cognito (JWT RS256), S3/MinIO privado, auditoría de descargas, RBAC + scope por empresa/yacimiento.
**Tipo:** Auditoría defensiva (solo análisis — sin explotación, sin modificación de código).
**Estado del proyecto:** Pre-producción (entorno de desarrollo local con Docker).

> **Nota de honestidad:** este informe no es complaciente, pero tampoco infla hallazgos. El núcleo de seguridad (autenticación, autorización, acceso a archivos) está sorprendentemente sólido para la etapa del proyecto. La mayoría de los hallazgos abiertos son de **preparación para producción** y **observabilidad/auditoría**, no vulnerabilidades explotables en el estado actual.

---

## 1. Resumen ejecutivo

| Severidad | Cantidad |
|-----------|----------|
| Crítico | 0 |
| Alto | 2 |
| Medio | 5 (M3 y M4 ya resueltos) |
| Bajo | 4 (B3 descartado como falso positivo) |
| Informativo / mitigado | 14 |

**Veredicto general:** El sistema tiene una base de seguridad **bien diseñada**: verificación criptográfica completa del JWT, resolución de identidad por match exacto, autorización por scope aplicada en la propia query (mitiga IDOR), descargas siempre por backend con auditoría y validación de magic-bytes, y sin almacenamiento de contraseñas locales. **No se detectaron vulnerabilidades críticas ni vías de escalación de privilegios explotables** en el código actual.

Los riesgos reales se concentran en **tres frentes**:
1. **Configuración de producción aún no endurecida** (`APP_DEBUG=true`, orígenes CORS placeholder, secretos en `.env`).
2. **Integridad y cobertura de la auditoría** (logs de auditoría no son inmutables; los intentos no autorizados no se registran; el borrado en cascada elimina el historial de descargas).
3. **Defaults de scope permisivos** para usuarios sin yacimiento asignado (acceso a nivel empresa).

Ninguno bloquea el desarrollo actual, pero **H1, H2 y M2 deben resolverse antes de exponer el sistema a internet**.

---

## 2. Mapa de superficie de ataque

### Entradas expuestas (rutas)
| Ruta | Auth | Rol | Notas |
|------|------|-----|-------|
| `GET /api/health` | Pública | — | Solo metadata, sin datos sensibles ✓ |
| `GET /` (web) | Pública | — | Status JSON estático ✓ |
| `POST /api/auth/login` | Pública | — | `throttle:10,1`; proxy a Cognito vía `Http::` |
| `GET /api/auth/me` | JWT | cualquiera | Devuelve claims mínimos ✓ |
| `GET /api/catalogos` | JWT | cualquiera | Cacheado 1h |
| `GET /api/dashboard/overview` | JWT | admin/sup/tec | Scope por empresa |
| `GET /api/elementos`, `/elementos/{id}` | JWT | admin/sup/tec | Scope en query |
| `GET /api/inspecciones/{id}` | JWT | admin/sup/tec | Scope en query |
| `POST /api/inspecciones` | JWT | admin/sup/tec | `throttle:5,60`, multipart |
| `GET /api/archivos/{id}/download` | JWT | admin/sup/tec | Scope en query + auditoría |
| `POST/PUT/DELETE /api/elementos` | JWT | admin/sup | `canMutateElement` |
| `PATCH /api/inspecciones/{id}/estado` | JWT | admin/sup | owner-supervisor/admin |
| `*/admin/*` | JWT | admin | Gestión usuarios/empresas/yacimientos |

### Activos sensibles
- Archivos térmicos / informes (S3/MinIO privado).
- PII de usuarios (email, nombre) y estructura organizacional (empresas/yacimientos).
- Tokens JWT de Cognito.
- Secretos: `COGNITO_APP_CLIENT_SECRET`, `APP_KEY`, credenciales S3.

### Vectores principales
1. Bypass de scope multi-tenant (IDOR) → **mitigado** (scope en query).
2. Escalación de rol → **mitigado** (rol desde DB local, no claim manipulable).
3. Exposición de archivos privados → **mitigado** (todo por backend + scope + auditoría).
4. Fuga de info por errores → **abierto** (APP_DEBUG, H1).
5. CORS mal configurado → **abierto** (placeholders, M2).

---

## 3. Hallazgos ALTOS

### H1 — `APP_DEBUG=true` filtra stack traces completos
- **Severidad:** Alto (si se despliega; Bajo mientras sea solo local)
- **OWASP:** A05 Security Misconfiguration
- **Evidencia:** `backend/.env:4` y `backend/.env.example:4` → `APP_DEBUG=true`. Reproducido en esta auditoría: una respuesta `429` devolvió el JSON completo con `exception`, `file` y **rutas absolutas del filesystem** (`C:\Users\Alejandro\Desktop\local\termovault\backend\vendor\...`) y traza de los 30+ frames de middleware.
- **Impacto:** En un entorno accesible, expone rutas internas, versiones, estructura de middleware y mensajes de excepción → facilita reconocimiento del atacante.
- **Riesgo real:** Alto en producción / nulo en local aislado.
- **Recomendación:** Forzar `APP_ENV=production` y `APP_DEBUG=false` en el deploy; validarlo en el pipeline (fallar el build si `APP_DEBUG=true` con `APP_ENV=production`). `config/app.php:42` ya tiene default seguro (`env('APP_DEBUG', false)`), así que basta con no setearlo en prod.
- **Archivos:** `backend/.env`, `backend/.env.example`, pipeline de deploy.
- **Prioridad:** Antes de producción (bloqueante).

### H2 — Orígenes CORS hardcodeados con placeholders + config real muerta
- **Severidad:** Alto (preparación prod / misconfiguration)
- **OWASP:** A05 Security Misconfiguration
- **Evidencia:** `app/Http/Middleware/SecurityHeaders.php:13-18` hardcodea `['https://app.example.com','https://app.staging.example.com','http://localhost:5173','http://127.0.0.1:5173']`. La auditoría confirmó (vía `curl`) que `config/cors.php` (que sí lee `CORS_ALLOWED_ORIGINS`) **no se aplica** — no aparece `Vary: Origin` ni los `exposed_headers` del config; todo el CORS lo maneja a mano `SecurityHeaders`.
- **Impacto:** (1) Configuración confusa: `config/cors.php` y `CORS_ALLOWED_ORIGINS` dan falsa sensación de control. (2) En producción, el origen real del frontend está hardcodeado como `app.example.com` (placeholder) → o se bloquea el front real, o alguien lo "arregla" con un comodín peligroso.
- **Riesgo real:** Medio-Alto: no es explotable hoy (localhost funciona), pero es una trampa garantizada al desplegar.
- **Recomendación:** Unificar el manejo de CORS en **una sola** fuente leída de entorno (`CORS_ALLOWED_ORIGINS`), eliminar la lista hardcodeada, y documentar que el SPA define su propio CSP. No usar `*` con credenciales.
- **Archivos:** `app/Http/Middleware/SecurityHeaders.php`, `config/cors.php`, `bootstrap/app.php`.
- **Prioridad:** Antes de producción.

---

## 4. Hallazgos MEDIOS

### M1 — Auditoría no es inmutable y se borra en cascada
- **Severidad:** Medio
- **OWASP:** A09 Security Logging & Monitoring Failures
- **Evidencia:**
  - `auditoria_descargas_archivos.archivo_id` → `cascadeOnDelete()` (`migration 000015:13`). Como `elementos → inspecciones → archivos` también cascadea, un admin que **elimina un elemento borra todo el historial de descargas** de sus archivos.
  - La auditoría de acciones de estado (`AuditTrail::record`) escribe a **logs de aplicación** (`Log::info('audit.trail')`, `AuditTrail.php:17`), no a una tabla consultable/inmutable. Los logs rotan y se pueden perder/alterar.
- **Impacto:** Pérdida de trazabilidad ante borrados; auditoría no apta para compliance (Etapa 10) ni para investigación forense.
- **Recomendación:** Para descargas, considerar `restrictOnDelete`/soft-delete o archivar la auditoría antes del borrado. Para acciones de estado, persistir en una tabla `audit_trail` dedicada (append-only) además del log. Evaluar bucket/almacén append-only para retención.
- **Archivos:** `migration 000015`, `app/Services/AuditTrail.php`.
- **Prioridad:** Antes de producción / Etapa 10.

### M2 — CSP y headers ignoran las env definidas; CSP del API es irrelevante para el SPA
- **Severidad:** Medio
- **OWASP:** A05 Security Misconfiguration
- **Evidencia:** `.env.example:42-44` define `SECURITY_HEADERS_ENABLED` y `SECURITY_CSP`, pero `SecurityHeaders.php` **no los lee** — hardcodea todo (incluyendo `script-src 'self' https://cdn.jsdelivr.net` y `connect-src ... http://localhost:8000`, que no tienen sentido en respuestas de una API JSON). El SPA (Vite) no recibe CSP del backend; su host debe definirla.
- **Impacto:** Falsa expectativa de configurabilidad; el SPA queda sin CSP efectiva (riesgo XSS mitigado solo por React + DOMPurify).
- **Recomendación:** Definir la CSP **en el hosting del frontend** (nginx/CloudFront), estricta para el SPA. En el backend, dejar headers mínimos coherentes con una API. Eliminar las env muertas o cablearlas.
- **Archivos:** `app/Http/Middleware/SecurityHeaders.php:52-62`, `.env.example`.
- **Prioridad:** Antes de producción.

### M3 — Los intentos de acceso no autorizado a archivos no se registran
- **✅ ESTADO: RESUELTO (2026-05-29)** — `ArchivoController::download` ahora emite `auth.scope.violation` (`archivo.download.denied.out_of_scope`) cuando el archivo existe pero queda fuera de scope, con `user_id`, `archivo_id`, IP y UA. Test: `test_user_cannot_download_file_outside_scope`.
- **Severidad:** Medio
- **OWASP:** A09 Logging & Monitoring Failures
- **Evidencia:** `ArchivoController::download` devuelve `404` cuando el archivo está fuera de scope (`ArchivoController.php:58-60`) **sin emitir un log de seguridad**. Solo se audita la descarga exitosa. (En contraste, `AccessScopeResolver` sí loguea violaciones de scope en elementos/inspecciones.)
- **Impacto:** Un atacante autenticado puede enumerar IDs de archivos ajenos sin dejar rastro; no hay señal para detección.
- **Recomendación:** Loguear `auth.scope.violation` (igual que el resolver) cuando un archivo existe pero queda fuera de scope, con `user_id`, `archivo_id`, IP, UA.
- **Archivos:** `app/Http/Controllers/ArchivoController.php`.
- **Prioridad:** Corto plazo.

### M4 — Default de scope permisivo para usuarios sin yacimiento asignado
- **✅ ESTADO: RESUELTO (2026-05-29) — Opción A (fail-closed) para técnico.** `AccessScopeResolver::applyElementScope` y `canCreateInspectionForElement` ahora niegan acceso al técnico sin yacimiento asignado (antes empresa-wide); lectura y escritura quedaron alineadas. El supervisor mantiene fallback por empresa (intencional para contratista). Test: `test_unassigned_tecnico_cannot_create_inspection`.
- **Severidad:** Medio (revisar intención de diseño)
- **OWASP:** A01 Broken Access Control (diseño)
- **Evidencia:**
  - `AccessScopeResolver::canCreateInspectionForElement` — un **técnico/supervisor sin yacimientos asignados** puede crear inspecciones para **cualquier elemento de su empresa** (`AccessScopeResolver.php:162-171, 183-192`).
  - `applyElementScope` — un técnico/supervisor sin asignaciones **lee todos los elementos de su empresa** (`AccessScopeResolver.php:106-112`).
  - **Inconsistencia:** `InspeccionController::show` para técnico sin asignaciones aplica `1=0` (no ve nada), pero la creación sí permite a nivel empresa. Reglas de lectura y escritura difieren para el mismo usuario.
- **Impacto:** Un técnico recién creado sin yacimientos podría operar sobre toda la empresa. Puede ser intencional, pero es un default amplio y la inconsistencia es señal de regla poco definida.
- **Recomendación:** Definir explícitamente: ¿“sin asignación” = acceso empresa o = sin acceso? Recomendado: **sin acceso** (fail-closed) para técnico, y unificar lectura/escritura. Documentar la decisión.
- **Archivos:** `app/Services/Auth/AccessScopeResolver.php`, `app/Http/Controllers/InspeccionController.php`.
- **Prioridad:** Corto plazo (decisión de producto + ajuste).

### M5 — Gestión de secretos en `.env` (no en un store gestionado)
- **Severidad:** Medio (preparación prod)
- **OWASP:** A05 / A02 (Cryptographic Failures por manejo de secretos)
- **Evidencia:** `backend/.env` contiene `COGNITO_APP_CLIENT_SECRET`, `APP_KEY` y credenciales S3 reales en texto plano. Está `gitignored` y el hook `pre-commit` bloquea `.env` (✓), pero en deploy el patrón `.env` en el servidor/imagen es frágil.
- **Impacto:** Riesgo de filtración de secretos en imágenes Docker, backups o variables de entorno mal gestionadas.
- **Recomendación:** Migrar secretos a **AWS SSM Parameter Store (SecureString)** o Secrets Manager para producción (alineado con la conversación previa sobre SSM). Rotar el `COGNITO_APP_CLIENT_SECRET` (estuvo visible en sesiones de trabajo).
- **Archivos:** `backend/.env`, despliegue.
- **Prioridad:** Antes de producción.

---

## 5. Hallazgos BAJOS

### B1 — Sesión no persiste entre recargas; refresh token descartado
- **Severidad:** Bajo (UX / no es vulnerabilidad)
- **Evidencia:** `TokenManager` guarda tokens **solo en memoria** y `setToken` fuerza `refreshToken = null` (`TokenManager.ts:13`). Al recargar la página, el usuario se desloguea.
- **Impacto:** Tradeoff deliberado a favor de seguridad (resistente a XSS/robo de token). Costo: UX. No persistir el refresh token impide renovación silenciosa.
- **Recomendación:** Mantener memoria-only es correcto para esta app. Si se quiere persistencia, evaluar refresh token en cookie `HttpOnly`+`Secure`+`SameSite=Strict` (requiere endpoint de refresh en backend). No usar `localStorage` para tokens.
- **Prioridad:** Mejora futura.

### B2 — Header `X-XSS-Protection` obsoleto
- **Severidad:** Bajo (informativo)
- **Evidencia:** `SecurityHeaders.php:50` setea `X-XSS-Protection: 1; mode=block`. Header deprecado (los navegadores modernos lo ignoran o puede causar efectos no deseados).
- **Recomendación:** Eliminarlo y confiar en CSP. Inocuo, baja prioridad.

### B3 — ❌ DESCARTADO (falso positivo) — asignación cross-empresa es by-design
- **Severidad:** N/A — comportamiento intencional, no es un hallazgo.
- **Aclaración:** Originalmente se marcó que un admin puede asignar a un usuario yacimientos de otra empresa (`AdminUserController::validatePayload` valida `yacimientos.*` solo con `exists:yacimientos,id`). **Esto es correcto y necesario** por el modelo operador↔contratista (ver nota arquitectónica abajo): el personal de una empresa **contratista** debe poder asignarse a yacimientos de la empresa **operadora** que es dueña de esos activos. Forzar `yacimiento.empresa_id === usuario.empresa_id` rompería el flujo de contratistas.
- **Validación del modelo de scope:** el acceso del contratista funciona por **asignación de yacimiento** (`assigned_yacimiento_ids`), no por empresa, así que `applyElementScope` y `canCreateInspectionForElement` lo manejan correctamente. La **mutación** de elementos sí exige empresa propia (`owner_yacimiento_ids` con `empresa_id` igual), por lo que el contratista solo inspecciona y el operador administra. El admin es el gatekeeper de las asignaciones.

> **Nota arquitectónica (modelo multi-tenant operador ↔ contratista):**
> - **Empresa operadora** (ej. PAE): dueña de yacimientos y elementos; sus supervisores "owner" administran elementos.
> - **Empresa contratista** (ej. PECOM): aporta técnicos/supervisores que **inspeccionan** los yacimientos del operador a los que un admin los asigna.
> - Por eso un usuario puede pertenecer a una empresa y estar asignado a yacimientos de **otra**. El control de acceso es **por asignación de yacimiento**, no por igualdad de empresa.

### B4 — Rate limit de login solo por IP, sin bloqueo por cuenta
- **Severidad:** Bajo
- **Evidencia:** `routes/api.php:33` → `throttle:10,1` (por IP). No hay lockout por cuenta; Cognito provee su propia protección anti-bruteforce (se observó `LimitExceeded`/`TooManyRequests` traducido).
- **Impacto:** Bajo — Cognito mitiga el bruteforce real. El throttle de Laravel protege la capa de proxy.
- **Recomendación:** Considerar throttle adicional por email para distribuir intentos. Opcional.

### B5 — `.env.example` con `APP_DEBUG=true` y `FILESYSTEM_DISK=local`
- **Severidad:** Bajo (informativo)
- **Evidencia:** `.env.example:4,37`. Es solo plantilla, pero refuerza valores inseguros por defecto.
- **Recomendación:** Mantener un `.env.production.example` con `APP_DEBUG=false`, `APP_ENV=production`, `FILESYSTEM_DISK=s3`.

---

## 6. Buenas prácticas YA implementadas (mitigaciones verificadas)

1. **Verificación JWT criptográficamente completa** — RS256, valida `alg`, `kid`, `iss`, `aud`/`client_id` según `token_use`, `exp`, `iat` con leeway, y firma vía JWKS cacheado 6h (`CognitoJwtVerifier.php`). No acepta `alg:none`.
2. **Resolución de identidad por match exacto** — excluye `preferred_username` (user-settable), exige `email_verified`, sin matching por prefijo/LIKE (`EnsureCognitoJwt.php:78-108`). Cierra un IDOR de identidad previo.
3. **Rol desde la DB local, no del claim** — `EnsureRoleFromClaims` y `AccessScopeResolver` priorizan el rol local sobre `cognito:groups`, evitando escalación por manipulación de grupos (`EnsureRoleFromClaims.php:65-82`).
4. **Autorización por scope aplicada en la query** — `show`/`download`/`update`/`destroy` filtran por `tecnico_id`/`yacimiento_id`/`empresa_id` dentro del query, mitigando IDOR por ID directo.
5. **Descargas siempre por backend** — sin URLs públicas; bucket privado con constraint `chk_archivos_s3_bucket_valido` (`s3_bucket <> 'local'`).
6. **Validación de archivos en capas** — extensión (`.is2` validado manualmente por ser formato propietario), tamaño, y **magic-bytes** al descargar (`ArchivoController::validateMimeTypeMatch`).
7. **Separación nombre físico / nombre de descarga** — `s3_key` interno con `uuid`/`archivo_id` + nombre saneado; nombre de descarga normalizado y saneado (`ascii`, sin caracteres peligrosos). Sin path traversal (acceso por ID).
8. **Auditoría de descargas exitosas** — registra `archivo_id`, `usuario_id`, IP, UA, timestamp; el `insert` está en `try/catch` para no bloquear la operación.
9. **Sin contraseñas locales** — autenticación 100% delegada a Cognito; `Usuario` no tiene campo password.
10. **Protección SQL injection** — queries parametrizadas; `byYacimientoIds` valida enteros positivos; `whereRaw('LOWER(email)=?', [...])` con bindings; `DB::raw` no usado con input de usuario.
11. **Mass assignment controlado** — `$fillable` acotado en todos los modelos; mutación de usuarios solo por endpoints admin.
12. **Headers de seguridad presentes** — `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `Permissions-Policy`, `Cache-Control: no-store` en `/api/*`.
13. **Frontend resistente a XSS** — tokens en memoria (no `localStorage`), DOMPurify en el único `dangerouslySetInnerHTML` (`FormFields.tsx:26`), inputs saneados.
14. **Stack limpio** — sin Telescope/Debugbar; dependencias mínimas y modernas (Laravel 12, React 19, Vite 8, DOMPurify 3.4.7); sin paquetes vulnerables evidentes; hooks de git bloquean commits a `main` y archivos sensibles.

---

## 7. Riesgos residuales

- **Configuración de producción pendiente:** HTTPS/HSTS, `APP_DEBUG=false`, orígenes CORS reales, secretos en SSM. (Etapas 8-10 del roadmap).
- **Auditoría no tamper-evident** ni retenida en almacén dedicado.
- **Secreto de Cognito** estuvo visible en sesiones de trabajo → rotar antes de prod.
- **Detección/alerting ausente:** no hay WAF, ni CloudWatch/alertas sobre `auth.scope.violation` / fallos de login.
- **Sin backups automatizados** de DB ni versionado de bucket (Etapa 9).
- **Default de scope** a confirmar (M4).

---

## 8. Checklist de producción

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (validado en pipeline). **(H1)**
- [ ] HTTPS forzado + `Strict-Transport-Security` (HSTS) en el edge.
- [ ] Orígenes CORS reales, fuente única, sin placeholders ni `*`. **(H2)**
- [ ] CSP estricta definida en el host del SPA. **(M2)**
- [ ] Secretos en SSM Parameter Store (SecureString) / Secrets Manager; fuera de `.env`/imagen. **(M5)**
- [ ] Rotar `COGNITO_APP_CLIENT_SECRET` y `APP_KEY`.
- [ ] Cognito en la región correcta (`us-east-2`) y `COGNITO_AUTH_REQUIRED=true` en prod.
- [ ] PostgreSQL gestionado (RDS) con backups automáticos + retención. **(Etapa 9)**
- [ ] Bucket S3 privado, `BlockPublicAccess`, versionado y SSE (KMS). **(Etapa 9)**
- [ ] Auditoría en tabla append-only + retención; logs de violaciones de scope y descargas denegadas. **(M1, M3)**
- [ ] WAF / rate limiting perimetral + alertas (CloudWatch) sobre eventos de seguridad.
- [ ] Separación de entornos dev / staging / prod (configs y pools Cognito distintos).
- [ ] `php artisan config:cache` con verificación de que no quedó debug activo.

---

## 9. Recomendaciones priorizadas

| # | Acción | Severidad | Esfuerzo |
|---|--------|-----------|----------|
| 1 | Forzar `APP_DEBUG=false`/`APP_ENV=production` + validación en pipeline | Alto | Bajo |
| 2 | Unificar CORS en fuente única por entorno; quitar placeholders | Alto | Bajo |
| 3 | Migrar secretos a SSM + rotar Cognito secret | Medio | Medio |
| 4 | CSP del SPA en el host frontend | Medio | Bajo |
| 5 | Loggear descargas denegadas + persistir auditoría en tabla | Medio | Medio |
| 6 | Definir y unificar default de scope (fail-closed) | Medio | Bajo |
| 7 | Quitar `X-XSS-Protection`; `.env.production.example` | Bajo | Bajo |

---

## 10. Plan de corrección por etapas

### 🔴 Urgente (antes de cualquier exposición pública)
- H1: `APP_DEBUG=false` / `APP_ENV=production`.
- H2: CORS real, fuente única, sin placeholders.

### 🟡 Corto plazo (próximas iteraciones)
- ✅ M3: log de descargas denegadas (alineado con `auth.scope.violation`). **Hecho 2026-05-29.**
- ✅ M4: default de scope unificado fail-closed para técnico. **Hecho 2026-05-29.**
- ~~B3: validar yacimiento↔empresa~~ → **descartado (falso positivo)**: la asignación cross-empresa es intencional (modelo operador↔contratista).

### 🟢 Antes de producción (Etapas 8-9-10)
- M5: secretos en SSM + rotación.
- M2: CSP estricta en el SPA + headers coherentes en API.
- M1: auditoría append-only + retención; revisar cascadas de borrado.
- Checklist completo de producción (sección 8).

### 🔵 Mejora futura
- B1: estrategia de refresh token (cookie HttpOnly) si se requiere persistencia de sesión.
- B4: throttle adicional por cuenta.
- B2: limpieza de headers obsoletos.

---

## Anexo — Falsos positivos / parecen riesgo pero están mitigados

- **"IDOR en `/archivos/{id}/download`"** → mitigado: el query une `inspecciones/elementos/yacimientos/usuarios` y aplica scope; un ID ajeno devuelve 404. (El único gap es de *logging*, no de acceso — ver M3.)
- **"`dangerouslySetInnerHTML` = XSS"** → mitigado: pasa por `DOMPurify` (`sanitizeHtml`) antes de renderizar (`FormFields.tsx:24-26`).
- **"`DB::raw`/`whereRaw` = SQL injection"** → mitigado: siempre con bindings parametrizados; `byYacimientoIds` valida enteros.
- **"Tokens en el front = robo"** → mitigado: memoria-only, sin `localStorage`/`sessionStorage` para tokens.
- **"Escalación por `cognito:groups`"** → mitigado: el rol efectivo se toma de la DB local, no del claim.
- **"Archivos `.sql` en el repo"** → no es fuga: son `schema.sql` + seeds de catálogos (sin credenciales).
- **"`alg:none` / confusión de algoritmo"** → mitigado: el verificador exige `RS256` explícitamente.
- **"Admin asigna yacimientos de otra empresa (B3)"** → by-design: el modelo operador↔contratista requiere que el personal de una contratista se asigne a yacimientos de la operadora; el control de acceso es por asignación de yacimiento, no por igualdad de empresa.

---

*Auditoría defensiva — sin explotación destructiva ni modificación de código. Las correcciones se implementarán solo tras aprobación explícita.*
