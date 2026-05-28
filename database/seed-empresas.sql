-- Empresas reales
INSERT INTO empresas (nombre, plan) VALUES
  ('PECOM', 'basico'),
  ('PAE', 'basico');

-- Yacimiento real asociado a PAE
INSERT INTO yacimientos (empresa_id, nombre, codigo) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PAE'),
   'PAE',
   'YAC-PAE');

-- Usuario administrador principal (para linkeo de Cognito JWT)
-- Note: password_hash field removed; auth is always via Cognito JWT, never local.
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'admin'),
   'Alejandro',
   'Oviedo',
   'aleoviedo071298@gmail.com',
   NOW(),
   NOW());

-- Vincular usuario al yacimiento PAE
INSERT INTO usuario_yacimientos (usuario_id, yacimiento_id) VALUES
  ((SELECT id FROM usuarios WHERE email = 'aleoviedo071298@gmail.com'),
   (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE'));

-- Usuario técnico marijo006@gmail.com (para linkeo de Cognito JWT)
-- Note: password_hash field removed; auth is always via Cognito JWT, never local.
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'tecnico'),
   'Marijo',
   'Técnico',
   'marijo006@gmail.com',
   NOW(),
   NOW());

-- Vincular usuario marijo al yacimiento PAE
INSERT INTO usuario_yacimientos (usuario_id, yacimiento_id) VALUES
  ((SELECT id FROM usuarios WHERE email = 'marijo006@gmail.com'),
   (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE'));
