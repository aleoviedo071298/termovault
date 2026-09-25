# Documentacion - TermoVault

Indice de documentacion interna del proyecto.

## Indice

| Doc | Contenido |
|---|---|
| [`01-arquitectura.md`](./01-arquitectura.md) | Vision de alto nivel, componentes y flujo. |
| [`02-modelo-datos.md`](./02-modelo-datos.md) | Modelo de datos y relaciones. |
| [`03-api.md`](./03-api.md) | Endpoints y contratos API. |
| [`04-decisiones.md`](./04-decisiones.md) | ADRs y decisiones tecnicas. |
| [`05-roadmap.md`](./05-roadmap.md) | Estado y plan de trabajo. |
| [`06-seguridad.md`](./06-seguridad.md) | Auth, roles, hardening, rate limiting, CSRF, headers. |
| [`07-deploy-aws.md`](./07-deploy-aws.md) | Guia AWS vigente: EC2 unica + Postgres local + S3 + Cognito. |
| [`08-multitenant.md`](./08-multitenant.md) | Aislamiento de datos por tenant. |
| [`THREAT_MODEL.md`](./THREAT_MODEL.md) | Modelo de amenazas y riesgos residuales. |
| [`INCIDENT_RESPONSE.md`](./INCIDENT_RESPONSE.md) | Playbook de respuesta a incidentes. |
| [`FRONTEND_SECURITY.md`](./FRONTEND_SECURITY.md) | Medidas de seguridad y CSP del frontend React. |
| [`ENV_SECRETS_HARDENING.md`](./ENV_SECRETS_HARDENING.md) | Permisos y manejo de `.env`/secrets. |
| [`PERFORMANCE.md`](./PERFORMANCE.md) | Estrategia de optimización de performance y caching. |
| [`diagrama-er.md`](./diagrama-er.md) | Diagrama ER y estados. |

## Archivos clave en raiz

- [`../README.md`](../README.md) - setup rapido y comandos.
- [`../SECURITY.md`](../SECURITY.md) - politica de reporte de vulnerabilidades.
- [`../CHANGELOG.md`](../CHANGELOG.md) - historial de cambios.
- [`../CONTRIBUTING.md`](../CONTRIBUTING.md) - convenciones de colaboracion.

## Reglas de mantenimiento

- Si cambia DB: actualizar `02-modelo-datos.md` y `diagrama-er.md`.
- Si cambia API: actualizar `03-api.md`.
- Si cambia auth/roles: actualizar `06-seguridad.md` y `08-multitenant.md`.
- Si cambia infraestructura: actualizar `07-deploy-aws.md`.
