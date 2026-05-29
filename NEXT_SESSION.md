# TermoVault - Next Session Roadmap

**Date:** 2026-05-29  
**Branch baseline:** `main`  
**Status:** Etapa 1-5 completed in code branch `feature/etapa-5-security-polish` (pending merge in this session)

## Completed security stages

### Etapa 1-3 (already merged previously)
- Auth identity hardening (exact match, no preferred_username abuse)
- Inspection flow hardening
- Upload rate-limit and MIME controls

### Etapa 4 (already merged previously)
- Medium findings addressed (M1-M6 scope completed)

### Etapa 5 (this session)
- L1 Logging improvements:
  - structured auth failure logs in `EnsureCognitoJwt`
  - structured scope violation logs in `AccessScopeResolver`
  - authorization deny logs in controllers
- L2 Error message consistency:
  - removed implementation-leaking `error` field from auth responses
  - generic server auth unavailability response
- L3 Cache header optimization:
  - API responses: no-store/no-cache policy
  - non-API backend responses: long-lived public cache policy
- L4 Timing attack review:
  - JWT verification path unchanged and safe
  - identity resolution remains exact-match and non-prefix
- L5 Audit trail completeness:
  - added `AuditTrail` service for state-changing operations
  - users, org entities, elementos, inspecciones and novedades operations now log audit events

## New/updated files in Etapa 5

### Backend code
- `backend/app/Services/AuditTrail.php` (new)
- `backend/app/Http/Middleware/EnsureCognitoJwt.php`
- `backend/app/Services/Auth/AccessScopeResolver.php`
- `backend/app/Http/Middleware/SecurityHeaders.php`
- `backend/app/Http/Controllers/AuthController.php`
- `backend/app/Http/Controllers/ElementoController.php`
- `backend/app/Http/Controllers/InspeccionController.php`
- `backend/app/Http/Controllers/AdminUserController.php`
- `backend/app/Http/Controllers/AdminOrganizationController.php`

### Tests
- `backend/tests/Feature/AuthMiddlewareTest.php`
- `backend/tests/Feature/SecurityHeadersTest.php` (new)
- `backend/tests/Feature/AuthTest.php`
- `backend/tests/Feature/ElementoManagementTest.php`
- `backend/tests/Feature/InspeccionManagementTest.php`

### Documentation
- `docs/SECURITY.md` (new)
- `docs/THREAT_MODEL.md` (new)
- `docs/INCIDENT_RESPONSE.md` (new)
- `docs/README.md` (updated index)

## Validation checklist for next run

```bash
cd backend
php artisan test
```

Expected:
- all feature tests passing
- no auth response leaks (`error` field absent)
- cache headers validated by tests

## Suggested next stage (Etapa 6)

1. Performance profiling with realistic dataset:
   - scope-heavy queries
   - download endpoint under load
2. Query optimization pass (indexes + eager loading audit).
3. Frontend hardening follow-up:
   - evaluate migration from localStorage tokens to safer strategy.

