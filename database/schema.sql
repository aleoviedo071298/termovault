-- ============================================================
-- TermoVault — Schema PostgreSQL
-- Versión: 2 (post-validación con datos reales)
-- ============================================================
-- Multi-tenant: una empresa = un cliente. Todo se filtra por empresa_id.
-- ============================================================

-- ====================  EMPRESAS Y YACIMIENTOS  ====================

CREATE TABLE empresas (
  id          SERIAL PRIMARY KEY,
  nombre      VARCHAR(150) NOT NULL,
  cuit        VARCHAR(20) UNIQUE,
  logo_url    VARCHAR(500),
  plan        VARCHAR(30) DEFAULT 'basico',     -- basico | pro | enterprise
  activo      BOOLEAN DEFAULT true,
  created_at  TIMESTAMP DEFAULT NOW(),
  updated_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE yacimientos (
  id          SERIAL PRIMARY KEY,
  empresa_id  INT NOT NULL REFERENCES empresas(id) ON DELETE RESTRICT,
  nombre      VARCHAR(150) NOT NULL,
  codigo      VARCHAR(50) NOT NULL,
  zona        VARCHAR(100),
  descripcion TEXT,
  activo      BOOLEAN DEFAULT true,
  created_at  TIMESTAMP DEFAULT NOW(),
  UNIQUE(empresa_id, codigo)
);

CREATE INDEX idx_yacimientos_empresa ON yacimientos(empresa_id);

-- ====================  ROLES Y USUARIOS  ====================

CREATE TABLE roles (
  id      SERIAL PRIMARY KEY,
  codigo  VARCHAR(30) UNIQUE NOT NULL,   -- admin | supervisor | tecnico
  nombre  VARCHAR(50) NOT NULL
);

CREATE TABLE usuarios (
  id            SERIAL PRIMARY KEY,
  empresa_id    INT NOT NULL REFERENCES empresas(id) ON DELETE RESTRICT,
  rol_id        INT NOT NULL REFERENCES roles(id),
  nombre        VARCHAR(100) NOT NULL,
  apellido      VARCHAR(100) NOT NULL,
  email         VARCHAR(150) UNIQUE NOT NULL,
  activo        BOOLEAN DEFAULT true,
  created_at    TIMESTAMP DEFAULT NOW(),
  updated_at    TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_usuarios_empresa ON usuarios(empresa_id);

CREATE TABLE usuario_yacimientos (
  usuario_id    INT REFERENCES usuarios(id) ON DELETE CASCADE,
  yacimiento_id INT REFERENCES yacimientos(id) ON DELETE CASCADE,
  PRIMARY KEY (usuario_id, yacimiento_id)
);

-- ====================  CATÁLOGOS  ====================

CREATE TABLE tipos_elemento (
  id               SERIAL PRIMARY KEY,
  codigo           VARCHAR(50) UNIQUE NOT NULL,
  nombre           VARCHAR(100) NOT NULL,
  prefijo_archivo  VARCHAR(20),               -- p/ autodetectar tipo al subir Word
  requiere_tension BOOLEAN DEFAULT false,
  activo           BOOLEAN DEFAULT true
);

CREATE TABLE niveles_tension (
  id        SERIAL PRIMARY KEY,
  kv        NUMERIC(6,2) NOT NULL,
  etiqueta  VARCHAR(20) NOT NULL,
  activo    BOOLEAN DEFAULT true
);

CREATE TABLE criticidades (
  id      SERIAL PRIMARY KEY,
  nivel   INT UNIQUE NOT NULL,        -- 1=baja .. 4=crítica
  nombre  VARCHAR(30) NOT NULL,
  color   VARCHAR(7)                  -- HEX para badges UI
);

-- ====================  ELEMENTOS (lo central)  ====================

CREATE TABLE elementos (
  id                      SERIAL PRIMARY KEY,
  yacimiento_id           INT NOT NULL REFERENCES yacimientos(id) ON DELETE RESTRICT,
  tipo_elemento_id        INT NOT NULL REFERENCES tipos_elemento(id),
  funcion                 VARCHAR(20),                  -- SET | PIAS | PC | PTC | PB_PTC | RCI | NULL
  nivel_tension_id        INT REFERENCES niveles_tension(id),
  nombre                  VARCHAR(150) NOT NULL,
  codigo                  VARCHAR(50) NOT NULL,
  marca                   VARCHAR(100),
  modelo                  VARCHAR(100),
  n_serie                 VARCHAR(100),
  criticidad_id           INT REFERENCES criticidades(id),
  estado_operativo        VARCHAR(30) DEFAULT 'operativo',
  observaciones_generales TEXT,
  activo                  BOOLEAN DEFAULT true,
  created_at              TIMESTAMP DEFAULT NOW(),
  updated_at              TIMESTAMP DEFAULT NOW(),
  UNIQUE(yacimiento_id, codigo)
);

CREATE INDEX idx_elementos_yacimiento ON elementos(yacimiento_id);
CREATE INDEX idx_elementos_tipo ON elementos(tipo_elemento_id);
CREATE INDEX idx_elementos_funcion ON elementos(funcion);
CREATE INDEX idx_elementos_tension ON elementos(nivel_tension_id);

-- ====================  INSPECCIONES  ====================

CREATE TABLE inspecciones (
  id                    SERIAL PRIMARY KEY,
  elemento_id           INT NOT NULL REFERENCES elementos(id) ON DELETE RESTRICT,
  tecnico_id            INT NOT NULL REFERENCES usuarios(id),
  fecha_inspeccion      TIMESTAMP NOT NULL,
  -- Encabezado del Word actual
  cuadrilla             VARCHAR(100),
  integrantes           TEXT,
  empresa_contratista   VARCHAR(150),
  -- Condiciones
  temperatura_ambiente  NUMERIC(4,1),
  humedad_relativa      NUMERIC(4,1),
  carga_pct             NUMERIC(4,1),
  condiciones_clima     VARCHAR(50),
  resumen               TEXT,
  estado                VARCHAR(20) DEFAULT 'borrador',
  -- estados: borrador | enviada | revisada | cerrada
  revisada_por          INT REFERENCES usuarios(id),
  fecha_revision        TIMESTAMP,
  observaciones_revisor TEXT,
  created_at            TIMESTAMP DEFAULT NOW(),
  updated_at            TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_inspecciones_elemento ON inspecciones(elemento_id);
CREATE INDEX idx_inspecciones_tecnico ON inspecciones(tecnico_id);
CREATE INDEX idx_inspecciones_fecha ON inspecciones(fecha_inspeccion DESC);

-- ====================  ARCHIVOS  ====================

CREATE TABLE archivos (
  id              SERIAL PRIMARY KEY,
  inspeccion_id   INT NOT NULL REFERENCES inspecciones(id) ON DELETE CASCADE,
  tipo            VARCHAR(30) NOT NULL,   -- informe_word | pack_imagenes_zip | otro
  nombre_original VARCHAR(255),
  s3_bucket       VARCHAR(100) NOT NULL CHECK (s3_bucket <> 'local'),
  s3_key          VARCHAR(500) NOT NULL,
  tamano_bytes    BIGINT,
  mime_type       VARCHAR(100),
  subido_por      INT REFERENCES usuarios(id),
  created_at      TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_archivos_inspeccion ON archivos(inspeccion_id);

-- ====================  NOVEDADES (hallazgos)  ====================

CREATE TABLE novedades (
  id                        SERIAL PRIMARY KEY,
  inspeccion_id             INT NOT NULL REFERENCES inspecciones(id) ON DELETE CASCADE,
  criticidad_id             INT REFERENCES criticidades(id),
  titulo                    VARCHAR(200) NOT NULL,
  descripcion               TEXT,
  ubicacion_dentro_elemento VARCHAR(200),    -- "Campo 2 — Bushing 33 kV", etc. (texto libre)
  temperatura_detectada     NUMERIC(5,2),
  accion_recomendada        TEXT,
  estado                    VARCHAR(20) DEFAULT 'abierta',
  -- abierta | en_seguimiento | resuelta | descartada
  fecha_resolucion          TIMESTAMP,
  resuelta_en_inspeccion_id INT REFERENCES inspecciones(id),
  created_at                TIMESTAMP DEFAULT NOW(),
  updated_at                TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_novedades_inspeccion ON novedades(inspeccion_id);
CREATE INDEX idx_novedades_estado ON novedades(estado);
CREATE INDEX idx_novedades_criticidad ON novedades(criticidad_id);

-- ====================  SOPORTE / AUDITORÍA  ====================

CREATE TABLE comentarios (
  id            SERIAL PRIMARY KEY,
  inspeccion_id INT REFERENCES inspecciones(id) ON DELETE CASCADE,
  usuario_id    INT REFERENCES usuarios(id),
  contenido     TEXT NOT NULL,
  created_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE historial_cambios (
  id            SERIAL PRIMARY KEY,
  tabla         VARCHAR(50) NOT NULL,
  registro_id   INT NOT NULL,
  usuario_id    INT REFERENCES usuarios(id),
  accion        VARCHAR(20) NOT NULL,    -- create | update | delete | upload
  datos_antes   JSONB,
  datos_despues JSONB,
  created_at    TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_historial_tabla_reg ON historial_cambios(tabla, registro_id);

CREATE TABLE sesiones (
  id          SERIAL PRIMARY KEY,
  usuario_id  INT REFERENCES usuarios(id),
  ip          VARCHAR(45),
  user_agent  VARCHAR(500),
  login_at    TIMESTAMP DEFAULT NOW(),
  logout_at   TIMESTAMP
);
