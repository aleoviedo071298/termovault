# API REST — TermoVault

Base URL local: `http://localhost:8000/api`

Todas las respuestas son JSON. Todos los endpoints excepto `/health` y `/auth/login` requieren `Authorization: Bearer <access_token>` con un access_token de Cognito.

## Convenciones

- **Códigos**:
  - `200` OK — operación exitosa
  - `201` Created — recurso creado
  - `202` Accepted — login requiere desafío (NEW_PASSWORD_REQUIRED, etc.)
  - `401` Unauthorized — token ausente, inválido o expirado
  - `403` Forbidden — autenticado pero sin rol o sin scope
  - `404` Not Found — recurso no existe o queda fuera del scope del usuario
  - `422` Unprocessable Entity — validación falló (incluye `errors` con detalle)
- **Errores**: `{"message": "...", "errors": {...}}` (formato de `ValidationException` de Laravel).
- **Fechas**: ISO 8601 con timezone (`2026-05-27T14:00:00-03:00`).
- **Auth header**: `Authorization: Bearer <access_token>`. El access_token viene de Cognito (no del id_token).

## Endpoints

### Health check

```
GET /api/health
```

Sin auth. Devuelve `{ "status": "ok", "service": "termovault-api", "timestamp": "...", "version": "0.1.0" }`.

---

### Auth

#### `POST /api/auth/login`

Login contra Cognito. Sin token.

**Request**:
```json
{
  "email": "user@example.com",
  "password": "...",
  "session": "...",          // solo en challenge response
  "new_password": "...",     // solo en challenge response
  "nickname": "Nombre"       // opcional, default = prefix del email
}
```

**Response 200**:
```json
{
  "access_token": "eyJ...",
  "id_token": "eyJ...",
  "refresh_token": "eyJ...",
  "expires_in": 3600
}
```

**Response 202** (NEW_PASSWORD_REQUIRED):
```json
{
  "message": "Challenge required: NEW_PASSWORD_REQUIRED",
  "challenge": "NEW_PASSWORD_REQUIRED",
  "session": "..."
}
```

#### `GET /api/auth/me`

Devuelve los claims del JWT + datos del usuario local.

**Response**:
```json
{
  "id": 1,
  "sub": "...",
  "email": "user@example.com",
  "username": "user@example.com",
  "token_use": "access",
  "empresa_id": 1,
  "local_role": "admin",
  "claims": { ... }
}
```

---

### Catálogos

#### `GET /api/catalogos`

Catálogos filtrados por scope del usuario:

```json
{
  "yacimientos":     [ { "id": 1, "nombre": "PAE", "codigo": "YAC-PAE" } ],
  "tipos_elemento":  [ { "id": 1, "nombre": "Subestación", "codigo": "subestacion", "requiere_tension": true } ],
  "niveles_tension": [ { "id": 1, "kv": 6.6, "etiqueta": "6,6 kV" } ],
  "criticidades":    [ { "id": 1, "nivel": 1, "nombre": "Baja", "color": "#22c55e" } ]
}
```

> Tipos / niveles / criticidades son globales. Yacimientos quedan limitados a los asignados al usuario (excepto admin, que ve todos).

---

### Dashboard

#### `GET /api/dashboard/overview`

Métricas y reportes según el rol.

**Query params** (opcionales):
- `estado` — filtra reports por estado
- `tecnico_id` — filtra por técnico
- `empresa_id` — filtra por empresa contratista
- `yacimiento_id` — filtra por yacimiento
- `fecha_desde`, `fecha_hasta` — rango ISO date

**Response**:
```json
{
  "role": "admin" | "supervisor" | "tecnico",
  "scope": {
    "empresa_id": 1,
    "empresa_nombre": "PECOM",
    "user_id": 1,
    "user_name": "Alejandro Oviedo",
    "is_pae_supervisor": false,
    "assigned_yacimiento_names": ["PAE"]
  },
  "stats": {
    "total_informes": 245,
    "pendientes": 12,
    "revisados": 233,
    "fallas_criticas": 8,
    "tecnicos_activos": 5,
    "empresas": 2,
    "yacimientos": 1,
    "informes_mes": 14,
    "informes_mes_global": 32,
    "mis_observados": 3,
    "mis_aprobados": 28,
    "informes_con_archivos": 200,
    "ultimo_informe": "2026-05-27T...",
    "contratistas_activas": 2,
    "elementos_termografiados": 145
  },
  "top_tecnicos": [ { "id": 1, "nombre": "...", "total": 32 } ],
  "subestaciones_recientes": [ { "nombre": "SET AGR", "ultima_fecha": "..." } ],
  "informes_criticos_recientes": [ ... ],
  "reports": [
    {
      "id": 1,
      "fecha_inspeccion": "2026-05-27T...",
      "estado": "revisada",
      "observaciones_revisor": "...",
      "elemento": "SET AGR (SET-AGR)",
      "tecnico": "Alejandro Oviedo",
      "empresa": "PECOM S.A.",
      "yacimiento": "PAE",
      "criticidad": "Normal",
      "hallazgos": 0
    }
  ]
}
```

---

### Elementos

#### `GET /api/elementos`

Listado según scope. Roles: `admin`, `supervisor`, `tecnico`.

**Query params**:
- `my_inspections_only=true` — devuelve solo elementos con al menos una inspección del técnico actual.

**Response**: array de objetos `{ id, nombre, codigo, tipo_elemento_id, tipo, funcion, empresa_id, yacimiento, criticidad, criticidad_nivel, criticidad_color, nivel_tension_id, tension }`.

#### `GET /api/elementos/{id}`

Detalle del elemento + historial de inspecciones (con archivos y novedades).

**404** si el elemento queda fuera del scope.

#### `POST /api/elementos`

Crear elemento. Roles: `admin`, `supervisor` (con scope).

**Body**:
```json
{
  "yacimiento_id": 1,
  "tipo_elemento_id": 1,
  "funcion": "SET",
  "nivel_tension_id": 3,
  "nombre": "SET AGR",
  "codigo": "SET-AGR",
  "marca": "Siemens",
  "modelo": "...",
  "n_serie": "...",
  "criticidad_id": 2,
  "estado_operativo": "operativo",
  "observaciones_generales": "...",
  "activo": true
}
```

> Supervisor solo puede crear elementos en yacimientos propios. Admin sin restricción.

#### `PUT /api/elementos/{id}`

Misma forma que POST. Roles: `admin`, `supervisor`.

#### `DELETE /api/elementos/{id}`

Hard-delete cascada (elimina inspecciones del elemento). Roles: `admin`, `supervisor`.

> En el roadmap: cambiar a soft-delete (`activo=false`) para preservar evidencia histórica.

---

### Inspecciones

#### `POST /api/inspecciones`

Crear inspección con archivos. Roles: `admin`, `supervisor`, `tecnico`.

**Content-Type**: `multipart/form-data`

**Campos**:
- `elemento_id` (int, required) — elemento al que se asocia
- `fecha_inspeccion` (datetime, required)
- `cuadrilla` (string, optional, max 100)
- `integrantes` (string, optional)
- `empresa_contratista` (string, optional, max 150) — snapshot del nombre; si va vacío se autocompleta con la empresa del usuario
- `condiciones_clima` (string, optional, max 50)
- `resumen` (string, optional)
- `estado` (string, optional) — default `enviada`
- `reporte` (file, optional, max 15 MB) — Word o Excel
- `imagenes` (file, optional, max 60 MB) — ZIP
- `novedades` (JSON string, optional) — array de hallazgos

**Ejemplo de `novedades`** (como JSON string):
```json
[
  {
    "criticidad_id": 3,
    "titulo": "Sobrecalentamiento en fase R",
    "descripcion": "Temperatura detectada de 78°C en bushing superior...",
    "ubicacion_dentro_elemento": "Bushing 33 kV - fase R",
    "temperatura_detectada": 78.5,
    "accion_recomendada": "Programar limpieza y reapriete en próxima parada."
  }
]
```

**Response 201**: inspección creada con relaciones cargadas.

> Las novedades se crean con `estado='abierta'`. Si la inspección luego se cierra, pasan automáticamente a `resuelta`.

#### `GET /api/inspecciones/{id}`

Detalle completo de la inspección (técnico, revisor, cerrador, elemento, archivos, novedades). Filtrado por scope.

#### `PATCH /api/inspecciones/{id}/estado`

Cambia el estado de la inspección. Roles: `admin`, `supervisor` (solo PAE).

**Body**:
```json
{
  "estado": "enviada" | "revisada" | "cerrada",
  "observaciones_revisor": "Comentarios opcionales del revisor"
}
```

Efectos:
- `revisada` → setea `revisada_por` + `fecha_revision`.
- `cerrada` → setea `cerrada_por` + `fecha_cierre`. Si no había sido revisada, también setea `revisada_por`/`fecha_revision`. Pasa todas las novedades `abierta` a `resuelta`.

---

### Admin

> Todos requieren rol `admin`.

#### `GET /api/admin/usuarios`

Lista todos los usuarios del sistema con sus empresas, roles y yacimientos asignados.

#### `GET /api/admin/usuarios/meta`

Metadatos para el form de alta:
```json
{
  "roles": [ { "id": 1, "codigo": "admin", "nombre": "Administrador" } ],
  "empresas": [ { "id": 1, "nombre": "PECOM" } ],
  "yacimientos": [ { "id": 1, "nombre": "PAE", "codigo": "YAC-PAE", "empresa_id": 2 } ]
}
```

#### `POST /api/admin/usuarios`

Crea un usuario local. No crea el usuario en Cognito; eso se hace por separado en la consola de AWS (el provisioning automático sucede en el primer login si el usuario existe en Cognito).

**Body**:
```json
{
  "nombre": "...",
  "apellido": "...",
  "email": "user@example.com",
  "empresa_id": 1,
  "rol_codigo": "tecnico",
  "yacimientos": [1, 2]
}
```

#### `PUT /api/admin/usuarios/{id}`

Actualiza usuario y reasigna yacimientos.

**Body**: igual que POST + `activo: true|false`.

#### `POST /api/admin/empresas`

```json
{ "nombre": "..." }
```

#### `POST /api/admin/yacimientos`

```json
{
  "empresa_id": 1,
  "nombre": "...",
  "codigo": "YAC-..."
}
```

---

## Matriz de permisos por endpoint

| Endpoint | admin | supervisor | tecnico |
|---|---|---|---|
| `GET /catalogos` | ✓ | ✓ | ✓ |
| `GET /dashboard/overview` | ✓ (global) | ✓ (scope) | ✓ (propios) |
| `GET /elementos` | ✓ (global) | ✓ (scope) | ✓ (scope) |
| `GET /elementos/{id}` | ✓ | ✓ (scope) | ✓ (scope) |
| `POST /elementos` | ✓ | ✓ solo PAE | ✗ |
| `PUT /elementos/{id}` | ✓ | ✓ solo PAE | ✗ |
| `DELETE /elementos/{id}` | ✓ | ✓ solo PAE | ✗ |
| `POST /inspecciones` | ✓ | ✓ (scope) | ✓ (scope) |
| `GET /inspecciones/{id}` | ✓ | ✓ (scope) | ✓ (propias) |
| `PATCH /inspecciones/{id}/estado` | ✓ | ✓ solo PAE | ✗ |
| `GET /admin/*` | ✓ | ✗ | ✗ |
| `POST /admin/usuarios` | ✓ | ✗ | ✗ |
| `POST /admin/empresas` | ✓ | ✗ | ✗ |
| `POST /admin/yacimientos` | ✓ | ✗ | ✗ |

> **Supervisor "solo PAE"** = supervisor cuya empresa es PAE y que tiene yacimiento `YAC-PAE` asignado. Los supervisores de empresas contratistas NO pueden mutar elementos ni cambiar estados.

Ver `docs/06-seguridad.md` para el detalle del scope.
