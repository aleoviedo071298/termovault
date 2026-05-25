#!/usr/bin/env bash
# install.sh — instala los hooks de Git de este repo
# Corré una vez después de clonar:  ./.githooks/install.sh

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Verificar que estamos dentro de un repo git
if ! git rev-parse --git-dir > /dev/null 2>&1; then
  echo "✗ No estás dentro de un repositorio git."
  exit 1
fi

# Apuntar git a la carpeta .githooks del repo
git config core.hooksPath .githooks

# Asegurar permisos de ejecución (en *nix; en Windows no aplica pero no rompe)
chmod +x .githooks/pre-commit .githooks/commit-msg .githooks/pre-push 2>/dev/null || true

echo -e "${GREEN}✓ Hooks de Git instalados.${NC}"
echo ""
echo "Hooks activos:"
echo "  • pre-commit  → bloquea commits a main, valida secretos, conflictos"
echo "  • commit-msg  → valida formato Conventional Commits"
echo "  • pre-push    → bloquea push directo a main"
echo ""
echo -e "${YELLOW}Bypass excepcional:${NC} git commit --no-verify  |  git push --no-verify"
