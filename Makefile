# TermoVault — comandos comunes de desarrollo
#
# Uso:  make <target>
#       make help    para ver todos los targets disponibles
#
# En Windows: instalá `make` con `winget install GnuWin32.Make`
# o corré los comandos directos (están abajo de cada target).

.PHONY: help up down restart logs psql adminer minio reset clean status test test-watch test-coverage

help: ## Lista todos los comandos disponibles
	@echo "Comandos disponibles:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Levanta todos los servicios (Postgres, MinIO, Adminer)
	docker compose up -d
	@echo ""
	@echo "✓ Servicios arriba:"
	@echo "  Postgres:  localhost:5433 (user: termovault, db: termovault)"
	@echo "  Adminer:   http://localhost:8080"
	@echo "  MinIO UI:  http://localhost:9001 (user: minioadmin)"

down: ## Para todos los servicios (mantiene datos)
	docker compose down

restart: ## Reinicia todos los servicios
	docker compose restart

logs: ## Sigue los logs de todos los servicios
	docker compose logs -f --tail=50

logs-db: ## Solo logs de Postgres
	docker compose logs -f --tail=50 postgres

psql: ## Abre una shell psql contra el Postgres del compose
	docker compose exec postgres psql -U termovault -d termovault

status: ## Muestra el estado de los servicios y conteos de DB
	@docker compose ps
	@echo ""
	@echo "→ Conteo de elementos seedados:"
	@docker compose exec -T postgres psql -U termovault -d termovault \
		-c "SELECT funcion, COUNT(*) FROM elementos GROUP BY funcion ORDER BY funcion;" 2>/dev/null \
		|| echo "  (Postgres no responde todavía)"

reset: ## ⚠️  Borra TODOS los datos (volumes) y vuelve a aplicar schema + seeds
	docker compose down -v
	docker compose up -d
	@echo ""
	@echo "✓ Postgres recreado con schema fresco y 65 elementos seedados"

clean: ## Borra contenedores, volumes y red (sin tocar imágenes)
	docker compose down -v --remove-orphans

# ─── Testing ───────────────────────────────────────────────────────────────

test: ## Ejecuta todos los tests del backend
	cd backend && php artisan test

test-watch: ## Ejecuta tests en modo watch (rerun on file change)
	cd backend && php artisan test --watch

test-coverage: ## Ejecuta tests con coverage (requiere xdebug)
	cd backend && php artisan test --coverage --min=70

test-feature: ## Solo Feature tests
	cd backend && php artisan test --filter Feature

test-unit: ## Solo Unit tests
	cd backend && php artisan test --filter Unit

test-verbose: ## Tests con output verboso
	cd backend && php artisan test --verbose

test-auth: ## Solo tests de autenticación
	cd backend && php artisan test tests/Feature/AuthTest.php

test-elemento: ## Solo tests de elementos
	cd backend && php artisan test tests/Feature/ElementoTest.php

test-inspeccion: ## Solo tests de inspecciones
	cd backend && php artisan test tests/Feature/InspeccionTest.php

# ─── Comandos sin make (copiá y pegá si no tenés make en Windows) ───────────
# up:        docker compose up -d
# down:      docker compose down
# psql:      docker compose exec postgres psql -U termovault -d termovault
# reset:     docker compose down -v && docker compose up -d
# test:      cd backend && php artisan test
# test-auth: cd backend && php artisan test tests/Feature/AuthTest.php
