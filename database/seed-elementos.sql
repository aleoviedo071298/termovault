-- ============================================================
-- Seed de elementos inspeccionables
-- Extraídos de los nombres de archivos Word del cliente
-- Total: 65 elementos
--
-- IMPORTANTE: este seed asume que ya corriste:
--   1. schema.sql
--   2. seed-empresas.sql      (crea Empresa Demo + Yacimiento Demo)
--   3. seed-tipos-elemento.sql
--   4. seed-niveles-tension.sql
--   5. seed-criticidades.sql
-- ============================================================

-- Variable: id del yacimiento donde va todo
-- (PostgreSQL no soporta variables directas en scripts, usamos CTE)

WITH ctx AS (
  SELECT
    (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')              AS yac_id,
    (SELECT id FROM tipos_elemento WHERE codigo = 'subestacion')        AS tipo_sub,
    (SELECT id FROM tipos_elemento WHERE codigo = 'estacion_transformadora') AS tipo_etr,
    (SELECT id FROM tipos_elemento WHERE codigo = 'banco_capacitores')  AS tipo_bco
)
INSERT INTO elementos (yacimiento_id, tipo_elemento_id, funcion, nombre, codigo)
SELECT yac_id, tipo_sub, 'SET',    'SET AGR',  'SET-AGR'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET BAY',  'SET-BAY'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET CDN',  'SET-CDN'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET CD3',  'SET-CD3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET CG1',  'SET-CG1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET CG2',  'SET-CG2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET CG3',  'SET-CG3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET CT2',  'SET-CT2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ESC',  'SET-ESC'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET LFL',  'SET-LFL'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET LMS',  'SET-LMS'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET LMS3', 'SET-LMS3' FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET MSC',  'SET-MSC'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET OR1',  'SET-OR1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET OR3',  'SET-OR3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET PAM',  'SET-PAM'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET RE1',  'SET-RE1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET VH1',  'SET-VH1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET VH3',  'SET-VH3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET VMA',  'SET-VMA'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ZR1',  'SET-ZR1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ZR2',  'SET-ZR2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ZR3',  'SET-ZR3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ZR4',  'SET-ZR4'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ZR5',  'SET-ZR5'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'SET',    'SET ZR6',  'SET-ZR6'  FROM ctx UNION ALL

-- PIAS (16) — Plantas Inyectoras
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS CD2',  'PIAS-CD2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS CG8',  'PIAS-CG8'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS CT2',  'PIAS-CT2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS ES3',  'PIAS-ES3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS JO3',  'PIAS-JO3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS LMA',  'PIAS-LMA'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS LMS2', 'PIAS-LMS2' FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS LMS3', 'PIAS-LMS3' FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS LP2',  'PIAS-LP2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS OR1',  'PIAS-OR1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS OR3',  'PIAS-OR3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS RE1',  'PIAS-RE1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS VH2',  'PIAS-VH2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS ZR2',  'PIAS-ZR2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS ZR3',  'PIAS-ZR3'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PIAS',   'PIAS ZR4',  'PIAS-ZR4'  FROM ctx UNION ALL

-- PC (7) — Puestos de Conexión
SELECT yac_id, tipo_sub, 'PC',     'PC AGR',  'PC-AGR'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PC',     'PC CD',   'PC-CD'   FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PC',     'PC TP',   'PC-TP'   FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PC',     'PC VH2',  'PC-VH2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PC',     'PC ZR1',  'PC-ZR1'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PC',     'PC ZR2',  'PC-ZR2'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PC',     'PC ZR3',  'PC-ZR3'  FROM ctx UNION ALL

-- PTC (2)
SELECT yac_id, tipo_sub, 'PTC',    'PTC CD',  'PTC-CD'  FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PTC',    'PTC VH',  'PTC-VH'  FROM ctx UNION ALL

-- PB PTC (2)
SELECT yac_id, tipo_sub, 'PB_PTC', 'PB PTC CD', 'PB-PTC-CD' FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'PB_PTC', 'PB PTC VH', 'PB-PTC-VH' FROM ctx UNION ALL

-- RCI (2) — Red Contra Incendios
SELECT yac_id, tipo_sub, 'RCI',    'RCI AGR',     'RCI-AGR'     FROM ctx UNION ALL
SELECT yac_id, tipo_sub, 'RCI',    'RCI PTC CD',  'RCI-PTC-CD'  FROM ctx UNION ALL

-- ETR (7) — Estaciones Transformadoras
SELECT yac_id, tipo_etr, NULL,     'ETR CD1', 'ETR-CD1' FROM ctx UNION ALL
SELECT yac_id, tipo_etr, NULL,     'ETR CD2', 'ETR-CD2' FROM ctx UNION ALL
SELECT yac_id, tipo_etr, NULL,     'ETR OR2', 'ETR-OR2' FROM ctx UNION ALL
SELECT yac_id, tipo_etr, NULL,     'ETR RE2', 'ETR-RE2' FROM ctx UNION ALL
SELECT yac_id, tipo_etr, NULL,     'ETR VH2', 'ETR-VH2' FROM ctx UNION ALL
SELECT yac_id, tipo_etr, NULL,     'ETR ZR1', 'ETR-ZR1' FROM ctx UNION ALL
SELECT yac_id, tipo_etr, NULL,     'ETR ZR2', 'ETR-ZR2' FROM ctx;

-- ============================================================
-- Actualizaciones post-carga solicitadas por el usuario:
-- ============================================================

-- 1. Modificar subestaciones para empezar con SET y tener funcion = 'set'
UPDATE elementos
SET
  nombre = CASE
    WHEN nombre NOT LIKE 'SET %' THEN 'SET ' || nombre
    ELSE nombre
  END,
  funcion = 'set'
WHERE tipo_elemento_id = (SELECT id FROM tipos_elemento WHERE codigo = 'subestacion');

-- 2. Modificar estaciones transformadoras para tener tension de 132 kV y funcion = 'ETR'
UPDATE elementos
SET
  nivel_tension_id = (SELECT id FROM niveles_tension WHERE etiqueta = '132 kV'),
  funcion = 'ETR'
WHERE tipo_elemento_id = (SELECT id FROM tipos_elemento WHERE codigo = 'estacion_transformadora');
