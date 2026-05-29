# SECURITY - TermoVault

## Authentication

- Identity provider: AWS Cognito User Pool.
- Login endpoint: `POST /api/auth/login`.
- Protected API uses `Authorization: Bearer <access_token>`.
- JWT verification:
  - signature (JWKS),
  - issuer/audience,
  - expiration and leeway checks.

## Authorization

- Middleware chain:
  - `cognito.auth`
  - `role.claim:*`
- Effective role resolution:
  1) local DB role (`usuarios.rol_id`) if present,
  2) Cognito groups fallback.
- Scope enforcement:
  - tenant boundary by `empresa_id`,
  - yacimiento assignment,
  - owner-supervisor flag for element mutations.

## Rate limiting

- `POST /api/auth/login` uses throttle middleware (`10 req/min`).
- Abuse events should be monitored at app and reverse-proxy level.

## CSRF model

- API is JWT-based and stateless.
- CSRF tokens are not used for bearer-authenticated JSON API calls.
- Browser clients must keep strict origin/cors controls.

## File upload and download

- Upload validation:
  - report files: doc/docx/xls/xlsx, max 10 MB
  - image pack: zip, max 50 MB
- Filenames are sanitized before storage key generation.
- Downloads are always authorized by backend scope checks.
- Download events are audited in DB (`auditoria_descargas_archivos`).

## Headers hardening

- Global security headers are applied via middleware.
- API responses use:
  - `Cache-Control: no-store, ...`
  - `Pragma: no-cache`
  - `Expires: 0`
- Non-API responses from backend may use public static cache policy.

## Logging and audit

- Failed auth attempts are logged with request metadata.
- Scope violations are logged as structured events.
- State-changing operations are logged through `AuditTrail`.

## Secrets handling

- `.env` files are local-only and gitignored.
- Production secrets must be managed outside repo (for example Secrets Manager).
