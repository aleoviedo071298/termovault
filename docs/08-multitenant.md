# Multi-tenant — TermoVault

> Cómo TermoVault aísla datos entre empresas, qué garantías ofrece y dónde están los límites.

## Modelo

TermoVault es una aplicación **shared-database, shared-schema** con discriminador por `empresa_id`. Es la opción más simple del espectro multi-tenant; ver alternativas más abajo.

### Jerarquía de propiedad

```
Empresa (tenant primario)
  ├── Usuarios   (FK empresa_id NOT NULL)
  ├── Yacimientos (FK empresa_id NOT NULL)
  │     └── Elementos
  │           └── Inspecciones
  │                 ├── Archivos
  │                 └── Novedades
  └── usuario_yacimientos (asignación N:M)
```

- Cada empresa tiene sus **propios** usuarios y yacimientos.
- Los elementos, inspecciones, archivos y novedades quedan implícitamente bajo una empresa a través de la cadena `elemento → yacimiento → empresa`.
- Una inspección guarda además `empresa_contratista` como snapshot (texto) — esto modela que **la empresa del técnico que inspecciona** puede no ser la dueña del yacimiento (caso típico: PECOM inspeccionando equipos de PAE).

### Dos identidades de empresa por inspección

Es importante distinguir:

| Concepto | Dónde vive | Para qué |
|---|---|---|
| **Empresa dueña del yacimiento** | `yacimientos.empresa_id → empresas.id` | Aísla el tenant. Es PAE en nuestro caso. |
| **Empresa contratista** | `usuarios.empresa_id` del técnico, o snapshot en `inspecciones.empresa_contratista` | Quién hace el trabajo. Puede ser PECOM, SES, etc. |

Esto permite que **un técnico de PECOM inspeccione un elemento de PAE**, y la inspección quede correctamente:

- Visible para Admin (ve todo).
- Visible para el técnico (es suya).
- Visible para el supervisor PAE (es del yacimiento que supervisa).
- Visible para el supervisor de PECOM (es de un técnico de su empresa).

---

## Scope por rol

Implementado en `App\Services\Auth\AccessScopeResolver::resolve()`.

### Admin

- `is_admin = true`
- Sin filtros. Todas las queries devuelven datos globales.

### Técnico

- `is_tecnico = true`
- Solo ve sus propias inspecciones: `inspecciones.tecnico_id = $user_id`.
- Solo puede crear inspecciones para elementos cuyo yacimiento esté en `usuario_yacimientos` para él.
- Si no tiene yacimientos asignados, queda **sin acceso operativo** (queries devuelven 0 filas).

### Supervisor contratista

Definición: `is_supervisor = true ∧ NOT is_pae_supervisor`.

- Ve inspecciones donde el técnico es de su misma empresa (`usuarios.empresa_id = $user_empresa_id`).
- Ve elementos del yacimiento asignado.
- **No puede revisar/cerrar** inspecciones.
- **No puede mutar elementos**.

### Supervisor PAE

Definición:
```
is_pae_supervisor = is_supervisor
                  ∧ empresa_nombre coincide con "PAE" (igual o contiene)
                  ∧ "YAC-PAE" ∈ assigned_yacimiento_codes
```

- Ve TODAS las inspecciones del yacimiento PAE (de cualquier contratista).
- Puede revisar/cerrar inspecciones.
- Puede crear/editar/borrar elementos del yacimiento PAE.

> Esta heurística está acoplada a los strings "PAE" y "YAC-PAE". Si en el futuro hay múltiples empresas-dueñas-de-yacimiento, conviene reemplazarla por una columna explícita `empresas.es_operadora` y `yacimientos.es_propio` (ver ADR-009).

---

## Aplicación del scope

### Backend

Toda query que toca datos de tenants pasa por `AccessScopeResolver`:

```php
$scope = $this->scopeResolver->resolve($request);

// Para elementos
$query = Elemento::query();
$query = $this->scopeResolver->applyElementScope($query, $scope);
// → si admin: sin filtro
// → si scope con yacimientos: WHERE yacimiento_id IN (...)
// → si scope con solo empresa: WHERE yacimiento_id IN (SELECT id FROM yacimientos WHERE empresa_id = ...)
// → sin nada: WHERE 1=0  (devuelve 0 filas)
```

Para inspecciones y dashboard se construye un join con filtros equivalentes en `DashboardController::applyInspectionScope` y `InspeccionController::show`.

Para mutaciones existen helpers explícitos:

- `canMutateElement($scope, $yacimientoId)` → solo admin o supervisor PAE asignado al yacimiento.
- `canCreateInspectionForElement($scope, $elementId)` → admin, técnico o supervisor con el elemento en scope.

### Frontend

El frontend respeta el scope **a nivel UX** (oculta botones, hide pages):

- `ProtectedRoute` chequea grupos del usuario.
- Dashboard muestra distintos KPIs según `role`.
- `ElementosGestion` y `AdminUsuariosPage` chequean `isAdmin` / `isPaeSupervisor` para mostrar acciones.

> Sin embargo, **el backend es la fuente de verdad**. Aunque el frontend muestre un botón que no debería, la API responde 403/404. Es defensa en profundidad.

---

## Casos de prueba canónicos

### Caso 1 — Técnico de PECOM cargando inspección de elemento PAE

- Usuario: Alejandro (`tecnico`, empresa PECOM, yacimiento asignado YAC-PAE).
- Acción: crea inspección sobre `SET-AGR` (yacimiento PAE).
- Esperado: ✅ se crea con `tecnico_id=alejandro`, `empresa_contratista="PECOM S.A."` (snapshot).

### Caso 2 — Supervisor de PECOM intentando cerrar inspección

- Usuario: supervisor PECOM, sin asignación a PAE.
- Acción: PATCH `/api/inspecciones/123/estado` con `cerrada`.
- Esperado: ❌ 403 "No tenes permisos para revisar o cerrar informes".

### Caso 3 — Supervisor PAE cerrando inspección de un técnico de PECOM

- Usuario: Axel Elgueta (`supervisor`, empresa PAE, yacimientos: PAE).
- Acción: PATCH `/api/inspecciones/123/estado` con `cerrada`.
- Esperado: ✅ se cierra, novedades abiertas pasan a resueltas.

### Caso 4 — Técnico viendo dashboard

- Usuario: Marijo (`tecnico`).
- Acción: GET `/api/dashboard/overview`.
- Esperado: stats reflejan SOLO sus inspecciones (no las del resto).

### Caso 5 — Cruce entre tenants

- Empresa A tiene su yacimiento YAC-A; Empresa B tiene YAC-B.
- Técnico de Empresa B intenta GET `/api/elementos/{id}` con id de elemento de YAC-A.
- Esperado: ❌ 404 "Elemento no encontrado" (no fuga el id como existente).

---

## Onboarding de un nuevo cliente

Pasos para incorporar una nueva empresa como tenant:

### 1. En el backend (Admin)

```
POST /api/admin/empresas
{ "nombre": "Nueva Operadora SA" }
```

```
POST /api/admin/yacimientos
{ "empresa_id": 5, "nombre": "Yacimiento Sur", "codigo": "YAC-SUR" }
```

### 2. En Cognito

- Crear el usuario administrador del cliente.
- Asignarlo al grupo `admin`.
- (Opcional) custom attribute `custom:empresa_id=5`.

### 3. Login del admin del cliente

- Hace primer login → `LocalUserProvisioner` crea su fila en `usuarios` con `empresa_id=5` y `rol=admin`.
- Asignarle yacimientos vía Admin Usuarios si hace falta restringirlo.

### 4. Carga inicial

- Admin crea sus supervisores y técnicos en `Admin Usuarios`.
- Los empareja con Cognito por email (LocalUserProvisioner cierra el loop en el primer login).
- Admin carga elementos del yacimiento (directo en UI o vía un import script si tiene un Excel).

---

## Garantías de aislamiento

### Lo que SÍ garantiza el modelo actual

- ✅ **A nivel de query**: si pasa por `AccessScopeResolver`, no devuelve filas de otro tenant.
- ✅ **A nivel de UNIQUE**: dos empresas pueden tener un yacimiento con el mismo `codigo` (`UNIQUE(empresa_id, codigo)`).
- ✅ **A nivel de FK**: cada cadena `inspección → elemento → yacimiento → empresa` cierra correctamente.

### Lo que NO garantiza

- ❌ **Resource isolation**: si un tenant hace una query muy pesada, afecta a todos (es la misma DB y mismo backend).
- ❌ **Volumetría**: no hay límites por tenant (storage, requests/min). Si un cliente abusa, no hay cuota.
- ❌ **Compliance fuerte**: GDPR/HIPAA requieren a veces aislamiento físico. Este modelo no lo da.
- ❌ **Row-level security en Postgres**: las queries todavía dependen de que el código del backend las filtre. **Si una nueva query se olvida del scope, se filtra data entre tenants.**

---

## Roadmap multi-tenant

Si el negocio crece más allá de un par de clientes:

### Fase 1 — Endurecer el modelo actual

- [ ] Habilitar **Row-Level Security** de Postgres con `current_setting('app.empresa_id')` seteado por el conector Laravel en cada request. Red de seguridad contra olvidos del scope en código.
- [ ] **Tests automáticos** que validen aislamiento cross-tenant (Caso 5).
- [ ] **Auditoría**: tabla `auditoria_eventos` con todos los reads/writes sensibles.

### Fase 2 — Por-cliente cuando volumen lo justifique

- [ ] **Schema-per-tenant**: cada empresa en su propio schema dentro de la misma DB. Más aislamiento, mismo costo operativo.
- [ ] **Database-per-tenant** para tenants muy grandes o regulados.

### Fase 3 — Cross-tenant features (admin SaaS)

- [ ] Vista de "super-admin" con métricas agregadas (cuánto usa cada tenant, billing).
- [ ] Self-service para que el admin de un tenant invite usuarios sin pasar por el admin global.
- [ ] Branding personalizado por tenant (logo, colores).

---

## Notas operativas

- **Backup**: un único `pg_dump` de la DB cubre todos los tenants. Para restore selectivo de un tenant hace falta `pg_restore --data-only` + filtros — no trivial. Si esto es requerimiento, evaluar schema-per-tenant.
- **Migración de un cliente a su propia infra**: posible pero costosa. Hay que extraer todas sus filas con joins en cascada (`empresa.id=X`) y reinsertar en otra DB. Mejor diseñar la salida desde el día 1 si se prevé.
- **Borrar un tenant**: `DELETE FROM empresas WHERE id=X` no funciona por las FK `restrictOnDelete`. Hay que borrar en orden: archivos S3 + novedades + archivos + inspecciones + elementos + yacimientos + usuario_yacimientos + usuarios + empresa. Considerar un comando `php artisan tenant:purge {empresa_id}` cuando haga falta.
