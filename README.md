# TermoVault

> Multi-tenant platform for managing thermographic inspection reports in the Oil & Gas industry.

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-61DAFB?style=for-the-badge&logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=for-the-badge&logo=typescript&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![AWS](https://img.shields.io/badge/AWS-232F3E?style=for-the-badge&logo=amazon-aws&logoColor=white)

## About

TermoVault allows thermographic inspection companies to manage their entire report workflow: upload infrared images, document findings, generate PDF reports, and share results with clients — all with strict tenant isolation so each company only sees its own data.

## Tech Stack

| Layer | Detail |
|---|---|
| **Backend** | Laravel 12 (API) |
| **Frontend** | React 19 + TypeScript, Vite 8 |
| **Database** | PostgreSQL 16 |
| **Auth** | AWS Cognito |
| **Infrastructure** | Docker Compose |

## Features

- **Multi-tenancy** — strict data isolation per organization.
- **Report management** — create, edit, and version thermographic inspection reports.
- **Role-based access** — Admin, Inspector, Viewer roles with scoped permissions.
- **PDF export** — generate client-ready inspection reports.
- **Image storage** — infrared image upload and association to findings.
- **AWS Cognito auth** — token-based authentication with role claims.

## Project Structure

```
termovault/
├── backend/         Laravel 12 API
│   ├── app/         Models, Controllers, Middleware, Policies
│   ├── routes/      api.php (all REST endpoints)
│   └── .env.example Environment config template
├── frontend/        React 19 + TypeScript + Vite 8
│   ├── src/
│   │   ├── features/    Feature modules
│   │   ├── components/  Shared UI components
│   │   └── api/         Axios client + typed hooks
│   └── vite.config.ts
└── docker-compose.yml
```

## Setup

```bash
# Full stack via Docker
docker compose up --build

# Backend only
cd backend
composer install
cp .env.example .env   # fill in DB and Cognito credentials
php artisan migrate

# Frontend only
cd frontend
npm install
npm run dev
```

---

**Alejandro Oviedo** · [LinkedIn](https://www.linkedin.com/in/aleoviedo071298/) · [GitHub](https://github.com/aleoviedo071298)
