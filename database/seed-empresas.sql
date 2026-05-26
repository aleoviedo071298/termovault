-- Empresa real
INSERT INTO empresas (nombre, cuit, plan) VALUES
  ('PECOM', '30-00000000-0', 'basico');

-- Yacimiento real asociado a PECOM
INSERT INTO yacimientos (empresa_id, nombre, codigo, zona, descripcion) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   'PAE',
   'YAC-PAE',
   'Cuenca Neuquina',
   'Yacimiento principal para carga de inspecciones termográficas');

-- Usuario administrador principal (para linkeo de Cognito JWT)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, password_hash, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'admin'),
   'Alejandro',
   'Oviedo',
   'aleoviedo071298@gmail.com',
   '$2y$12$DummyBcryptHashForLocalAuthWhichIsNotUsedSinceWeUseCognito',
   NOW(),
   NOW());

-- Vincular usuario al yacimiento PAE
INSERT INTO usuario_yacimientos (usuario_id, yacimiento_id) VALUES
  ((SELECT id FROM usuarios WHERE email = 'aleoviedo071298@gmail.com'),
   (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE'));

-- Usuario técnico marijo006@gmail.com (para linkeo de Cognito JWT)
INSERT INTO usuarios (empresa_id, rol_id, nombre, apellido, email, password_hash, created_at, updated_at) VALUES
  ((SELECT id FROM empresas WHERE nombre = 'PECOM'),
   (SELECT id FROM roles WHERE codigo = 'tecnico'),
   'Marijo',
   'Técnico',
   'marijo006@gmail.com',
   '$2y$12$DummyBcryptHashForLocalAuthWhichIsNotUsedSinceWeUseCognito',
   NOW(),
   NOW());

-- Vincular usuario marijo al yacimiento PAE
INSERT INTO usuario_yacimientos (usuario_id, yacimiento_id) VALUES
  ((SELECT id FROM usuarios WHERE email = 'marijo006@gmail.com'),
   (SELECT id FROM yacimientos WHERE codigo = 'YAC-PAE'));

