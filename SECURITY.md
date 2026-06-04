# 🔐 Guía de Seguridad — TermoVault

## Archivos Sensibles (.env)

Los archivos `.env` contienen **credenciales, claves API, tokens y secrets**. Deben estar protegidos.

### Permisos Recomendados

```
Archivo                          Permiso   Público?   Nota
─────────────────────────────────────────────────────────────
backend/.env                     600       ❌        Secretos de prod
backend/.env.example             644       ✅        Template, sin secrets
backend/.env.testing             600       ❌        Secrets de testing
web/.env.local                   600       ❌        Variables privadas
web/.env.development.local       600       ❌        Dev secrets
web/.env.example                 644       ✅        Template público
```

### Reforzar Permisos (Linux/macOS)

```bash
# Script automático (recomendado)
bash scripts/secure-env.sh

# O manualmente
chmod 600 backend/.env web/.env.local web/.env.development.local
chmod 644 backend/.env.example web/.env.example
```

### Verificar Permisos

```bash
ls -la backend/.env backend/.env.example
# Esperado:
# -rw------- 1 user group  ... backend/.env
# -rw-r--r-- 1 user group  ... backend/.env.example
```

### En Windows (NTFS)

Git Bash no fuerza permisos POSIX en NTFS. **Alternativas:**

1. **Usar WSL2:** Dentro de WSL, los permisos funcionan normalmente
   ```bash
   wsl bash scripts/secure-env.sh
   ```

2. **Ejecutar antes de push:**
   ```bash
   bash scripts/secure-env.sh
   ```

3. **Confiar en permisos de archivos del SO:**
   - NTFS: Los archivos `.env` están en `%USERPROFILE%` (solo tú tienes acceso)
   - En prod (Linux), sí se fuerzan los permisos

---

## En Producción

### Deploy Automático

Antes de que el servidor arranque, la migración fuerza:

```bash
# Via .env.example en prod
chmod 600 /var/www/termovault/backend/.env
chmod 600 /var/www/termovault/web/.env.local
```

### Auditoria

Verificá permisos en prod:

```bash
ssh user@prod "ls -la /var/www/termovault/backend/.env"
# Debe ser: -rw------- (600)
```

---

## Git & Secrets

### `.gitignore` (configurado ✅)

```gitignore
.env
.env.local
.env.*.local
*.env
```

**Nunca** commiteés:
- `.env` (credenciales reales)
- `.env.*.local` (secrets personales)

**Sí commiteés:**
- `.env.example` (template, sin valores)
- Código fuente (secretos inyectados via variables de entorno)

### Si Accidentalmente Commiteaste un Secret

```bash
# 1. Revertí el commit
git reset --soft HEAD~1

# 2. Sacá el archivo del staging
git restore --staged backend/.env

# 3. Creá uno nuevo sin el secret expuesto
# 4. Fuerza push (peligroso, requiere permisos)
git push --force-with-lease origin main

# 5. Rotá todos los secrets expuestos
```

---

## Checklist de Seguridad (.env)

Antes de hacer push:

- [ ] ¿Ejecuté `bash scripts/secure-env.sh`?
- [ ] ¿Verifiqué que `.env` no esté en git? (`git status | grep .env`)
- [ ] ¿Mi `.gitignore` incluye `.env`?
- [ ] ¿Los `.example` no tienen valores reales?
- [ ] ¿Las claves de Cognito/RDS solo están en `.env`, no en código?

---

## Variables de Entorno: Arquitectura

```
┌─ .env.example (PÚBLICO — versionado)
│  ├─ Estructura de variables
│  └─ Comentarios de configuración
│
├─ .env (PRIVADO — .gitignore)
│  ├─ RDS_PASSWORD=hunter2...
│  ├─ COGNITO_CLIENT_SECRET=xxx...
│  └─ JWT_SECRET=yyy...
│
├─ .env.development.local (PRIVADO — dev)
│  ├─ VITE_API_URL=http://localhost:8000
│  └─ VITE_DEBUG=true
│
└─ En CI/CD (GitHub Actions, etc.)
   └─ Secrets inyectados vía GitHub UI
```

**Flujo:**
1. Dev crea `.env.example` con variables sin valores
2. Dev crea `.env` local con valores reales
3. Git ignora `.env` (no va a repo)
4. Prod obtiene `.env` via:
   - Upload manual `scp`
   - CI/CD pipeline (secrets de GitHub)
   - Configuration management (Ansible, Terraform)

---

## Auditoría de Credenciales

### Buscar Secrets en el Código

```bash
# Usar truffleHog (si tenés hackingtool)
truffleHog filesystem . --debug

# O grep simple
grep -r "password\|secret\|token\|api_key" . \
  --include="*.php" --include="*.ts" --include="*.js" \
  --exclude-dir=node_modules --exclude-dir=vendor
```

### En CI/CD

Agregá escaneo en GitHub Actions (GitHub secretos scanning es automático).

---

## Resumen

| Acción | Comando | Por qué |
|--------|---------|--------|
| Reforzar permisos | `bash scripts/secure-env.sh` | `.env` no debe ser legible por otros usuarios |
| Verificar no está committed | `git log -p -- backend/.env \| head` | Si ves cambios, el secret está en historio |
| Verificar .gitignore | `git check-ignore backend/.env` | Debe retornar `.gitignore:2:*.env` |
| Antes de push | `git status \| grep .env` | Nada debe salir |
| Antes de deploy | `chmod 600 /path/to/.env` | Prod también lo necesita |

**→ Si seguís esto, los `.env` están seguros.** 🔐
