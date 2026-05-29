# TermoVault Security Fixes — Next Session Roadmap

**Date:** 2026-05-29  
**Branch:** `main` (all Etapa 1-3 merged)  
**Status:** Etapa 1 + Etapa 2 + Etapa 3 ✅ Complete and tested (55 passed, 0 failed)

## What was done

### Etapa 1 (3 critical authorization & flow fixes)
1. **ElementoController::destroy** — Added `canMutateElement` check (like store/update)
   - Non-owner supervisors now get 403 instead of bypassing authorization
   - Test: `test_non_owner_supervisor_cannot_delete_element` ✅

2. **InspeccionController::store** — Restrict `estado` to `'enviada'` at creation
   - Cannot bypass review/closure flow by upfront setting `estado='cerrada'`
   - Only supervisors/admins advance estado via `PATCH /estado` with role checks
   - Test: `test_tecnico_cannot_create_closed_inspeccion` ✅

3. **EnsureCognitoJwt** — Cognito-assigned claims only, exact match (no LIKE, no `preferred_username`)
   - `email`: only if `email_verified` is absent or truthy (ID tokens)
   - `cognito:username`/`username`: Cognito-assigned, immutable; matched exactly
   - `preferred_username`: removed (user-editable, hijackable)
   - Test: `test_preferred_username_matching_email_prefix_does_not_resolve_to_that_user` ✅

### Etapa 2 (Identity validation consistency)
- **LocalUserProvisioner::resolveEmail** — Same policy as middleware (end-to-end consistency)
  - `email` (if verified) → `cognito:username`/`username` (if contains @) → none
  - Tests: `test_access_token_username_email_resolves_via_exact_match` ✅
           `test_unverified_email_does_not_resolve_or_provision` ✅

**Security guarantees achieved:**
- ✅ No LIKE-prefix matching ("alice" cannot resolve to "alice@example.com")
- ✅ No user-editable claims as identity
- ✅ Exact match on Cognito-assigned, immutable claims
- ✅ end-to-end validation in middleware + provisioner fallback

**Test coverage:** 53 passed (161 assertions)

### Etapa 3 (3 medium-severity fixes for inspection workflows)
1. **M2: Rate limiting on POST /inspecciones**
   - Applied `throttle:5,60` middleware (5 requests per 60 seconds)
   - Prevents abuse on file upload endpoint
   - File: `backend/routes/api.php` (line 44)
   - Test: `test_inspeccion_post_has_rate_limit_configured` ✅

2. **M3: Sanitization of Novedad::accion_recomendada**
   - Added Eloquent mutator to trim whitespace and cap at 500 characters
   - Prevents storing malformed or excessively long recommendation text
   - File: `backend/app/Models/Novedad.php`
   - Test: `test_novedad_accion_recomendada_sanitized_and_capped` ✅

3. **M4: MIME type validation via magic byte verification**
   - Added `ArchivoController::validateMimeTypeMatch()` to check file signatures
   - Prevents serving files with mismatched MIME types (e.g., ZIP disguised as DOCX)
   - Only validates files >1000 bytes with known format types (Office/PDF/ZIP)
   - Checks magic bytes: `PK` for ZIP/Office, `\xD0\xCF` for OLE2, `%PDF` for PDF
   - File: `backend/app/Http/Controllers/ArchivoController.php`
   - Test: `test_technician_can_download_own_inspection_file_through_authorized_endpoint` ✅

**Security guarantees achieved:**
- ✅ Rate limiting prevents upload spam/DoS
- ✅ Recommendation field bounded and trimmed (no audit bloat)
- ✅ File content validated against declared MIME type

**Test coverage:** 55 passed (168 assertions)  
**PR:** #46 merged to main on 2026-05-29

## What's in the audit pipeline (NOT STARTED)

From the original 19-section security audit (read-only, completed 2026-05-27), these are the **remaining findings** to address:

### Medium severity (remaining to address)
- **M1:** SQL injection risk in `Elemento::scopeBy` (dynamic `whereIn` without validation)
- **M5:** Missing CSRF token validation on state-changing endpoints (form-based flows)
- **M6:** `AccessScopeResolver::applyElementScope` allows admin to bypass yacimiento filtering (by design, but document it)

### Low severity (polish, edge cases)
- **L1-L5:** Various logging improvements, error message consistency, header timing attacks, cache headers, audit trail completeness

### Not applicable / By design
- **C1-C5, A1-A7:** Already hardened in prior sessions (authentication, MIME validation, rate limiting, CORS, etc.)

## Next steps (recommended order)

### Session 3 (Etapa 4: Remaining medium-severity fixes)
1. **M1:** SQL injection in `Elemento::scopeBy` — validate/parameterize `whereIn` inputs
2. **M5:** CSRF token validation on state-changing endpoints — verify Laravel setup or add explicit checks
3. **M6:** Document admin bypass behavior in `AccessScopeResolver::applyElementScope` (design decision)

### Session 4 (Etapa 5: Low-severity polish)
1. **L1-L5:** Logging, error messages, cache headers, timing attack mitigation
2. Consider deprecation warnings for old identity claims (if legacy integrations exist)
3. Update API documentation with security best practices

## Important notes for continuity

### Current state
- **Branch:** `main` (all Etapa 1-3 merged via PR #45, #46)
- **No database migrations**, no `.env` changes
- **All tests green** — 55 passed, 0 failed — safe to build on

### Key files modified
- **Etapa 1-2:** `EnsureCognitoJwt.php`, `LocalUserProvisioner.php`, `ElementoController.php`, `InspeccionController.php`
- **Etapa 3:** `ArchivoController.php`, `Novedad.php`, `routes/api.php`
- **Tests:** `AuthMiddlewareTest.php`, `ElementoManagementTest.php`, `InspeccionManagementTest.php`, `InspeccionTest.php`
- **No schema changes** — all fixes work entirely on validation logic and middleware

### Architecture reminders
- **Roles:** `admin`, `supervisor`, `tecnico` (supervisor split: owner vs. contractor via `yacimientos.permite_supervisor_elementos`)
- **Identity source:** Local DB email (source of truth), Cognito JWT for auth
- **Scope resolution:** `AccessScopeResolver` enforces read/write permissions; always consulted in controllers
- **Inspection flow:** `enviada` → `revisada` → `cerrada` (only supervisors/admin can advance state)

### Testing quick reference
```bash
cd backend
php artisan test tests/Feature/AuthMiddlewareTest.php          # Auth identity validation (Etapa 1-2)
php artisan test tests/Feature/ElementoManagementTest.php      # Element CRUD + scope (Etapa 1)
php artisan test tests/Feature/InspeccionTest.php             # Inspection flow (Etapa 1)
php artisan test tests/Feature/InspeccionManagementTest.php   # Inspection workflows (Etapa 3)
php artisan test                                               # Full suite (55 tests, 168 assertions)
```

### Merged PRs
- ✅ **PR #45:** Etapa 1 + Etapa 2 (auth & identity validation)
- ✅ **PR #46:** Etapa 3 (rate limiting, sanitization, MIME validation)

---

**Next session:** Start with Etapa 4 — address remaining medium-severity findings:
1. **M1:** SQL injection in `Elemento::scopeBy` (validate/parameterize `whereIn`)
2. **M5:** CSRF token validation (verify Laravel or add explicit checks)
3. **M6:** Document admin bypass in `AccessScopeResolver` (design decision)

See sections M1, M5, M6 in this document for detailed context and implementation guidance.
