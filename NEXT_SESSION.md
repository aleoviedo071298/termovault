# TermoVault - Next Session (2026-05-28)

Lee primero:
1. `README.md`
2. `backend/routes/api.php`
3. `web/src/pages/Dashboard.tsx`
4. `web/src/pages/ElementosGestion.tsx`
5. `web/src/pages/AdminUsuariosPage.tsx`

## Estado operativo actual

- Auth Cognito JWT + provision local de usuario (`LocalUserProvisioner`).
- Seguridad por rol validada en backend (`AccessScopeResolver`).
- Storage local migrado a MinIO/S3 usando bucket `termovault-dev`.
- Archivos historicos migrados a keys legibles:
  - `inspecciones/{inspeccion_id}/reports/{archivo_id}-{nombre-original}`
  - `inspecciones/{inspeccion_id}/images/{archivo_id}-{nombre-original}`
- La API devuelve `download_url` por archivo; el frontend descarga por endpoint autorizado y ya no construye `/storage/...`.
- Dashboard, gestion de elementos, admin usuarios y login redisenados con UI industrial SaaS premium.
- Flujo de informe: `enviada -> revisada -> cerrada`.
- Cierre de informe resuelve novedades abiertas (`abierta -> resuelta`).
- Trazabilidad de auditoria en inspecciones:
  - `created_by`, `updated_by`
  - `revisada_por`, `fecha_revision`
  - `cerrada_por`, `fecha_cierre`
- Usuario inactivo queda bloqueado al ingresar.

## Reglas funcionales clave

- Admin: alcance global.
- Tecnico: solo sus informes/alcance.
- Supervisor contratista: solo informes de su empresa en yacimientos asignados.
- Supervisor PAE: alcance por yacimiento asignado, puede revisar/cerrar y gestionar elementos del yacimiento.
- Caso Axel Elgueta validado:
  - `aelgueta@pae-energy.com`
  - empresa usuario: `PAE`
  - yacimiento asignado: `PAE`
  - empresa del yacimiento: `PAE`

## UX/UI actual

- Dashboard:
  - hero enterprise por rol
  - KPIs compactos
  - filtro por criticidad
  - tabla operacional con badges y acciones discretas
- Elementos:
  - pantalla de inventario tecnico
  - filtro por tipo
  - criticidad removida de la tabla, queda solo como extra en detalle/formulario
- Admin usuarios:
  - consola de identidad y organizacion
  - metricas de usuarios, activos, supervisores y empresas
  - paneles para alta rapida, empresas y yacimientos
- Login:
  - redisenado como acceso industrial premium
  - errores de Cognito normalizados al espanol
  - primer ingreso muestra panel separado para nueva contrasena

## Convenciones de criticidad

- DB: `Baja`, `Media`, `Alta`, `Critica`.
- Dashboard:
  - sin hallazgos: `Normal`
  - con hallazgos: segun maximo nivel detectado.

## Validaciones rapidas al retomar

```bash
git status
cd backend && php artisan test
cd ../web && npm run build
```

## Servicios locales

```bash
docker compose up -d
cd backend && php artisan serve --host=127.0.0.1 --port=8000
cd web && npm run dev -- --host 127.0.0.1 --port 5173
```

- Frontend: `http://127.0.0.1:5173`
- Backend: `http://127.0.0.1:8000/api`
- MinIO Console: `http://localhost:9001`
- Adminer: `http://localhost:8080`

## Nota de mantenimiento

- No volver a guardar archivos en el disco `public` para inspecciones; usar `s3`.
- Si se resetea la DB, `database/seed-empresas.sql` ya crea `PAE` y asocia `YAC-PAE` a `PAE`.
- La advertencia local `Module "openssl" is already loaded` viene del PHP local y no bloquea tests.
