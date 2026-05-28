#!/bin/bash
# ============================================================
# Init script de Postgres para docker-compose.
# Se ejecuta UNA SOLA VEZ cuando el volumen está vacío.
# Lee los .sql desde /sql (montado read-only) y los aplica en orden.
#
# IMPORTANT: schema.sql is a GENERATED SNAPSHOT, not manually maintained.
# See scripts/regenerate-schema-sql.md for how to keep it in sync with migrations.
# Source of truth: Laravel migrations (backend/database/migrations/)
# ============================================================

set -e

SQL_DIR=/sql
PSQL="psql -v ON_ERROR_STOP=1 --username $POSTGRES_USER --dbname $POSTGRES_DB"

echo "→ Aplicando schema (snapshot de migraciones)..."
$PSQL -f "$SQL_DIR/schema.sql"

echo "→ Aplicando seeds (catálogos)..."
$PSQL -f "$SQL_DIR/seed-roles.sql"
$PSQL -f "$SQL_DIR/seed-tipos-elemento.sql"
$PSQL -f "$SQL_DIR/seed-niveles-tension.sql"
$PSQL -f "$SQL_DIR/seed-criticidades.sql"

echo "→ Aplicando seeds (empresa + yacimiento default)..."
$PSQL -f "$SQL_DIR/seed-empresas.sql"

echo "→ Aplicando seed de elementos (PAE yacimiento)..."
$PSQL -f "$SQL_DIR/seed-elementos.sql"

echo "→ Verificación:"
$PSQL -c "SELECT funcion, COUNT(*) FROM elementos GROUP BY funcion ORDER BY funcion;"
TOTAL=$($PSQL -tA -c "SELECT COUNT(*) FROM elementos;")
echo "✓ Total elementos: $TOTAL (1294 elementos del yacimiento PAE)"
