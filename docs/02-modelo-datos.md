# Modelo de Datos — TermoVault

> Versión 3 — Post hardening y migraciones de cleanup (2026-05-27).

## Fuente de verdad

Para cualquier decision relacionada con datos, primero se mira la estructura actual de Postgres y sus datos. Las migraciones de Laravel (`backend/database/migrations/`) explican la evolucion del esquema. `database/schema.sql` es un snapshot generado desde la DB local actual para Docker; no editar a mano ni usar como autoridad si contradice a la DB real.

## Jerarquía

```
Empresa (tenant)
  └── Yacimiento
        └── Elemento
              ├─ tipo: subestación  → función: SET | PIAS | PC | PTC | PB_PTC | RCI
              ├─ tipo: estación transformadora (ETR)
              ├─ tipo: banco de capacitores
              ├─ tipo: seccionador
              └─ tipo: reconectador
                    └── Inspección (estado: enviada | revisada | cerrada)
                          ├── Archivos (Word/Excel + ZIP de imágenes en S3/MinIO)
                          └── Novedades (hallazgos con criticidad y estado)
```

## Decisiones clave

1. **Multi-tenant por columna `empresa_id`**. Simple y suficiente para el tamaño esperado. Cada query se filtra por scope en `AccessScopeResolver`.
2. **Una sola tabla `elementos`** con `tipo_elemento_id` y `funcion`. Subestaciones SET, PIAS, PC, PTC, PB_PTC y RCI viven juntas, diferenciadas por `funcion`. Tipos top-level (ETR, banco, seccionador, reconectador) usan `tipo_elemento_id`.
3. **Niveles de tensión** como catálogo: 6.6 / 13.2 / 33 / 132 kV. Nullable — los Words actuales no siempre identifican una única tensión por elemento.
4. **Archivos fuera de la DB**: solo se guarda `s3_bucket + s3_key + mime_type + tamano_bytes`. El binario va a S3/MinIO bajo `inspecciones/{inspeccion_id}/{reports|images}/{archivo_id}-{nombre-original}`.
5. **Word como fuente de verdad** de los puntos internos del elemento. La DB no modela campos/bushings individuales — las novedades referencian la ubicación interna como texto libre (`ubicacion_dentro_elemento`).
6. **Bancos de capacitores son elementos independientes**, no hijos de subestaciones.
7. **Áreas/locaciones (ZR, CD, AGR, etc.)** no se modelan como tabla. Quedan implícitas en el `codigo` del elemento (`SET-AGR`, `EAGR_R1`, etc.).
8. **`prefijo_archivo` en `tipos_elemento`** permite autodetectar el tipo al subir un Word con nombre `SET ZR2.doc` o `ETR CD1.docx`.
9. **Auditoría en `inspecciones`**: cada cambio de estado deja huella (`created_by`, `updated_by`, `revisada_por`/`fecha_revision`, `cerrada_por`/`fecha_cierre`). Backfill aplicado en `2026_05_27_000010`.
10. **Constraints declarativos en Postgres**: `chk_inspecciones_estado` y `chk_novedades_estado` garantizan que solo entran valores válidos al estado.
11. **Email único case-insensitive**: índice `idx_usuarios_email_lower_unique` sobre `LOWER(email)`.

## Tablas activas

| Tabla | Propósito |
|---|---|
| `empresas` | Tenants (clientes). |
| `yacimientos` | Sitios geográficos pertenecientes a una empresa. UNIQUE(`empresa_id`, `codigo`). |
| `roles` | Catálogo de roles (`admin`, `supervisor`, `tecnico`). |
| `usuarios` | Usuarios locales sincronizados desde Cognito. UNIQUE(LOWER(email)). |
| `usuario_yacimientos` | Asignación N:M de usuarios a yacimientos (controla scope de supervisor/tecnico). |
| `tipos_elemento` | Catálogo: subestación, ETR, banco_capacitores, seccionador, reconectador. |
| `niveles_tension` | Catálogo: 6.6 / 13.2 / 33 / 132 kV. |
| `criticidades` | Catálogo: Baja, Media, Alta, Crítica (`nivel` 1–4). |
| `elementos` | Equipos inspeccionables. **Lo central del sistema.** UNIQUE(`yacimiento_id`, `codigo`). |
| `inspecciones` | Una visita a un elemento (típicamente 1 Word + 1 ZIP). Estado: `enviada` → `revisada` → `cerrada`. |
| `archivos` | Word/Excel + ZIP por inspección, referencia a S3/MinIO. |
| `novedades` | Hallazgos con criticidad, estado (`abierta`/`resuelta`) y ubicación interna como texto. |

> Las tablas `comentarios`, `historial_cambios` y `sesiones` fueron **eliminadas en el cleanup de 2026-05-27** por no tener uso real. Si en el futuro hace falta auditoría detallada, se vuelve a evaluar.

## Tablas técnicas de Laravel

- `cache`, `cache_locks` — `CACHE_STORE=database` en `.env`.
- `jobs`, `job_batches`, `failed_jobs` — `QUEUE_CONNECTION=database` en `.env`.
- `migrations` — tracking interno de Laravel.

> Las tablas `users`, `password_reset_tokens` y `sessions` del scaffold default de Laravel **no se usan** (la auth es por Cognito sobre la tabla `usuarios`). Candidatas a dropear en un próximo refactor.

## Audit fields en `inspecciones`

| Campo | Significado |
|---|---|
| `tecnico_id` | Quien cargó la inspección originalmente. |
| `created_by`, `updated_by` | Usuario que creó / modificó la fila (independiente del técnico operativo). |
| `revisada_por`, `fecha_revision` | Usuario y momento de la revisión. |
| `cerrada_por`, `fecha_cierre` | Usuario y momento del cierre formal. |
| `observaciones_revisor` | Comentarios del supervisor/admin que revisa. |

Cuando una inspección se cierra, todas las novedades en estado `abierta` se marcan automáticamente como `resuelta` (ver `InspeccionController::updateEstado`).

## Distribución actual del yacimiento PAE

Datos cargados en producción local (2026-05-28):

| Tipo | Función | Cantidad |
|---|---|---|
| Subestación | SET | 26 |
| Subestación | PIAS | 16 |
| Subestación | PC | 7 |
| Subestación | PTC | 2 |
| Subestación | PB_PTC | 2 |
| Subestación | RCI | 2 |
| Est. Transformadora (ETR) | — | 7 |
| Banco de Capacitores | — | 112 |
| Seccionador | — | 454 |
| Reconectador | — | 132 |
| **Total** | | **756** |

Los reconectadores, seccionadores y bancos de capacitores se importaron vía `app\Console\Commands\ImportElementosPaeCommand` desde un listado provisto por el cliente.

## Flujo de carga de una inspección

```
1. Técnico abre la web → "Nueva inspección"
2. Selecciona el elemento (autocomplete por codigo/nombre)
3. Completa encabezado:
     - Fecha de inspección
     - Cuadrilla
     - Integrantes
     - Empresa contratista (snapshot del nombre, no FK)
     - Condiciones de clima
     - Resumen general
4. Sube archivos a MinIO/S3:
     - Reporte: Word (.doc, .docx) o Excel (.xls, .xlsx) → key inspecciones/{id}/reports/{archivo_id}-{nombre}
     - Pack de imágenes: ZIP → key inspecciones/{id}/images/{archivo_id}-{nombre}
5. Carga novedades (0..N), cada una con:
     - Título corto
     - Descripción
     - Criticidad (Baja/Media/Alta/Crítica)
     - Ubicación dentro del elemento (texto libre)
     - Temperatura detectada (opcional, °C)
     - Acción recomendada (opcional)
6. "Enviar" → estado='enviada', supervisor recibe en su dashboard
7. Supervisor PAE/Admin revisa → estado='revisada' + observaciones_revisor
8. Cierre → estado='cerrada' + cerrada_por + fecha_cierre; novedades abiertas pasan a resuelta
```

## Estados y transiciones

### Inspección

```
[creada por técnico] → enviada → revisada → cerrada
                                      ↑          ↓
                                      └──────────┘
                                  (puede reabrirse a revisada)
```

CHECK constraint: `estado IN ('enviada','revisada','cerrada')`.

> El estado `borrador` quedó retirado en la migración `2026_05_27_000001`. Toda inspección persistida arranca al menos en `enviada`.

### Novedad

```
abierta → resuelta
```

CHECK constraint: `estado IN ('abierta','resuelta')`.

## Índices y performance

Compuestos agregados en `2026_05_27_000007`:

| Tabla | Índice | Para qué |
|---|---|---|
| `inspecciones` | `idx_inspecciones_tecnico_fecha` (`tecnico_id`, `fecha_inspeccion`) | "Mis inspecciones del último mes." |
| `inspecciones` | `idx_inspecciones_estado_fecha` (`estado`, `fecha_inspeccion`) | "Pendientes de revisión recientes." |
| `elementos` | `idx_elementos_yacimiento_tipo` (`yacimiento_id`, `tipo_elemento_id`) | Listado por tipo dentro de un yacimiento. |
| `novedades` | `idx_novedades_inspeccion_criticidad` (`inspeccion_id`, `criticidad_id`) | Resumen de criticidad por inspección. |
| `usuarios` | `idx_usuarios_email_lower_unique` (LOWER(email)) | Lookup de Cognito por email case-insensitive. |

## Queries típicas

### Historial completo de un elemento

```sql
SELECT
  i.id,
  i.fecha_inspeccion,
  u.nombre || ' ' || u.apellido AS tecnico,
  i.cuadrilla,
  i.empresa_contratista,
  i.estado,
  (SELECT COUNT(*) FROM novedades n WHERE n.inspeccion_id = i.id) AS novedades,
  (SELECT COUNT(*) FROM archivos a WHERE a.inspeccion_id = i.id) AS archivos
FROM inspecciones i
JOIN usuarios u ON u.id = i.tecnico_id
WHERE i.elemento_id = $1
ORDER BY i.fecha_inspeccion DESC;
```

### Novedades abiertas críticas por yacimiento

```sql
SELECT e.nombre AS elemento,
       e.funcion,
       n.titulo,
       n.ubicacion_dentro_elemento,
       n.created_at
FROM novedades n
JOIN inspecciones i ON i.id = n.inspeccion_id
JOIN elementos e ON e.id = i.elemento_id
JOIN criticidades c ON c.id = n.criticidad_id
WHERE e.yacimiento_id = $1
  AND n.estado = 'abierta'
  AND c.nivel >= 3
ORDER BY n.created_at DESC;
```

### Elementos sin inspección en los últimos 90 días

```sql
SELECT e.id, e.nombre, te.nombre AS tipo, e.funcion,
       MAX(i.fecha_inspeccion) AS ultima_inspeccion
FROM elementos e
JOIN tipos_elemento te ON te.id = e.tipo_elemento_id
LEFT JOIN inspecciones i ON i.elemento_id = e.id
WHERE e.yacimiento_id = $1 AND e.activo = true
GROUP BY e.id, te.nombre, e.funcion
HAVING MAX(i.fecha_inspeccion) IS NULL
    OR MAX(i.fecha_inspeccion) < NOW() - INTERVAL '90 days'
ORDER BY ultima_inspeccion NULLS FIRST;
```

### Conteo por tipo y función

```sql
SELECT
  te.nombre AS tipo,
  COALESCE(e.funcion, '—') AS funcion,
  COUNT(*) AS cantidad
FROM elementos e
JOIN tipos_elemento te ON te.id = e.tipo_elemento_id
WHERE e.yacimiento_id = $1 AND e.activo = true
GROUP BY te.nombre, e.funcion
ORDER BY te.nombre, e.funcion;
```

### Top técnicos con informes del mes

```sql
SELECT u.id, u.nombre || ' ' || u.apellido AS tecnico, COUNT(*) AS informes
FROM inspecciones i
JOIN usuarios u ON u.id = i.tecnico_id
WHERE i.fecha_inspeccion >= DATE_TRUNC('month', NOW())
GROUP BY u.id, u.nombre, u.apellido
ORDER BY informes DESC
LIMIT 5;
```
