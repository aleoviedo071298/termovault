# Deploy en AWS - TermoVault (EC2 unica)

Guia operativa para la arquitectura acordada:

- 1 EC2 (app + nginx + docker compose)
- PostgreSQL en la misma EC2
- S3 para adjuntos
- Cognito para auth
- Backups manuales

## 1. Arquitectura target

```txt
Internet
  -> Nginx (EC2)
      -> /        frontend estatico (web/dist)
      -> /api/*   backend Laravel
  -> PostgreSQL local (misma EC2)
  -> S3 privado (archivos)
  -> Cognito (login / JWT)
```

Notas:
- Route53 no es obligatorio (puede usarse DNS externo).
- ACM no es obligatorio en este esquema (TLS con Let's Encrypt en Nginx).

## 2. Pre-requisitos

- Cuenta AWS con permisos para EC2, S3, Cognito e IAM.
- Dominio (en Route53 o proveedor externo).
- Par de claves SSH y security group controlado.

## 3. Provision de EC2

Recomendado para inicio:

- Region: `us-east-1`
- AMI: Ubuntu LTS o Amazon Linux
- Tipo: `t3.small` o `t4g.small`
- Disco: 30-50 GB gp3

Puertos:
- 22 (SSH, restringido a IP admin)
- 80/443 (publico)

## 4. Servicios en EC2

Instalar:
- Docker + Docker Compose plugin
- Nginx
- Certbot (Let's Encrypt)

Estructura sugerida:

```txt
/opt/termovault/
  backend/
  web/
  docker-compose.yml
```

## 5. Backend y frontend en la misma EC2

- Backend Laravel corre como servicio interno (contenedor o php-fpm).
- Frontend se builda (`npm run build`) y Nginx sirve `web/dist`.
- Nginx proxy de `/api` hacia backend.

## 6. PostgreSQL local

- Motor en contenedor local o paquete del sistema.
- Solo escucha en red privada/local.
- No exponer 5432 publicamente.

Backup manual minimo recomendado:

```bash
pg_dump -U termovault -d termovault > /opt/backups/db-$(date +%F).sql
```

## 7. S3 para adjuntos

- Bucket privado con Block Public Access activado.
- App guarda `s3_key` y descarga via endpoint autorizado.
- Evitar URLs publicas directas.

## 8. Cognito

- User Pool con grupos `admin`, `supervisor`, `tecnico`.
- App Client para login.
- Variables en backend:
  - `COGNITO_REGION`
  - `COGNITO_USER_POOL_ID`
  - `COGNITO_APP_CLIENT_ID`
  - `COGNITO_APP_CLIENT_SECRET`
  - `COGNITO_AUTH_REQUIRED=true`

## 9. DNS y TLS

### Opcion A (Route53)
- Crear hosted zone y apuntar `app.tudominio.com` a la IP/ELastic IP.

### Opcion B (DNS externo)
- Crear registro A al Elastic IP de EC2.

TLS:
- Certbot + Let's Encrypt en Nginx.

## 10. Operacion basica

### Build frontend
```bash
cd /opt/termovault/web
npm ci
npm run build
```

### Migraciones backend
```bash
cd /opt/termovault/backend
php artisan migrate --force
```

### Reinicio servicios
```bash
docker compose -f /opt/termovault/docker-compose.yml up -d --build
sudo systemctl reload nginx
```

## 11. Checklist de hardening minimo

- `APP_ENV=production`
- `APP_DEBUG=false`
- SG cerrado (SSH solo IPs admin)
- Bucket S3 privado
- `COGNITO_AUTH_REQUIRED=true`
- Rotacion de secretos
- Backups verificados

## 12. Evolucion futura (cuando crezca)

Si sube volumen o criticidad:
- Separar DB a RDS
- Agregar ALB
- Pasar frontend a S3 + CloudFront
- Centralizar secretos en Secrets Manager
