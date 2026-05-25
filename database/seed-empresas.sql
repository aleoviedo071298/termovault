-- Empresa default (el primer cliente / el yacimiento propio)
-- Reemplazar nombre y CUIT por los reales antes de correr en producción

INSERT INTO empresas (nombre, cuit, plan) VALUES
  ('Empresa Demo', '30-00000000-0', 'basico');

-- Yacimiento default asociado a la empresa anterior
-- Reemplazar nombre y código por los reales

INSERT INTO yacimientos (empresa_id, nombre, codigo, zona, descripcion) VALUES
  ((SELECT id FROM empresas WHERE cuit = '30-00000000-0'),
   'Yacimiento Demo',
   'YAC-DEMO',
   'Cuenca Neuquina',
   'Yacimiento inicial para carga de inspecciones termográficas');
