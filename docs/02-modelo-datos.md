# Modelo de Datos — TermoVault

> Versión 2 — Post validación con datos reales del cliente

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
                    └── Inspección
                          ├── Archivos (Word + ZIP de imágenes)
                          └── Novedades (hallazgos con criticidad)
```

## Decisiones clave

1. **Multi-tenant por columna `empresa_id`**. Más simple, suficiente para el tamaño esperado.
2. **Una sola tabla `elementos`** con `tipo_elemento_id` y `funcion`. Subestaciones SET, PIAS, PC, PTC, PB_PTC y RCI viven juntas, diferenciadas por `funcion`. Tipos top-level distintos (ETR, banco, seccionador, reconectador) usan `tipo_elemento_id`.
3. **Niveles de tensión** como catálogo: 6.6, 13.2, 33, 132 kV. Nullable porque el Word actual no siempre identifica una única tensión por elemento (las subestaciones tienen 33 y 6.6 mezclados internamente).
4. **Archivos fuera de la DB**: solo se guarda `s3_bucket + s3_key`. El binario va a S3.
5. **Word como fuente de verdad**: la DB no modela los puntos internos (Campos, Bushings, etc.). El Word los contiene. Las novedades estructuradas referencian la ubicación interna como texto libre (`ubicacion_dentro_elemento`).
6. **Bancos de capacitores son elementos independientes**, no hijos de subestaciones. Se cargan, inspeccionan y trackean por separado.
7. **Áreas/locaciones (ZR, CD, AGR, etc.)** no se modelan como tabla. Quedan implícitas en el `codigo` del elemento.
8. **Función `prefijo_archivo`** en `tipos_elemento` permite que al subir un archivo "SET ZR2.doc" el sistema sugiera automáticamente vincularlo al elemento correspondiente.

## Tablas

Ver [`/database/schema.sql`](../database/schema.sql) para el DDL completo.

| Tabla | Propósito |
|---|---|
| `empresas` | Tenants (clientes). |
| `yacimientos` | Sitios geográficos de una empresa. |
| `roles`, `usuarios`, `usuario_yacimientos` | Auth y permisos por yacimiento. |
| `tipos_elemento` | Catálogo: subestación, ETR, banco, seccionador, reconectador. |
| `niveles_tension` | Catálogo: 6.6 / 13.2 / 33 / 132 kV. |
| `criticidades` | Catálogo: baja, media, alta, crítica. |
| `elementos` | Equipos inspeccionables. **Lo central del sistema.** |
| `inspecciones` | Una visita a un elemento (1 Word + 1 ZIP por defecto). |
| `archivos` | Word + ZIP por inspección, referencia a S3. |
| `novedades` | Hallazgos con criticidad, estado y ubicación interna como texto. |
| `comentarios` | Comunicación entre técnico y supervisor. |
| `historial_cambios` | Auditoría genérica (qué cambió, quién, cuándo). |
| `sesiones` | Login tracking. |

## Distribución de elementos en el yacimiento

Datos reales extraídos de los Words actuales del cliente (65 elementos):

| Tipo | Función | Cantidad |
|---|---|---|
| Subestación | SET     | 26 |
| Subestación | PIAS    | 16 |
| Subestación | PC      | 7  |
| Subestación | PTC     | 2  |
| Subestación | PB_PTC  | 2  |
| Subestación | RCI     | 2  |
| Est. Transformadora (ETR) | —   | 7  |
| Banco de Capacitores | —   | 3  |
| **Total**   |   | **65** |

A esto se sumarán los **seccionadores** (33 kV y 13.2 kV) y **reconectadores** que el admin cargará después, ya que no estaban en el set inicial de Words.

## Flujo de carga de una inspección

```
1. Técnico abre la web → "Nueva inspección"
2. Selecciona el elemento (autocomplete: "SET ZR2", "PIAS CD2", etc.)
3. Completa encabezado:
     - Fecha
     - Cuadrilla
     - Integrantes
     - Empresa contratista
     - Carga (A) si aplica
4. Sube archivos:
     - Informe Word (.doc o .docx)  → S3 via presigned URL
     - Pack de imágenes (.zip)       → S3 via presigned URL
5. Carga novedades (0..N), cada una con:
     - Título corto
     - Descripción
     - Criticidad (baja/media/alta/crítica)
     - Ubicación dentro del elemento (texto libre, autocomplete)
     - Temperatura detectada (opcional)
     - Acción recomendada (opcional)
6. "Enviar" → estado = 'enviada' → notifica supervisor
7. Supervisor revisa, comenta, cambia estado a 'revisada' o 'cerrada'
```

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
GROUP BY e.id, te.nombre
HAVING MAX(i.fecha_inspeccion) IS NULL
    OR MAX(i.fecha_inspeccion) < NOW() - INTERVAL '90 days'
ORDER BY ultima_inspeccion NULLS FIRST;
```

### Conteo por función (qué hay en el yacimiento)

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
