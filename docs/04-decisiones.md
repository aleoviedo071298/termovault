# Decisiones de arquitectura (ADRs) — TermoVault

Listado de las decisiones de diseño tomadas hasta hoy. Cada una incluye contexto, decisión y consecuencias. Si una decisión se reemplaza, se mantiene la entrada original y se añade la nueva con referencia.

---

## ADR-001 — Multi-tenant por columna `empresa_id`

### Contexto

TermoVault se vende como SaaS interno: una empresa cliente (PECOM hoy, otras después) gestiona inspecciones de sus técnicos y sus contratistas en uno o más yacimientos. Hay que poder aislar datos entre clientes sin operar N bases separadas.

### Decisión

Una única base PostgreSQL con columna `empresa_id` en todas las entidades top-level (`usuarios`, `yacimientos`). Las entidades dependientes (`elementos`, `inspecciones`, etc.) heredan el scope vía joins.

Cada query se filtra por `empresa_id` en el `AccessScopeResolver`. Para admin no se aplica filtro.

### Consecuencias

- ✅ Operacionalmente más simple (un backup, un schema, un upgrade).
- ✅ Suficiente para el volumen esperado.
- ⚠️ Riesgo de fuga entre tenants si una query se olvida del filtro: mitigado al concentrar el scope en `AccessScopeResolver` y exigir que pase por ahí.
- 🔄 Si crece a decenas de tenants con cargas dispares: se evalúa schema-per-tenant o RLS de Postgres.

---

## ADR-002 — Auth con AWS Cognito como SSO

### Contexto

Necesitamos un sistema de login con roles, MFA, password policies y eventualmente SSO con cuentas corporativas. Implementar y mantener auth propia (hashes, reset, throttling, 2FA) es costoso.

### Decisión

- AWS Cognito User Pool como **única** fuente de identidad.
- Backend Laravel **no** almacena contraseñas.
- El JWT de Cognito viaja en `Authorization: Bearer …` y se verifica contra JWKS (cacheado 6h).
- Los roles se asignan via `cognito:groups` o `custom:role` claim.
- El backend mantiene una tabla local `usuarios` con metadatos (empresa, rol, yacimientos asignados), provisionada en el primer login (`LocalUserProvisioner`).

### Consecuencias

- ✅ Sin password leaks posibles del lado del backend.
- ✅ MFA, reset flow y challenges manejados por Cognito.
- ✅ Posibilidad de federar con AD/SSO corporativo a futuro.
- ⚠️ Acoplamiento a AWS.
- La tabla local `usuarios` no almacena contrasenas; Cognito es la autoridad de identidad.
- ⚠️ Para desarrollo local se puede deshabilitar (`COGNITO_AUTH_REQUIRED=false`), pero entonces el endpoint queda público. **Nunca dejar `false` en producción.**

---

## ADR-003 — Una sola tabla `elementos` con tipo + función

### Contexto

El cliente clasifica sus equipos en varias dimensiones:

- **Tipo**: subestación, ETR, banco de capacitores, seccionador, reconectador.
- **Función** (solo dentro de subestaciones): SET, PIAS, PC, PTC, PB_PTC, RCI.

Modelar cada tipo como tabla propia generaría 5 tablas casi idénticas y un código fragmentado.

### Decisión

Una única tabla `elementos` con:
- `tipo_elemento_id` apuntando al catálogo de tipos.
- `funcion` como string opcional para el sub-tipo de subestación.

### Consecuencias

- ✅ Listado, búsqueda y permisos uniformes.
- ✅ Agregar un tipo nuevo es `INSERT` en `tipos_elemento`, no migración.
- ⚠️ Campos específicos de un tipo (si los hubiera) tendrían que vivir en `observaciones_generales` o en una tabla `elemento_atributos` futura.

---

## ADR-004 — Archivos en S3/MinIO, no en DB

### Contexto

Cada inspección produce un Word (~5 MB) y un ZIP de imágenes (~30–60 MB). Almacenarlos en Postgres como `bytea` infla la DB y complica los backups.

### Decisión

- La tabla `archivos` guarda solo metadata: `s3_bucket`, `s3_key`, `tamano_bytes`, `mime_type`, `nombre_original`.
- El binario va a S3 (producción) o MinIO (local).
- Naming convention de keys: `inspecciones/{inspeccion_id}/{reports|images}/{archivo_id}-{nombre-original}`.
- Las descargas pasan por `GET /api/archivos/{id}/download`, con JWT y scope por rol antes de transmitir el archivo desde S3/MinIO.

### Consecuencias

- ✅ DB liviana, backups rápidos.
- ✅ Storage horizontalmente escalable.
- ⚠️ Hay que sincronizar la limpieza: si se borra una inspección, deben limpiarse los archivos de S3 (hoy es `cascadeOnDelete` en DB pero los blobs quedan).
- 🔄 En el roadmap: `php artisan inspecciones:cleanup-orphan-files` periódico.

---

## ADR-005 - DB actual como fuente de verdad operativa

### Contexto

El repo arrancó con un `database/schema.sql` curado a mano. Cuando Laravel entró en escena, se sumaron migraciones que dropean/agregan columnas. Resultado: dos fuentes de verdad desincronizadas.

### Decision

- La estructura actual de Postgres es la fuente de verdad operativa para auditorias y limpiezas.
- `database/` (raiz) es la referencia operativa real; `backend/database/migrations` queda como historial tecnico de Laravel.
- `database/schema.sql` queda como snapshot generado para arranque local y debe regenerarse desde la DB, no editarse a mano.
- En produccion se aplican migraciones con respaldo previo; nunca se pisa una DB real con `schema.sql`.

### Consecuencias

- ✅ Se evita ambiguedad operativa: decisiones de DB se validan contra `database/` (raiz) y DB real.
- ✅ `artisan migrate:rollback` funciona para revertir.
- ⚠️ El CI workflow `validate-schema.yml` todavía usa `schema.sql` — actualizar.
- ⚠️ El `docker-compose.yml` arranca con `postgres-init.sh` que aún aplica `schema.sql` — actualizar.

---

## ADR-006 — Audit fields en `inspecciones`

### Contexto

Las inspecciones pasan por varios actores: técnico que las carga, supervisor que las revisa, admin que las cierra. Antes solo se guardaba `tecnico_id` + `revisada_por`. Faltaba trazabilidad sobre quién cerró formalmente y cuándo.

### Decisión

Agregar columnas:
- `created_by` (FK `usuarios`, nullable)
- `updated_by` (FK `usuarios`, nullable)
- `cerrada_por` (FK `usuarios`, nullable)
- `fecha_cierre` (timestamp, nullable)

Backfillear con la mejor aproximación disponible:
- `created_by` = `tecnico_id` cuando es null
- `updated_by` = `revisada_por` ?? `created_by`
- `cerrada_por` = `revisada_por` para inspecciones ya cerradas
- `fecha_cierre` = `fecha_revision` para inspecciones ya cerradas

Migraciones `2026_05_27_000007` y `2026_05_27_000010`.

### Consecuencias

- ✅ Cualquier inspección cerrada hoy tiene trazabilidad mínima.
- ✅ Cambios futuros heredan el patrón completo.
- ⚠️ El backfill es heurístico: para registros antiguos el `cerrada_por` puede no coincidir con quien realmente cerró.

---

## ADR-007 — Catálogo de tipos con `prefijo_archivo`

### Contexto

Cuando un técnico sube un Word `SET ZR2.doc`, idealmente el sistema sugiere automáticamente vincularlo al elemento `SET-ZR2`.

### Decisión

Agregar columna `prefijo_archivo` en `tipos_elemento` con el prefijo común (`SET`, `ETR`, `Bco Cap`, `SEC`, `REC`). El parser del nombre de archivo identifica el tipo por prefijo, luego intenta matchear el código del elemento.

### Consecuencias

- ✅ UX más fluida al cargar bulk de archivos.
- ⚠️ Falla si el técnico renombra el archivo. Es asistencial, no autoritativo.

---

## ADR-008 — `borrador` retirado del enum de estados

### Contexto

Originalmente las inspecciones podían quedar en `borrador` mientras el técnico armaba el informe. En la práctica nadie usaba ese estado: o se guardaba directo en `enviada` o no se guardaba.

### Decisión

Migración `2026_05_27_000001` actualiza todos los `borrador` existentes a `enviada` y cambia el default. CHECK constraint `chk_inspecciones_estado` solo permite `enviada`, `revisada`, `cerrada`.

### Consecuencias

- ✅ Flujo más claro: si está en DB, es porque el técnico la envió.
- ⚠️ Si en el futuro se quiere "guardar como borrador" del lado cliente, hay que reintroducir el estado (modificar el CHECK).

---

## ADR-009 — Supervisor PAE vs supervisor contratista

### Contexto

Hay dos clases de "supervisor":

- **Supervisor de empresa contratista** (e.g. supervisor de PECOM): solo ve los informes de su empresa, no puede revisar/cerrar ni administrar elementos.
- **Supervisor PAE**: la empresa cliente (PAE) tiene supervisores que sí necesitan revisar/cerrar y administrar elementos del yacimiento que les corresponde.

Modelar esto como dos roles distintos hubiera duplicado lógica.

### Decisión

Un único rol `supervisor`. El "tipo" se deriva en runtime:

```
is_pae_supervisor = (es supervisor)
                  ∧ (empresa del usuario == "PAE" o contiene "PAE")
                  ∧ (tiene "YAC-PAE" entre sus yacimientos asignados)
```

Las acciones sensibles (`updateEstado`, `canMutateElement`) chequean ese flag.

### Consecuencias

- ✅ Sin proliferación de roles.
- ⚠️ La heurística depende del nombre de empresa y código de yacimiento. Si PAE cambia de nombre o se agrega otra subsidiaria, hay que ajustar.
- 🔄 Si el caso se generaliza (múltiples "operadoras dueñas"), conviene una columna explícita `es_operadora=true` en `empresas`.

---

## ADR-010 — Hard-delete en `DELETE /api/elementos/{id}`

### Contexto

Si se borra un elemento, ¿qué pasa con sus inspecciones históricas? Las inspecciones tienen `elemento_id` con `restrictOnDelete`, pero el controller las elimina explícitamente antes de borrar el elemento.

### Decisión actual (a revisar)

`ElementoController::destroy` hace `$elemento->inspecciones()->delete()` y luego `$elemento->delete()`.

### Consecuencias

- ⚠️ **Borra evidencia histórica.** Crítico en industrias reguladas.
- 🔄 **En el roadmap (alta prioridad)**: cambiar a soft-delete (`activo=false`) o bloquear si hay inspecciones cerradas.

---

## ADR-011 — Empresa contratista como snapshot, no FK

### Contexto

Una inspección la realiza un técnico de una empresa contratista (PECOM, etc.). Si en el futuro la empresa cambia de nombre, el informe histórico debe mantener el nombre de cuando se hizo.

### Decisión

`inspecciones.empresa_contratista` es un `VARCHAR(150)` (snapshot), no una FK a `empresas`. Por default se autocompleta con el nombre actual de la empresa del usuario al momento de crear la inspección.

### Consecuencias

- ✅ Historial inmutable.
- ⚠️ No se puede filtrar por empresa contratista con joins; hay que comparar strings. Aceptable porque el dashboard ya hace filtros por `usuarios.empresa_id`.
