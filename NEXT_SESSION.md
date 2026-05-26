# TermoVault - Prompt de continuidad

Lee primero `README.md` completo y sincronizate con el estado real del repo.

## Objetivo general

Continuar evolucionando la plataforma sin romper seguridad por rol ni flujo actual operativo.

## Estado funcional ya implementado

- Auth Cognito JWT + mapeo usuario local.
- Dashboards por rol (`admin`, `supervisor`, `tecnico`).
- Flujo de informe:
  - `enviada` -> `revisada` -> `cerrada`
- Alcance por rol en backend:
  - Admin: global.
  - Tecnico: propio.
  - Supervisor contratista: su empresa.
  - Supervisor PAE: yacimiento asignado + gestion de elementos.

## Reglas no negociables

1. No inventar estructura nueva si ya existe.
2. Seguridad y filtros siempre en backend.
3. No exponer por URL datos fuera de alcance.
4. UI limpia: acciones principales visibles, listados largos fuera del dashboard.

## Checklist de arranque

1. `git status`
2. `git log --oneline -n 15`
3. `cd web && npm run build`
4. `cd backend && php artisan test`
5. Revisar rutas:
   - `backend/routes/api.php`
   - `web/src/App.tsx`

## Si hay que tocar permisos

- Revisar primero:
  - `backend/app/Services/Auth/AccessScopeResolver.php`
  - `backend/app/Http/Middleware/EnsureRoleFromClaims.php`
  - controladores de dashboard/inspecciones/elementos/admin.

## Entrega esperada en cada iteracion

- Resumen corto de analisis.
- Cambios implementados.
- Validacion (`build/tests`).
- Lista de archivos modificados y por que.
