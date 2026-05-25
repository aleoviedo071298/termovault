# TermoVault

Sistema multi-tenant de gestión de inspecciones termográficas para instalaciones eléctricas en el sector Oil & Gas / energía.

## ¿Qué hace?

Permite a técnicos cargar inspecciones termográficas (informe Word + ZIP de imágenes + novedades) sobre elementos eléctricos (subestaciones, seccionadores 33/13.2 kV, bancos de capacitores, reconectadores), y a supervisores consultar todo el historial, novedades y estadísticas por elemento, yacimiento o empresa.

## Stack

- **Backend:** Laravel 11 + PostgreSQL 16
- **Frontend Web:** React + Vite + TailwindCSS
- **App móvil:** Flutter (Android primero)
- **Cloud:** AWS (EC2, RDS, S3, Cognito, CloudFront, Lambda)
- **Infra:** Terraform

## Estructura

```
termovault/
├── backend/       API Laravel
├── web/           Frontend React
├── mobile/        App Flutter
├── database/      SQL schema, migrations, seeds
├── infra/         Terraform + scripts AWS
└── docs/          Documentación técnica
```

## Roles

- `admin`      → Gestión total (empresas, usuarios, catálogos)
- `supervisor` → Ve todo, aprueba inspecciones, cierra novedades
- `tecnico`    → Carga inspecciones y archivos

## Setup local

### Requisitos
- Git
- Docker Desktop (para Postgres + MinIO + Adminer locales)
- (Opcional) `make` para comandos cortos

### Bootstrap

```bash
# 1. Clonar
git clone git@github.com:aleoviedo071298/termovault.git
cd termovault

# 2. Instalar hooks de Git
./.githooks/install.sh

# 3. Variables de entorno
cp .env.example .env

# 4. Levantar servicios
make up
# o sin make:
docker compose up -d
```

Eso te deja:

| Servicio | URL / Conexión | Credenciales |
|---|---|---|
| Postgres | `localhost:5432` | user `termovault` / pass `devsecret_cambiar_en_prod` |
| Adminer (DB UI) | http://localhost:8080 | autocompleto desde Adminer |
| MinIO API (S3) | http://localhost:9000 | `minioadmin` / `minioadmin_cambiar` |
| MinIO Console | http://localhost:9001 | mismas que API |

La DB arranca con **schema + 65 elementos seedados automáticamente**. Verificá con:

```bash
make status
# o
docker compose exec postgres psql -U termovault -d termovault \
  -c "SELECT funcion, COUNT(*) FROM elementos GROUP BY funcion ORDER BY funcion;"
```

Ver [`CONTRIBUTING.md`](CONTRIBUTING.md) para el flujo de trabajo completo.

## Estado

🚧 En desarrollo — estructura inicial.

Ver [`docs/05-roadmap.md`](docs/05-roadmap.md) para el plan por fases.
