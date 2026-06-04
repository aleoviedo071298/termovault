#!/bin/bash
# Refuerza permisos de archivos .env sensibles
# Uso: bash scripts/secure-env.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

# Archivos sensibles que deben ser solo lectura del propietario (600)
SENSITIVE_FILES=(
    "backend/.env"
    "backend/.env.testing"
    "web/.env.local"
    "web/.env.development.local"
)

# Archivos templates que pueden ser públicos (644)
PUBLIC_TEMPLATES=(
    "backend/.env.example"
    "backend/.env.testing.example"
    "web/.env.example"
    ".env.example"
)

echo "🔐 Reforzando permisos de archivos .env..."

for file in "${SENSITIVE_FILES[@]}"; do
    if [ -f "$REPO_ROOT/$file" ]; then
        chmod 600 "$REPO_ROOT/$file"
        echo "✅ $file → 600 (solo dueño)"
    fi
done

for file in "${PUBLIC_TEMPLATES[@]}"; do
    if [ -f "$REPO_ROOT/$file" ]; then
        chmod 644 "$REPO_ROOT/$file"
        echo "✅ $file → 644 (template público)"
    fi
done

echo ""
echo "📋 Verificación de permisos:"
for file in "${SENSITIVE_FILES[@]}" "${PUBLIC_TEMPLATES[@]}"; do
    if [ -f "$REPO_ROOT/$file" ]; then
        ls -lh "$REPO_ROOT/$file" | awk '{print $1 " " $NF}'
    fi
done

echo ""
echo "✨ Completado. Los archivos .env están protegidos."
