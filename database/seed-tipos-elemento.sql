-- Catálogo de tipos de elemento inspeccionable
-- El prefijo_archivo permite autodetectar el tipo cuando un técnico
-- sube un Word con nombre tipo "SET ZR2.doc" o "ETR CD1.docx"

INSERT INTO tipos_elemento (codigo, nombre, prefijo_archivo, requiere_tension) VALUES
  ('subestacion',             'Subestación',              'SET',     true),
  ('estacion_transformadora', 'Estación Transformadora',  'ETR',     true),
  ('banco_capacitores',       'Banco de Capacitores',     'Bco Cap', false),
  ('seccionador',             'Seccionador',              'SEC',     true),
  ('reconectador',            'Reconectador',             'REC',     true),
  ('seccionalizador',         'Seccionalizador',          'SECC',    true),
  ('fusesaver',               'Fusesaver',                'FS',      true);

-- Nota: dentro de "subestación" hay varias funciones (SET, PIAS, PC, PTC, PB_PTC, RCI)
-- que se guardan en la columna elementos.funcion. Si el nombre del archivo empieza
-- con "PIAS", "PC", "PTC", "PB PTC" o "RCI" → es subestación + esa función.
