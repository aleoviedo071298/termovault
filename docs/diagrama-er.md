# Diagrama ER — TermoVault

> Vista entidad-relación del modelo actual. Renderizable en GitHub (Mermaid) o cualquier viewer que soporte `mermaid` blocks.

## Diagrama principal

```mermaid
erDiagram
    EMPRESAS ||--o{ USUARIOS : "tiene"
    EMPRESAS ||--o{ YACIMIENTOS : "opera"

    ROLES ||--o{ USUARIOS : "asigna"

    USUARIOS ||--o{ USUARIO_YACIMIENTOS : "asignado_a"
    YACIMIENTOS ||--o{ USUARIO_YACIMIENTOS : "tiene_asignados"

    YACIMIENTOS ||--o{ ELEMENTOS : "contiene"

    TIPOS_ELEMENTO ||--o{ ELEMENTOS : "clasifica"
    NIVELES_TENSION ||--o{ ELEMENTOS : "califica"
    CRITICIDADES ||--o{ ELEMENTOS : "categoriza"

    ELEMENTOS ||--o{ INSPECCIONES : "inspeccionado_en"

    USUARIOS ||--o{ INSPECCIONES : "como_tecnico"
    USUARIOS ||--o{ INSPECCIONES : "como_revisor"
    USUARIOS ||--o{ INSPECCIONES : "como_cerrador"
    USUARIOS ||--o{ INSPECCIONES : "como_creador"

    INSPECCIONES ||--o{ ARCHIVOS : "adjunta"
    INSPECCIONES ||--o{ NOVEDADES : "encuentra"

    CRITICIDADES ||--o{ NOVEDADES : "califica"

    USUARIOS ||--o{ ARCHIVOS : "subio"

    EMPRESAS {
        bigint id PK
        varchar nombre
        varchar plan
        boolean activo
        timestamp created_at
        timestamp updated_at
    }

    YACIMIENTOS {
        bigint id PK
        bigint empresa_id FK
        varchar nombre
        varchar codigo
        boolean activo
        timestamp created_at
    }

    ROLES {
        bigint id PK
        varchar codigo
        varchar nombre
    }

    USUARIOS {
        bigint id PK
        bigint empresa_id FK
        bigint rol_id FK
        varchar nombre
        varchar apellido
        varchar email "UNIQUE LOWER"
        varchar password_hash "dummy, Cognito es auth real"
        boolean activo
        timestamp created_at
        timestamp updated_at
    }

    USUARIO_YACIMIENTOS {
        bigint usuario_id PK_FK
        bigint yacimiento_id PK_FK
    }

    TIPOS_ELEMENTO {
        bigint id PK
        varchar codigo "UNIQUE"
        varchar nombre
        varchar prefijo_archivo
        boolean requiere_tension
        boolean activo
    }

    NIVELES_TENSION {
        bigint id PK
        decimal kv
        varchar etiqueta
        boolean activo
    }

    CRITICIDADES {
        bigint id PK
        int nivel "UNIQUE 1-4"
        varchar nombre
        varchar color
    }

    ELEMENTOS {
        bigint id PK
        bigint yacimiento_id FK
        bigint tipo_elemento_id FK
        varchar funcion "SET PIAS PC PTC PB_PTC RCI"
        bigint nivel_tension_id FK
        varchar nombre
        varchar codigo "UNIQUE por yacimiento"
        varchar marca
        varchar modelo
        varchar n_serie
        bigint criticidad_id FK
        varchar estado_operativo
        text observaciones_generales
        bigint created_by FK
        bigint updated_by FK
        boolean activo
        timestamp created_at
        timestamp updated_at
    }

    INSPECCIONES {
        bigint id PK
        bigint elemento_id FK
        bigint tecnico_id FK
        timestamp fecha_inspeccion
        varchar cuadrilla
        text integrantes
        varchar empresa_contratista "snapshot"
        varchar condiciones_clima
        text resumen
        varchar estado "enviada revisada cerrada"
        bigint revisada_por FK
        timestamp fecha_revision
        bigint cerrada_por FK
        timestamp fecha_cierre
        text observaciones_revisor
        bigint created_by FK
        bigint updated_by FK
        timestamp created_at
        timestamp updated_at
    }

    ARCHIVOS {
        bigint id PK
        bigint inspeccion_id FK
        varchar tipo "informe_word informe_excel pack_imagenes_zip"
        varchar nombre_original
        varchar s3_bucket
        varchar s3_key
        bigint tamano_bytes
        varchar mime_type
        bigint subido_por FK
        timestamp created_at
    }

    NOVEDADES {
        bigint id PK
        bigint inspeccion_id FK
        bigint criticidad_id FK
        varchar titulo
        text descripcion
        varchar ubicacion_dentro_elemento "texto libre"
        decimal temperatura_detectada
        text accion_recomendada
        varchar estado "abierta resuelta"
        timestamp created_at
        timestamp updated_at
    }
```

## Diagrama simplificado (flujo de negocio)

Para discusiones de producto sin detalle de columnas:

```mermaid
flowchart LR
    Empresa --> Yacimiento
    Yacimiento --> Elemento
    Elemento --> Inspeccion[Inspección]
    Inspeccion --> Archivo
    Inspeccion --> Novedad[Novedad / Hallazgo]

    Tecnico((Técnico)) -.->|crea| Inspeccion
    Supervisor((Supervisor)) -.->|revisa / cierra| Inspeccion
    Admin((Admin)) -.->|gestiona| Empresa
    Admin -.->|gestiona| Usuario
    Usuario -.->|asignado a| Yacimiento
```

## Diagrama de estados — Inspección

```mermaid
stateDiagram-v2
    [*] --> enviada: técnico carga el informe
    enviada --> revisada: supervisor PAE / admin revisa
    revisada --> cerrada: supervisor PAE / admin cierra
    cerrada --> revisada: reapertura (admin)
    revisada --> enviada: rechazo (admin)
    cerrada --> [*]
```

Notas:

- `enviada` es el estado inicial (no hay `borrador` desde la migración `2026_05_27_000001`).
- Al pasar a `cerrada`, todas las novedades en estado `abierta` se actualizan a `resuelta` (ver `InspeccionController::updateEstado`).
- Las transiciones de reapertura/rechazo no están explícitamente en el código actual pero están permitidas por la CHECK constraint `chk_inspecciones_estado`.

## Diagrama de estados — Novedad

```mermaid
stateDiagram-v2
    [*] --> abierta: creada al cargar inspección
    abierta --> resuelta: cierre de inspección o acción manual
    resuelta --> [*]
```

CHECK constraint `chk_novedades_estado` solo permite `abierta` y `resuelta`.

## Notas

- **Cardinalidades**: `||` = exactamente uno, `o{` = cero o más, `o|` = cero o uno.
- **`USUARIOS` aparece 4 veces** del lado de `INSPECCIONES` porque la misma tabla cubre cuatro roles distintos en la inspección: técnico, revisor, cerrador y creador. Mermaid permite múltiples relaciones entre las mismas tablas con etiquetas diferentes.
- **`empresa_contratista` no es FK**: es un snapshot textual del nombre de la empresa al momento de crear la inspección. Decisión registrada en ADR-011.
- **`ubicacion_dentro_elemento` es texto libre**: la DB no modela los puntos internos (bushings, campos) — esos viven dentro del Word.

## Para mantener el diagrama

- Si una migración agrega/quita una columna o tabla, **actualizar este archivo** en el mismo PR.
- Para regenerar el diagrama a partir del schema real:
  ```bash
  docker compose exec postgres pg_dump -s -U termovault termovault > /tmp/schema.sql
  # cargar a https://dbdiagram.io o usar mermaidchart.com con la opción "import"
  ```
