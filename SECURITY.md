# Security Policy

## Reporting a Vulnerability

If you find a security vulnerability in TermoVault, please report it privately — do not open a public issue.

- **Contact:** aleoviedo071298@gmail.com
- Include: affected component, steps to reproduce, and potential impact.
- You should receive an acknowledgement within a few days.

We'll work with you to confirm the issue, fix it, and coordinate disclosure timing before any public write-up.

## Scope

This repository is a single-deployment platform (no multiple supported versions). Reports about the codebase in `main`, the deployment scripts in `scripts/`, or the AWS configuration described in `docs/07-deploy-aws.md` are all in scope.

## Project security documentation

- [`docs/06-seguridad.md`](docs/06-seguridad.md) — auth model, roles, hardening checklist.
- [`docs/THREAT_MODEL.md`](docs/THREAT_MODEL.md) — threat model and residual risks.
- [`docs/INCIDENT_RESPONSE.md`](docs/INCIDENT_RESPONSE.md) — incident response playbook.
- [`docs/FRONTEND_SECURITY.md`](docs/FRONTEND_SECURITY.md) — frontend security controls (CSP, XSS, token storage).
- [`docs/ENV_SECRETS_HARDENING.md`](docs/ENV_SECRETS_HARDENING.md) — `.env`/secrets handling and file permissions.
