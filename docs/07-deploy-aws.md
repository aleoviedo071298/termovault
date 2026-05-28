# Deploy en AWS — TermoVault

> Guía operativa para llevar TermoVault de local a producción en AWS.

## Arquitectura target

```
                  ┌───────────────────────────────┐
                  │  CloudFront                   │
                  │   ├─ /        → S3 (frontend) │
                  │   └─ /api/*   → ALB           │
                  └────────────┬──────────────────┘
                               │
            ┌──────────────────┴──────────────────┐
            │                                     │
   ┌────────▼─────────┐                  ┌────────▼─────────┐
   │  S3 (frontend)   │                  │  ALB             │
   │  build estático  │                  │  + WAF + SSL     │
   └──────────────────┘                  └────────┬─────────┘
                                                  │
                                         ┌────────▼─────────┐
                                         │  ECS / EC2       │
                                         │  Laravel API     │
                                         └────────┬─────────┘
                                                  │
                  ┌───────────────────────────────┴───────────────────────┐
                  │                                                       │
        ┌─────────▼─────────┐                                  ┌──────────▼──────┐
        │  RDS Postgres 16  │                                  │  S3 (archivos)  │
        │  Single-AZ        │                                  │   privado       │
        └───────────────────┘                                  └─────────────────┘

                  ┌────────────────────────┐
                  │  Cognito User Pool     │
                  │   + App Client         │
                  └────────────────────────┘
```

Componentes que se autoaprovisionan via Terraform (cuando los módulos estén implementados; ver `infra/terraform/`):

- VPC + subnets pública/privada en 2 AZs.
- Security groups: ALB (80/443 público), EC2/ECS (8000 desde ALB), RDS (5432 desde EC2/ECS).
- IAM roles least-privilege.
- Cognito User Pool + App Client + grupos (`admin`, `supervisor`, `tecnico`).

---

## Pre-requisitos

### Cuenta AWS

- Cuenta con permisos administrativos (IAM, VPC, RDS, S3, EC2/ECS, Cognito, CloudFront, ACM, Route53).
- Dominio comprado o gestionado en Route53 (o externo apuntando a CloudFront).

### Local

- AWS CLI configurado con el perfil correcto.
- Terraform ≥ 1.6 (cuando los módulos estén listos).
- Docker para builds locales si se usa ECS con ECR.

---

## Setup inicial (manual, primera vez)

### 1. Cognito User Pool

1. Consola AWS → Cognito → Create user pool.
2. Configuración recomendada:
   - Sign-in: Email (case-insensitive).
   - Password policy: min 12 chars, requiere mayúsculas, minúsculas, números, símbolos.
   - MFA: Optional (obligatoria recomendada para admins).
   - User account recovery: Email only.
   - Self-service sign-up: **Disabled** (los admins crean usuarios).
3. Crear App Client:
   - Auth flows: `USER_PASSWORD_AUTH` + `REFRESH_TOKEN_AUTH`.
   - Refresh token validity: 30 días.
   - Access token validity: 1 hora.
   - **Generate client secret**: ✓ (tomar nota, ir a Secrets Manager).
4. Crear grupos: `admin`, `supervisor`, `tecnico`.
5. Atributos custom (opcionales pero recomendados):
   - `custom:empresa_id` — para que el JWT traiga la empresa directo.
   - `custom:role` — fallback para los grupos.

### 2. RDS Postgres

1. RDS → Create database → PostgreSQL 16.
2. Instance class: `db.t4g.small` o superior según volumen.
3. Storage: 20 GB gp3 con autoscaling habilitado.
4. **Publicly accessible: No.**
5. VPC: la VPC de la app.
6. Multi-AZ: recomendado para prod.
7. Backup retention: 7 días.
8. Encryption at rest: ✓.
9. Tomar nota del endpoint, usuario, password (ir a Secrets Manager).

### 3. S3 bucket de archivos

1. S3 → Create bucket → `termovault-prod-files-{cuenta-id}`.
2. **Block all public access**: ✓.
3. Versioning: enabled.
4. Encryption: SSE-S3.
5. Lifecycle: transitar a Standard-IA después de 30 días, Glacier después de 365 días.
6. CORS:
   ```json
   [{
     "AllowedHeaders": ["*"],
     "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
     "AllowedOrigins": ["https://app.tudominio.com"],
     "ExposeHeaders": ["ETag"]
   }]
   ```

### 4. S3 + CloudFront para frontend

1. Bucket `termovault-prod-web` con `Block all public access` ✓.
2. CloudFront distribution:
   - Origin: el bucket (con OAC).
   - Default behavior: cache estático, redirect HTTP→HTTPS.
   - Custom behavior `/api/*` → ALB (no-cache, forward Authorization header).
3. SSL cert via ACM (us-east-1).
4. Route53 alias del dominio a CloudFront.

### 5. Secrets Manager

Crear secretos:

- `termovault/prod/db` → `{"username":"...","password":"...","host":"...","port":5432,"database":"termovault"}`.
- `termovault/prod/cognito` → `{"client_id":"...","client_secret":"...","user_pool_id":"...","region":"us-east-2"}`.
- `termovault/prod/app` → `{"key":"base64:..."}` (Laravel APP_KEY).

### 6. ECS / EC2 backend

#### Opción A: EC2

1. AMI Amazon Linux 2023.
2. Instance: `t4g.small`.
3. User data instala PHP 8.3 + composer + nginx + supervisor.
4. IAM role: read de Secrets Manager + read/write del bucket de archivos.
5. Deploy:
   ```bash
   git clone <repo>
   cd termovault/backend
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan migrate --force
   ```

#### Opción B: ECS Fargate (recomendado para escala)

1. Dockerfile en `backend/`:
   ```dockerfile
   FROM php:8.3-fpm-alpine
   # install dependencies, copy code, run composer install
   ```
2. Imagen empujada a ECR.
3. Task definition con vars desde Secrets Manager.
4. Service detrás del ALB.

### 7. CI/CD

GitHub Actions workflow (referencia, no commiteado todavía):

```yaml
name: Deploy
on:
  push:
    branches: [main]
jobs:
  build-and-deploy-backend:
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-dev
      - run: docker build -t termovault-api .
      - run: aws ecr push ...
      - run: aws ecs update-service ...
      - run: php artisan migrate --force  # vía task one-off

  build-and-deploy-web:
    steps:
      - uses: actions/checkout@v4
      - run: npm ci
      - run: npm run build
      - run: aws s3 sync web/dist/ s3://termovault-prod-web/
      - run: aws cloudfront create-invalidation --distribution-id ... --paths '/*'
```

---

## `.env` de producción (referencia)

```
APP_NAME=TermoVault
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.tudominio.com
APP_KEY=base64:...                  # de Secrets Manager

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=...                          # de Secrets Manager
DB_PORT=5432
DB_DATABASE=termovault
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=                   # vacío si se usa IAM role
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-2
AWS_BUCKET=termovault-prod-files-xxxxxxxxx
AWS_URL=https://termovault-prod-files-xxxxxxxxx.s3.us-east-2.amazonaws.com
AWS_USE_PATH_STYLE_ENDPOINT=false

COGNITO_REGION=us-east-2
COGNITO_USER_POOL_ID=us-east-2_xxxxxxxxx
COGNITO_APP_CLIENT_ID=xxxxxxxxxxxxx
COGNITO_APP_CLIENT_SECRET=...         # de Secrets Manager
COGNITO_AUTH_REQUIRED=true            # NUNCA false en prod
COGNITO_JWT_LEEWAY=60
```

---

## Procedimientos operativos

### Crear el primer admin

Como el provisioning de usuario local pasa en el primer login Cognito, el flujo es:

1. En Cognito: crear usuario manual con email + temporary password + asignarlo al grupo `admin`.
2. Mandarle al admin la URL de login.
3. Primer login: el frontend hace login → Cognito devuelve challenge `NEW_PASSWORD_REQUIRED` → completa password permanente → backend recibe el JWT con `cognito:groups: ["admin"]` → `LocalUserProvisioner` crea la fila en `usuarios` con `rol_id=admin`.

### Aplicar migraciones nuevas

```bash
# Opción A — ECS: ejecutar como one-off task
aws ecs run-task --cluster termovault \
  --task-definition termovault-migrate \
  --launch-type FARGATE

# Opción B — EC2: SSH y correr
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
```

### Rotar el client secret de Cognito

1. Consola Cognito → User pool → App client → Create new app client secret.
2. Actualizar el valor en Secrets Manager `termovault/prod/cognito`.
3. Forzar restart del backend (ECS service update / EC2 supervisor reload).
4. Verificar login con un usuario de prueba.

### Restaurar un backup de la DB

```bash
# Descargar el dump
aws s3 cp s3://termovault-prod-backups/db-2026-05-27.dump .

# Restaurar (a DB nueva, NUNCA pisar prod sin validar)
pg_restore --no-owner --no-privileges \
  -h <new-rds-endpoint> -U termovault -d termovault \
  db-2026-05-27.dump
```

### Forzar logout de todos los usuarios

```bash
# Invalida todos los refresh tokens del pool
aws cognito-idp admin-user-global-sign-out \
  --user-pool-id us-east-2_xxxxxxxxx \
  --username user@example.com
```

Repetir por usuario, o usar un script con `list-users` + loop.

---

## Costos estimados (orden de magnitud)

Para un yacimiento con ~1000 elementos, ~50 inspecciones/mes:

| Servicio | Especificación | Costo aprox /mes USD |
|---|---|---|
| RDS Postgres `db.t4g.small` Multi-AZ | 20 GB | 40–55 |
| ECS Fargate 1 task 0.5 vCPU + 1 GB | 24/7 | 18–25 |
| ALB | tráfico bajo | 17–22 |
| S3 archivos | 10 GB + 100 GB transfer | 5–10 |
| CloudFront | 50 GB transfer | 4–6 |
| Cognito | < 50k MAU | gratis |
| CloudWatch logs + WAF básico | | 5–10 |
| **Total** | | **~90–130 USD/mes** |

---

## Recovery / Disaster

### RTO / RPO objetivo

- RTO (Recovery Time Objective): 4 horas.
- RPO (Recovery Point Objective): 24 horas (backup diario).

### Plan

1. **Pérdida del backend**: ECS auto-restart. Si la imagen está corrupta, rollback al tag anterior en ECR.
2. **Pérdida de la DB**: restore del último snapshot automático de RDS (PITR habilitado da granularidad de 5 min). Tiempo: 10–30 min.
3. **Pérdida del bucket de archivos**: versioning permite restore de objetos individuales. Si pérdida total: restore desde cross-region replica (configurar).
4. **Cuenta AWS comprometida**: contacto AWS Support; rotar todas las credenciales; revisar CloudTrail.

### Backup cross-region (recomendado)

- Snapshot manual mensual de RDS replicado a otra región (e.g. `us-west-2`).
- Replication rule del bucket de archivos a un bucket en otra región.

---

## Documentación adicional

- `docs/06-seguridad.md` — checklist de hardening completo y matriz de permisos.
- `docs/01-arquitectura.md` — diagrama de componentes.
- `infra/terraform/` — módulos IaC (pendientes de implementación, hoy vacíos).
- `Makefile` del repo — comandos comunes de dev local que se traducen a equivalente en prod.
