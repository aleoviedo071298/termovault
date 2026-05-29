# TermoVault - Next Session Roadmap

**Date:** 2026-05-29  
**Branch baseline:** `main`  
**Status:** Etapa 7 (Frontend Security Hardening) completed successfully.

## Completed Stages

### Etapa 1-6 (already merged/completed)
- Hardening of auth identity, rate limiting, logging, audit trails, database indexing, eager loading query tuning, and caching strategies.

### Etapa 7 (this session)
- **Content Security Policy (CSP)**:
  - Added CSP meta tag in [index.html](file:///C:/Users/Alejandro/Desktop/local/termovault/web/index.html) allowing Cognito connect endpoints, style/script whitelists, and local dev server WebSocket connections.
- **Secure Token Management (Memory-Only)**:
  - Implemented [TokenManager.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/auth/TokenManager.ts) to store `access_token` and `id_token` strictly in RAM (cleared on tab reload or tab close).
  - Modified Axios/Fetch client [client.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/api/client.ts) to read from `TokenManager` and globally intercept `401 Unauthorized` responses to trigger redirects.
  - Refactored [AuthContext.tsx](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/auth/AuthContext.tsx) to get/set tokens using `TokenManager` instead of `localStorage`.
- **XSS Prevention (DOMPurify)**:
  - Installed `dompurify` and `@types/dompurify` for frontend sanitization.
  - Created [DomSanitizer.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/utils/DomSanitizer.ts) with `sanitizeHtml` (tag whitelisting) and `sanitizeUserInput` (HTML striping).
  - Created [FormFields.tsx](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/components/FormFields.tsx) introducing `<SanitizedInput>` and `<SafeHtmlContent>` components.
- **CORS & Preflight Interception**:
  - Removed standard Laravel `HandleCors` middleware in [app.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/bootstrap/app.php).
  - Handled CORS whitelist origins checking dynamically in [SecurityHeaders.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/app/Http/Middleware/SecurityHeaders.php) and handled preflight `OPTIONS` requests returning 204.
- **Automated Verification**:
  - Installed `vitest` and `jsdom` inside the `web` folder.
  - Added unit tests [TokenManager.test.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/__tests__/security/TokenManager.test.ts) and [DomSanitizer.test.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/__tests__/security/DomSanitizer.test.ts).
  - Updated backend integration tests [SecurityHeadersTest.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/tests/Feature/SecurityHeadersTest.php) and [AuthMiddlewareTest.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/tests/Feature/AuthMiddlewareTest.php).

## New/updated files in Etapa 7

### Frontend code
- `web/index.html` (updated)
- `web/package.json` (updated with `vitest` and `jsdom`)
- `web/src/auth/TokenManager.ts` (new)
- `web/src/api/client.ts` (updated)
- `web/src/auth/AuthContext.tsx` (updated)
- `web/src/utils/DomSanitizer.ts` (new)
- `web/src/components/FormFields.tsx` (new)

### Tests
- `web/src/__tests__/security/TokenManager.test.ts` (new)
- `web/src/__tests__/security/DomSanitizer.test.ts` (new)
- `backend/tests/Feature/SecurityHeadersTest.php` (updated)
- `backend/tests/Feature/AuthMiddlewareTest.php` (updated)

### Documentation
- `docs/FRONTEND_SECURITY.md` (new)
- `docs/README.md` (updated index)

---

## Validation checklist for next run

### 1. Frontend tests
```bash
cd web
npx vitest run src/__tests__/security
```
Expected: 11 tests passing.

### 2. Backend tests
```bash
cd backend
$env:DB_PORT="5433"; php artisan test
```
Expected: 77 tests passing.

---

## Suggested next stage (Etapa 8)

**Etapa 8: Deployment Hardening & Environment Isolation**
1. Review secret management (e.g. AWS Secrets Manager or secure SSM parameter store for production configs).
2. Configure environment isolation (restrict debug mode and Telescope/Pulse outputs in production).
3. Setup audit logs aggregation (forwarding backend logs and system access trail into AWS CloudWatch or structured logs files).
