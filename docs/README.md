# Documentación — TermoVault

Índice de la documentación interna del proyecto. Ordenado de lo más general a lo más operativo.

## Índice

| Doc | Contenido | Para quién |
|---|---|---|
| [`01-arquitectura.md`](./01-arquitectura.md) | Visión de alto nivel: stack, componentes, flujo de request, capas. | Nuevo dev que llega al proyecto. |
| [`02-modelo-datos.md`](./02-modelo-datos.md) | Tablas, jerarquía, decisiones de modelado, distribución de elementos, queries típicas. | Backend / DBA. |
| [`03-api.md`](./03-api.md) | Endpoints REST, request/response, matriz de permisos. | Frontend / consumers de la API. |
| [`04-decisiones.md`](./04-decisiones.md) | ADRs — cada decisión arquitectónica con contexto y consecuencias. | Tech leads y onboarding profundo. |
| [`05-roadmap.md`](./05-roadmap.md) | Qué se hizo, qué está en curso, qué viene. | Producto + dev. |
| [`06-seguridad.md`](./06-seguridad.md) | Modelo de auth, roles, checklist de hardening, procedimientos operativos. | DevOps / responsables de seguridad. |
| [`07-deploy-aws.md`](./07-deploy-aws.md) | Guía paso a paso para llevar el sistema a AWS. | DevOps. |
| [`08-multitenant.md`](./08-multitenant.md) | Cómo aísla TermoVault los datos entre clientes, garantías y límites. | Tech leads / sales engineering. |
| [`diagrama-er.md`](./diagrama-er.md) | Diagrama ER en Mermaid + diagramas de estados. | Backend / nuevo dev. |

## Documentos en la raíz del repo

Estos no están en `docs/` pero son parte del contrato del proyecto:

| Archivo | Contenido |
|---|---|
| [`../README.md`](../README.md) | Setup rápido + comandos básicos. **Leer primero.** |
| [`../NEXT_SESSION.md`](../NEXT_SESSION.md) | Estado actual y prompt base para continuar con otra IA. |
| [`../CHANGELOG.md`](../CHANGELOG.md) | Historial de releases (Keep a Changelog format). |
| [`../CONTRIBUTING.md`](../CONTRIBUTING.md) | Convenciones de git, commits, PRs. |

## Cómo mantener esta documentación

### Cuándo actualizar

- **Migración que cambia el schema** → actualizar `02-modelo-datos.md` + `diagrama-er.md` en el mismo PR.
- **Endpoint nuevo / removido** → actualizar `03-api.md`.
- **Decisión arquitectónica** → agregar entrada en `04-decisiones.md` con el siguiente número de ADR.
- **Cambio en flujo de auth o roles** → actualizar `06-seguridad.md` y `08-multitenant.md`.
- **Task completada del roadmap** → moverla a la sección "Done" en `05-roadmap.md` (o tachar y dejar nota).
- **Cambio en infra prod** → actualizar `07-deploy-aws.md`.

### Cuándo NO inventar

- No agregar specs de features que todavía no se aprobaron a `01-arquitectura.md`. Esa doc describe lo que existe.
- Si una decisión es tentativa, dejarla en `05-roadmap.md` (Wishlist), no en `04-decisiones.md`.
- Si un diagrama no se puede mantener actualizado, mejor borrarlo que dejarlo mintiendo.

### Estilo

- Español, tono técnico pero conversacional.
- Markdown estándar, con tablas y bloques de código tipados.
- Diagramas en Mermaid (renderizan en GitHub).
- Sin emojis salvo para iconos funcionales en checklists (✓ ✗ ⚠️).
- Snippets de código completos y ejecutables cuando sea posible.

## Orden de lectura sugerido

### Para empezar a desarrollar

1. `../README.md` — setup local.
2. `01-arquitectura.md` — entender el sistema.
3. `03-api.md` — entender el contrato del backend.
4. `02-modelo-datos.md` — entender la DB.

### Para resolver un bug

1. `../NEXT_SESSION.md` — qué estaba en curso.
2. `04-decisiones.md` — por qué algo está como está.
3. Código fuente.

### Para revisar seguridad / acceso

1. `06-seguridad.md` — modelo completo.
2. `08-multitenant.md` — cómo se aíslan los tenants.
3. `03-api.md` — matriz de permisos por endpoint.

### Para planificar un release

1. `05-roadmap.md` — qué está pendiente.
2. `../CHANGELOG.md` — qué ya salió.
3. `06-seguridad.md` (checklist pre-deploy) — qué validar antes de subir.

### Para llevar a producción AWS

1. `07-deploy-aws.md` — paso a paso.
2. `06-seguridad.md` (checklist) — hardening.
3. `05-roadmap.md` (sección "Now") — bloqueos críticos a resolver primero.
