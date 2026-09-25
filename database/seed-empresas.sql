-- Empresas reales
INSERT INTO empresas (nombre, plan) VALUES
  ('PECOM', 'basico'),
  ('PAE', 'basico');

-- Yacimiento real asociado a PAE
INSERT INTO yacimientos (empresa_id, nombre, codigo) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PAE'),
   'PAE',
   'YAC-PAE');

-- 1. Admin PECOM
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'admin'),
   'Admin',
   'Demo',
   'admin@example.com',
   NOW(),
   NOW());

-- 2. Supervisor PAE
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PAE'),
   (SELECT id FROM roles WHERE codigo = 'supervisor'),
   'Supervisor',
   'Demo',
   'supervisor@example.com',
   NOW(),
   NOW());

-- 3. Cuadrilla 625 (Técnico PECOM)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'tecnico'),
   'Cuadrilla',
   '625',
   'tecnico@example.com',
   NOW(),
   NOW());

-- 4. Supervisor PECOM
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'supervisor'),
   'Supervisor',
   'Demo2',
   'supervisor2@example.com',
   NOW(),
   NOW());

-- Vincular usuarios al yacimiento PAE
INSERT INTO usuario_yacimientos (usuario_id, yacimiento_id) VALUES
  ((SELECT id FROM usuarios WHERE email = 'admin@example.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')),
  ((SELECT id FROM usuarios WHERE email = 'supervisor@example.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')),
  ((SELECT id FROM usuarios WHERE email = 'tecnico@example.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')),
  ((SELECT id FROM usuarios WHERE email = 'supervisor2@example.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE'));
