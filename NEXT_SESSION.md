# TermoVault Security Fixes — Next Session Roadmap

**Date:** 2026-05-29  
**Branch:** `feature/security-etapa1-etapa2`  
**Status:** Etapa 1 + Etapa 2 ✅ Complete and tested (53 passed)

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

## What's in the audit pipeline (NOT STARTED)

From the original 19-section security audit (read-only, completed 2026-05-27), these are the **remaining findings** to address:

### Medium severity (consider next)
- **M1:** SQL injection risk in `Elemento::scopeBy` (dynamic `whereIn` without validation)
- **M2:** Missing rate limiting on `/api/inspecciones/` POST (file upload endpoint)
- **M3:** No input sanitization for `Novedad::accion_recomendada` (free text, audit trail)
- **M4:** `ArchivoController::download` should validate `mime_type` against actual file content
- **M5:** Missing CSRF token validation on state-changing endpoints (form-based flows)
- **M6:** `AccessScopeResolver::applyElementScope` allows admin to bypass yacimiento filtering (by design, but document it)

### Low severity (polish, edge cases)
- **L1-L5:** Various logging improvements, error message consistency, header timing attacks, cache headers, audit trail completeness

### Not applicable / By design
- **C1-C5, A1-A7:** Already hardened in prior sessions (authentication, MIME validation, rate limiting, CORS, etc.)

## Next steps (recommended order)

### Session 2 (Etapa 3: Medium-severity flow fixes)
1. **M2 + M3:** Rate limit `/api/inspecciones` POST; sanitize `accion_recomendada` text
2. **M4:** Add file content validation (`mime_type` vs. actual bytes) in `ArchivoController::download`
3. **M5:** Document CSRF token handling (may already be in place via Laravel; verify)

### Session 3 (Etapa 4: Edge cases & consistency)
1. **M1:** Validate `Elemento::scopeBy` inputs (or rewrite to parameterized query)
2. **L1-L5:** Logging, error messages, cache headers, timing attack mitigation

## Important notes for continuity

### Current state
- **Branch:** `feature/security-etapa1-etapa2` (not yet merged to main)
- **No database migrations**, no `.env` changes
- **All tests green** — safe to build on

### Key files modified
- Backend auth/controllers: `EnsureCognitoJwt.php`, `LocalUserProvisioner.php`, `ElementoController.php`, `InspeccionController.php`
- Tests: `AuthMiddlewareTest.php`, `ElementoManagementTest.php`, `InspeccionTest.php`
- **No schema changes** — the 3 fixes work entirely on validation logic

### Architecture reminders
- **Roles:** `admin`, `supervisor`, `tecnico` (supervisor split: owner vs. contractor via `yacimientos.permite_supervisor_elementos`)
- **Identity source:** Local DB email (source of truth), Cognito JWT for auth
- **Scope resolution:** `AccessScopeResolver` enforces read/write permissions; always consulted in controllers
- **Inspection flow:** `enviada` → `revisada` → `cerrada` (only supervisors/admin can advance state)

### Testing quick reference
```bash
cd backend
php artisan test tests/Feature/AuthMiddlewareTest.php          # Auth identity validation
php artisan test tests/Feature/ElementoManagementTest.php      # Element CRUD + scope
php artisan test tests/Feature/InspeccionTest.php             # Inspection flow
php artisan test                                               # Full suite (53 tests)
```

### When ready to merge
- Create PR from `feature/security-etapa1-etapa2` → `main`
- Verify CI/CD pipeline passes
- Merge only after code review

---

**Next session:** Start with reading the audit findings in `README.md` (sections M1-M5, L1-L5) and decide which Etapa 3 fixes to prioritize.
