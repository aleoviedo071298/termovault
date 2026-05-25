#!/usr/bin/env bash
# Corre el schema completo + todos los seeds en orden.
# Uso: ./seed-all.sh <db_name> [db_user] [db_host]
#
# Ejemplo:
#   ./seed-all.sh termovault postgres localhost

set -e

DB=${1:-termovault}
USER=${2:-postgres}
HOST=${3:-localhost}

echo "→ Aplicando schema en $DB..."
psql -h "$HOST" -U "$USER" -d "$DB" -f schema.sql

echo "→ Seed: catálogos (roles, tipos, tensiones, criticidades)..."
psql -h "$HOST" -U "$USER" -d "$DB" -f seed-roles.sql
psql -h "$HOST" -U "$USER" -d "$DB" -f seed-tipos-elemento.sql
psql -h "$HOST" -U "$USER" -d "$DB" -f seed-niveles-tension.sql
psql -h "$HOST" -U "$USER" -d "$DB" -f seed-criticidades.sql

echo "→ Seed: empresa y yacimiento default..."
psql -h "$HOST" -U "$USER" -d "$DB" -f seed-empresas.sql

echo "→ Seed: 65 elementos extraídos de los Words existentes..."
psql -h "$HOST" -U "$USER" -d "$DB" -f seed-elementos.sql

echo ""
echo "✓ Listo. Verificá con:"
echo "  psql -h $HOST -U $USER -d $DB -c \"SELECT funcion, COUNT(*) FROM elementos GROUP BY funcion;\""
