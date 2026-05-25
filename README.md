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

## Estado

🚧 En desarrollo — estructura inicial.

Ver [`docs/05-roadmap.md`](docs/05-roadmap.md) para el plan por fases.
