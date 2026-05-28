# Migration Guide — TermoVault

Documentación de patrones complejos y fallbacks en migraciones de base de datos.

---

## Patrón: Schema Builder Fallback para PostgreSQL

### Problema

Laravel's `Schema::dropColumn()` falla en **PostgreSQL** cuando la columna tiene constraints implícitos o referencias indirectas, incluso si no están explícitamente declarados en el schema builder.

**Ejemplo:**
```php
// ❌ Falla en PostgreSQL
Schema::table('usuarios', function (Blueprint $table) {
    $table->dropColumn('legajo'); // Constraint issue!
});
```

### Por Qué Ocurre

PostgreSQL es **más estricto** que MySQL/SQLite:
- Valida constraints cascada a nivel de tabla
- `DROP COLUMN` falla si hay dependencias ocultas (índices, triggers, etc.)
- Laravel's dropColumn() no genera SQL lo suficientemente permisivo

### Solución: Doble Migración

**Migration 005** (Laravel Schema Builder):
```php
Schema::table('usuarios', function (Blueprint $table) {
    $table->dropColumn(['legajo', 'telefono', 'ultimo_login']);
});
```
- ✅ Funciona en SQLite y MySQL
- ⚠️ Puede fallar en PostgreSQL

**Migration 006** (PostgreSQL Fallback):
```php
if (DB::getDriverName() === 'pgsql') {
    DB::statement('ALTER TABLE usuarios DROP COLUMN IF EXISTS legajo');
    DB::statement('ALTER TABLE usuarios DROP COLUMN IF EXISTS telefono');
    DB::statement('ALTER TABLE usuarios DROP COLUMN IF EXISTS ultimo_login');
}
```
- ✅ Funciona en PostgreSQL (resuelve constraints)
- ✅ No-op en SQLite/MySQL (el DROP IF EXISTS ya ocurrió)

### Ventajas

| Aspecto | Beneficio |
|---------|-----------|
| **Compatibilidad** | Una base de código, múltiples engines |
| **Robustez** | IF EXISTS previene errores de re-run |
| **Documentación** | Código claramente documenta por qué existe |
| **Reversibilidad** | Ambas migraciones tienen `down()` |

### Aplicación

1. **Escribir migración 1** con Schema builder (intenta lo "ideal")
2. **Si falla en PostgreSQL**, crear migración 2 con SQL directo
3. **Documentar** por qué el fallback existe
4. **Ambas migraciones** viajan juntas a producción

---

## Patrones Actuales en TermoVault

### M7: Migraciones de Limpieza (Usuarios)

| Migración | Propósito | Patrón |
|-----------|-----------|--------|
| 005 | Drop columns genéricamente | Schema builder |
| 006 | Drop columns en PG | SQL directo fallback |
| 007 | Índices y constraints | Schema builder |
| 010 | Backfill audit fields | Raw INSERT/UPDATE |
| 011 | Drop password_hash | Schema builder |
| 012 | Drop users table | Schema builder |

**Decisión**: 005 + 006 viajan juntas; 007+ son independientes.

---

## Para el Futuro

### Checklist para Migraciones Complejas

- [ ] ¿Afecta múltiples tablas? → Documentar dependencias
- [ ] ¿DROP COLUMN en PostgreSQL? → Preparar SQL directo como fallback
- [ ] ¿Backfill masivo? → Usar chunking o raw queries, no Eloquent mass assignment
- [ ] ¿Cambio de constraints? → Verificar con `PRAGMA foreign_keys` en dev
- [ ] ¿Reversibilidad?  → Implementar `down()` completo
- [ ] ¿Comentarios?  → Agregar bloque doc al clase (WHY, no just WHAT)

### Comandos Útiles

```bash
# Listar todas las migraciones
php artisan migrate:status

# Rollback solo la última
php artisan migrate:rollback --step=1

# Rollback todo y re-run
php artisan migrate:refresh

# Generar nueva migración
php artisan make:migration nombre_de_la_migracion

# Verificar SQL que se ejecutará (sin ejecutar)
php artisan migrate --pretend
```

### Debugging PostgreSQL

```sql
-- Ver definición de tabla
\d usuarios

-- Ver constraints y índices
\d usuarios+

-- Ver columnas específicamente
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'usuarios';
```

---

## Referencias

- [Laravel Migrations](https://laravel.com/docs/11.x/migrations)
- [PostgreSQL ALTER TABLE](https://www.postgresql.org/docs/current/sql-altertable.html)
- [Schema Builder Limitations](https://laravel.com/docs/11.x/migrations#modifying-columns)
