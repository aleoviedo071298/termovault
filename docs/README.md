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
| [`06-seguridad.md`](./06-seguridad.md) | Auth, roles y hardening. |
| [`07-deploy-aws.md`](./07-deploy-aws.md) | Guia AWS vigente: EC2 unica + Postgres local + S3 + Cognito. |
| [`08-multitenant.md`](./08-multitenant.md) | Aislamiento de datos por tenant. |
| [`diagrama-er.md`](./diagrama-er.md) | Diagrama ER y estados. |

## Archivos clave en raiz

- [`../README.md`](../README.md) - setup rapido y comandos.
- [`../NEXT_SESSION.md`](../NEXT_SESSION.md) - contexto para continuar trabajo.
- [`../CHANGELOG.md`](../CHANGELOG.md) - historial de cambios.
- [`../CONTRIBUTING.md`](../CONTRIBUTING.md) - convenciones de colaboracion.

## Reglas de mantenimiento

- Si cambia DB: actualizar `02-modelo-datos.md` y `diagrama-er.md`.
- Si cambia API: actualizar `03-api.md`.
- Si cambia auth/roles: actualizar `06-seguridad.md` y `08-multitenant.md`.
- Si cambia infraestructura: actualizar `07-deploy-aws.md`.
