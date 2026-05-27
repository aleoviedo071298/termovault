# TermoVault - Next Session (2026-05-27)

Lee primero:
1. `README.md`
2. `backend/routes/api.php`
3. `web/src/pages/Dashboard.tsx`

## Estado operativo actual

- Auth Cognito JWT + provision local de usuario (`LocalUserProvisioner`).
- Seguridad por rol validada en backend (`AccessScopeResolver`).
- Dashboards por rol: `admin`, `supervisor`, `tecnico`.
- Flujo de informe: `enviada -> revisada -> cerrada`.
- Cierre de informe resuelve novedades abiertas (`abierta -> resuelta`).
- Trazabilidad de auditoria en inspecciones:
  - `created_by`, `updated_by`
  - `revisada_por`, `fecha_revision`
  - `cerrada_por`, `fecha_cierre`
- Usuario inactivo queda bloqueado al ingresar (403 controlado).

## Reglas funcionales clave

- Admin: alcance global.
- Tecnico: solo sus informes/alcance.
- Supervisor contratista: solo informes de su empresa en yacimientos asignados.
- Supervisor PAE: alcance por yacimiento asignado (ej. `YAC-PAE`), puede revisar/cerrar y gestionar elementos de ese yacimiento.

## Convenciones de criticidad

- DB: `Baja`, `Media`, `Alta`, `Crítica`.
- Dashboard:
  - sin hallazgos: `Normal`
  - con hallazgos: según máximo nivel detectado.

## Validaciones rápidas al retomar

```bash
git status
cd backend && php artisan test
cd ../web && npm run build
```

## Nota de mantenimiento

- Las migraciones nuevas de 2026-05-27 forman parte del estado final actual.
- No borrar migraciones sin consolidar esquema primero, o se rompe `migrate:fresh`/CI.

