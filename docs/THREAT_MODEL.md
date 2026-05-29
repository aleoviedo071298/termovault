# THREAT_MODEL - TermoVault

## Scope

Internal multi-tenant thermography management platform for Oil and Gas operations.

## Assets to protect

- Tenant data (`empresa`, `yacimientos`, `elementos`, `inspecciones`, `novedades`).
- Attached files and reports.
- User identities and role assignments.
- Operational audit logs.

## Threats we explicitly address

- Unauthorized access across tenants.
- Role escalation by stale claims.
- Broken object-level authorization in file downloads and inspections.
- SQL injection through validated query paths.
- XSS-assisted token theft (partially mitigated with headers; residual risk remains with localStorage).
- CSRF on API endpoints (JWT bearer model, no cookie session auth for API).
- Timing information leakage in auth verification paths (JWT verification and exact identity matching).

## Threats out of scope / delegated controls

- Network eavesdropping (handled by HTTPS/TLS and infrastructure).
- Endpoint compromise on client devices.
- Cloud account takeover outside app controls.
- Malicious insiders with legitimate admin credentials.

## Security assumptions

- Cognito is trusted identity source.
- Infrastructure enforces TLS and secure network boundaries.
- Production secrets are not stored in repository.
- DB and object storage backups are controlled operationally.

## Residual risks

- Token storage in browser `localStorage` remains sensitive to XSS.
- Deep malware scanning of uploaded archives is not implemented.
- Log-based audit trail requires monitoring/retention discipline.

## Design decisions

- Admin bypass on element scope is intentional for enterprise operations.
- API returns generic auth errors to avoid implementation leakage.
- Download access is mediated by backend authorization, not direct bucket URLs.
