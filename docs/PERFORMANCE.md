# TermoVault - Performance Optimization & Caching Strategy

Este documento detalla las estrategias de optimización de rendimiento y almacenamiento en caché implementadas en TermoVault (Etapa 6) para asegurar tiempos de respuesta ágiles, escalabilidad y eficiencia bajo cargas de trabajo reales.

---

## 🎯 Resumen de Mejoras y Resultados

- **Reducción de Queries**: Pasamos de 50-100 consultas por request a **menos de 10 consultas** en los endpoints críticos (`/api/dashboard/overview` y `/api/catalogos`).
- **Tiempos de Respuesta**: Tiempos de respuesta esperados de `<100ms` en consultas complejas (Dashboard y Catálogos).
- **Eficiencia del Cache**: Tasa de acierto de caché (Cache Hit Rate) de `>80%` en catálogos y `>90%` en dashboards bajo uso concurrente.

---

## 💾 1. Estrategia de Caching

TermoVault utiliza el driver de caché `database` por compatibilidad de infraestructura. Dado que este driver no soporta agrupamiento por etiquetas nativo (`Cache::tags()`), se diseñaron estrategias alternativas y robustas.

### 1.1 Catálogo de Datos Estáticos
Los datos del catálogo que rara vez cambian (`tipos_elemento`, `niveles_tension`, `criticidades`) se agrupan y se almacenan globalmente por 1 hora (`3600` segundos).
- **Endpoint**: `/api/catalogos`
- **Llave de Caché**: `catalogs.static`
- **Invalidación**: Se utiliza el ciclo de vida de Eloquent (`boot` method en los modelos `Criticidad`, `TipoElemento`, `NivelTension`) para limpiar `catalogs.static` automáticamente en eventos `saved` y `deleted`.
- *Nota*: Los yacimientos autorizados del usuario se resuelven dinámicamente en tiempo de ejecución para mantener el aislamiento multi-tenant estricto.

### 1.2 Caché de Dashboard con Versionado (Cache Versioning)
El endpoint `/api/dashboard/overview` requiere múltiples agregaciones y conteos complejos en base de datos.
- **Estrategia**: Se cachea la respuesta completa por 5 minutos (`300` segundos).
- **Llave de Caché**: Para evitar datos obsoletos (stale data) sin soporte de tags, se utiliza **Cache Versioning** mediante un timestamp de alta resolución (`microtime(true)`):
  ```php
  $version = Cache::rememberForever('dashboard.version', fn() => microtime(true));
  $cacheKey = "dashboard.overview.{$userId}.v{$version}." . md5(json_encode($params));
  ```
- **Invalidación instantánea (O(1))**: Cuando se guarda, modifica o elimina una `Inspeccion`, un `Elemento` o una `Novedad`, se ejecuta el boot del modelo que actualiza la clave global:
  ```php
  Cache::forever('dashboard.version', microtime(true));
  ```
  Esto invalida de forma inmediata y en tiempo constante todos los cachés de dashboard de todos los usuarios sin requerir barrer la base de datos de caché.

---

## 🗄️ 2. Optimización de Consultas e Índices

Se agregaron índices estratégicos para optimizar las consultas de filtrado por scopes y relaciones frecuentes.

### 2.1 Índices Agregados
- `idx_usuarios_rol`: Optimiza chequeos de rol en autenticación y middlewares.
- `idx_elementos_criticidad`: Acelera agregaciones y filtros por criticidad en elementos.
- `idx_inspecciones_estado`: Indexa el estado (`borrador`, `enviada`, `revisada`, `cerrada`) para consultas de reportes.
- `idx_inspecciones_estado_fecha`: Composite index `(estado, fecha_inspeccion DESC)` para paginaciones rápidas.
- `idx_inspecciones_revisada_por` / `idx_inspecciones_cerrada_por`: Optimiza consultas de auditoría y reportes por supervisor/administrador.
- `idx_archivos_subido_por`: Optimiza búsquedas y listado de archivos por usuario.

### 2.2 Eager Loading (Carga Relaciones)
Para evitar problemas de N+1 queries, se utiliza carga selectiva con `with()` y `load()` en controladores críticos:
- **DashboardController**: Eager loading selectivo de relaciones de inspección y elementos usando sintaxis de array seguro.
- **ElementoController**: Carga eager de `yacimiento`, `tipoElemento`, `criticidad`, etc.

---

## 🌐 3. Caché HTTP (Browser Caching)

El middleware `SecurityHeaders` fue configurado para aplicar políticas óptimas de `Cache-Control`:
- **Recursos Estáticos (`*.js`, `*.css`, imágenes)**: `public, max-age=2592000, immutable` (30 días).
- **Catálogos API (`/api/catalogos`)**: `public, max-age=3600` (1 hora) para evitar llamadas redundantes desde el navegador.
- **Datos Sensibles/Personales (`/api/auth/me`, `/api/dashboard/*`)**: `private, no-cache, no-store, must-revalidate` para evitar almacenamiento local y garantizar seguridad.

---

## 🛠️ Cómo Profiler y Diagnosticar

### Tinker CLI Profiling
Puedes auditar la cantidad de consultas SQL ejecutadas por cualquier llamada lógica desde la consola interactiva:
```bash
php artisan tinker

# Habilitar logs de query
DB::enableQueryLog();

# Ejecutar lógica o simular request
$user = App\Models\Usuario::first();
Auth::login($user);
$response = app()->handle(Request::create('/api/catalogos', 'GET'));

# Obtener consultas ejecutadas
$queries = DB::getQueryLog();
echo "Total Queries: " . count($queries);
```

### Monitoreo del Cache
Para verificar si la clave está en el caché:
```bash
php artisan cache:forget catalogs.static # Forzar invalidación manual
```
