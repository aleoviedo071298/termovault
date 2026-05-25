# Descripción

<!-- ¿Qué hace este PR? ¿Por qué se hizo? -->

## Tipo de cambio

- [ ] feat — Nueva funcionalidad
- [ ] fix — Corrección de bug
- [ ] docs — Documentación
- [ ] refactor — Refactor sin cambio funcional
- [ ] chore — Tarea de mantenimiento
- [ ] ci — Cambios en CI/CD
- [ ] BREAKING CHANGE — Rompe compatibilidad

## Issue relacionado

Closes #

## ¿Cómo se probó?

<!-- Pasos para validar localmente. Comandos exactos si es posible. -->

```bash
# ejemplo
docker compose up -d
psql -h localhost -U postgres -d termovault -f database/schema.sql
```

## Checklist

- [ ] El título sigue [Conventional Commits](../CONTRIBUTING.md#convención-de-commits--conventional-commits).
- [ ] Hice **self-review** del diff completo.
- [ ] Los tests pasan localmente (cuando existan).
- [ ] El CI de GitHub Actions pasa.
- [ ] Actualicé documentación si correspondía (`docs/` y/o `README.md`).
- [ ] Actualicé `CHANGELOG.md` en la sección `[Unreleased]`.
- [ ] No commitéé secretos, archivos `.env` ni datos reales de clientes.
- [ ] Si toqué schema de DB, agregué la migration correspondiente.

## Screenshots / Evidencia

<!-- Opcional pero recomendado para cambios visibles -->

## Notas para el revisor

<!-- Cualquier contexto extra: decisiones tomadas, alternativas descartadas, deuda técnica asumida -->
