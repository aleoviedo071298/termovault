# Guía de contribución — TermoVault

## Estrategia de ramas

```
main          ← rama estable. NUNCA se commitea directo. Solo merges via PR.
  ↑
  └─ feature/<nombre-corto>     para nuevas features
     fix/<nombre-corto>         para correcciones
     docs/<nombre-corto>        para cambios solo de docs
     chore/<nombre-corto>       para tareas de mantenimiento
     refactor/<nombre-corto>    para refactors
```

**Ejemplos:**
```
feature/setup-laravel
feature/login-cognito
fix/seed-elementos-fk
docs/api-endpoints
chore/dependabot-config
```

## Flujo de trabajo

```bash
# 1. Partir siempre de main actualizado
git checkout main
git pull origin main

# 2. Crear rama nueva
git checkout -b feature/mi-feature

# 3. Trabajar y commitear (varios commits chicos > uno gigante)
git add archivo.php
git commit -m "feat(auth): agrega middleware de validación de rol"

# 4. Pushear al remoto
git push -u origin feature/mi-feature

# 5. Abrir PR contra main desde GitHub o con gh
gh pr create --base main --title "feat(auth): middleware de rol" --body "..."

# 6. Mergear cuando los checks pasen y revisaste el diff
gh pr merge --squash  # squash = un solo commit limpio en main

# 7. Borrar la rama local
git checkout main
git pull
git branch -d feature/mi-feature
```

## Convención de commits — Conventional Commits

Formato:
```
<tipo>(<scope opcional>): <descripción imperativa>

[cuerpo opcional explicando por qué]

[footer opcional, ej: BREAKING CHANGE, Refs #123]
```

### Tipos válidos

| Tipo | Cuándo usar |
|---|---|
| `feat` | Nueva funcionalidad |
| `fix` | Corrección de bug |
| `docs` | Cambios en documentación |
| `style` | Formateo, sin cambio funcional |
| `refactor` | Refactor sin cambio de comportamiento |
| `perf` | Mejora de performance |
| `test` | Agregar o corregir tests |
| `chore` | Tareas de mantenimiento, deps, config |
| `ci` | Cambios en pipelines de CI/CD |
| `build` | Cambios al sistema de build |
| `revert` | Revertir un commit previo |

### Scopes recomendados en este proyecto

`db`, `backend`, `web`, `mobile`, `infra`, `auth`, `api`, `elementos`, `inspecciones`, `novedades`, `archivos`, `seed`, `docs`

### Ejemplos buenos

```
feat(elementos): agrega autocomplete de elementos por código
fix(seed): corrige FK rota en seed-elementos
docs(arquitectura): explica flujo de presigned URLs S3
chore(infra): inicializa terraform base de VPC
refactor(api): unifica respuestas de error en formato JSON:API
```

### Ejemplos malos (evitar)

```
update                          ← qué actualizaste?
fix bug                          ← qué bug?
WIP                              ← no commitear WIP a main
asdkfjsa                         ← obvio
arreglos varios                  ← describí cada cambio
```

## Pull Requests

- **Título del PR** sigue la misma convención que los commits.
- Llenar la **plantilla de PR** completa.
- Vincular al issue con `Closes #N` si aplica.
- Esperar que los checks de CI pasen.
- Hacerse **self-review** antes de mergear (mirar el diff completo).
- Mergear con **squash** para mantener `main` limpio.
- Borrar la rama feature al mergear.

## Setup local

Ver [`README.md`](README.md) y [`docs/07-deploy-aws.md`](docs/07-deploy-aws.md).

### Hooks de Git (obligatorio después de clonar)

El repo trae hooks que bloquean errores comunes (commits a `main`, secretos stageados, mensajes mal formateados). **Instalalos una vez después de clonar:**

```bash
./.githooks/install.sh
```

Eso configura `core.hooksPath = .githooks`. Los hooks viajan con el repo, así que cualquier colaborador que corra el script tiene la misma protección.

#### Hooks activos

| Hook | Qué valida |
|---|---|
| `pre-commit` | No commitear a `main`, no stagear `.env` / `*.tfvars` / claves privadas, no dejar markers de merge sin resolver, no commitear secretos detectables (AWS keys, GitHub tokens, etc.) |
| `commit-msg` | El mensaje sigue Conventional Commits (`feat`, `fix`, `docs`, etc.) |
| `pre-push` | No pushear directo a `main` (forzar flujo PR) |

#### Bypass excepcional

Si necesitás saltearte un hook por una razón legítima (ej: commit de emergencia, validación falsa), usá:

```bash
git commit --no-verify
git push --no-verify
```

⚠️ Esto debería ser **excepcional**, no rutinario. Si lo usás seguido, hay algo mal con el hook o con tu flujo.

## Versionado — SemVer

`MAJOR.MINOR.PATCH`

- **MAJOR**: cambios incompatibles (ej: 1.0 → 2.0)
- **MINOR**: features nuevas compatibles (ej: 0.1 → 0.2)
- **PATCH**: solo fixes (ej: 0.1.0 → 0.1.1)

Mientras estemos en `0.x.y`, todo es desarrollo pre-producción.

## Secretos y credenciales

🚨 **NUNCA** commitear:
- `.env` (ya está en `.gitignore`)
- `*.tfvars` (ya está en `.gitignore`)
- Claves AWS, contraseñas, tokens, API keys.
- Datos reales de clientes en seeds o tests.

Si commiteás un secreto por error:
1. **No basta con borrar el commit**. El secreto queda en el historial.
2. Rotar el secreto inmediatamente en el servicio (AWS, etc.).
3. Limpiar el historial con `git filter-repo` o BFG Repo-Cleaner.
4. Notificar al equipo.

Para producción, los secretos van en:
- **GitHub Actions** → Settings → Secrets and variables
- **AWS** → Secrets Manager o Parameter Store
