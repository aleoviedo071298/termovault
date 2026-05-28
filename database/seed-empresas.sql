-- Empresas reales
INSERT INTO empresas (nombre, plan) VALUES
  ('PECOM', 'basico'),
  ('PAE', 'basico');

-- Yacimiento real asociado a PAE
INSERT INTO yacimientos (empresa_id, nombre, codigo) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PAE'),
   'PAE',
   'YAC-PAE');

-- 1. Alejandro Oviedo (Admin PECOM)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'admin'),
   'Alejandro',
   'Oviedo',
   'aleoviedo071298@gmail.com',
   NOW(),
   NOW());

-- 2. Axel Elgueta (Supervisor PAE)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PAE'),
   (SELECT id FROM roles WHERE codigo = 'supervisor'),
   'Axel',
   'Elgueta',
   'aelgueta@pae-energy.com',
   NOW(),
   NOW());

-- 3. Cuadrilla 625 (Técnico PECOM)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'tecnico'),
   'Cuadrilla',
   '625',
   'cuadrilla.625@gmail.com',
   NOW(),
   NOW());

-- 4. Raul Torrero (Supervisor PECOM)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'supervisor'),
   'Raul',
   'Torrero',
   'raul.torrero@pecomenergia.com.ar',
   NOW(),
   NOW());

-- Vincular usuarios al yacimiento PAE
INSERT INTO usuario_yacimientos (usuario_id, yacimiento_id) VALUES
  ((SELECT id FROM usuarios WHERE email = 'aleoviedo071298@gmail.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')),
  ((SELECT id FROM usuarios WHERE email = 'aelgueta@pae-energy.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')),
  ((SELECT id FROM usuarios WHERE email = 'cuadrilla.625@gmail.com'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE')),
  ((SELECT id FROM usuarios WHERE email = 'raul.torrero@pecomenergia.com.ar'), (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE'));
