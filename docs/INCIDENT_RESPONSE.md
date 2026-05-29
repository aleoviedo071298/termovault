# INCIDENT_RESPONSE - TermoVault

## 1) Suspicious authentication failures

Signals:
- Spike in `auth.jwt.invalid_token`
- Repeated `auth.jwt.missing_token` from same IP/user-agent

Actions:
1. Confirm source IP patterns and endpoints targeted.
2. Verify Cognito availability and token issuer configuration.
3. Apply temporary WAF/reverse-proxy rate controls if needed.
4. Rotate compromised credentials/secrets if suspected.

## 2) Scope violation attempts

Signals:
- `auth.scope.violation` events
- Repeated denied mutations (`authz.denied.*`)

Actions:
1. Correlate user_id, role, target IDs.
2. Validate yacimiento assignments and role in DB.
3. If abuse is confirmed, disable user (`usuarios.activo=false`).
4. Preserve logs for audit trail and postmortem.

## 3) Rate limit abuse

Signals:
- High volume on `/api/auth/login`
- Burst traffic from narrow IP ranges

Actions:
1. Validate throttle behavior and upstream proxy limits.
2. Block abusive IP ranges temporarily.
3. Monitor for credential stuffing patterns.

## 4) File tampering / suspicious downloads

Signals:
- Unexpected volume in `auditoria_descargas_archivos`
- Download attempts outside normal shifts/users

Actions:
1. Correlate file, user, and timestamp from audit table.
2. Check object existence and integrity in storage.
3. Temporarily revoke user access if abuse suspected.
4. Rotate signed access strategy if direct leakage is suspected.

## 5) Recovery procedures

1. Stabilize access controls (disable compromised users, rotate secrets).
2. Restore service configuration from last known good state.
3. Validate DB integrity and file availability.
4. Run smoke tests:
   - login
   - role-scoped dashboard
   - inspection creation
   - authorized download
5. Document incident timeline and corrective actions.

## 6) Post-incident checklist

- Root cause identified and documented.
- Detection rule/alert added.
- Recovery runbook improved.
- Stakeholders informed with clear impact statement.
