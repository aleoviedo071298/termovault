# Testing en TermoVault Backend

Guide para ejecutar y escribir tests en el backend Laravel de TermoVault.

## Quick Start

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar solo Feature tests
php artisan test --filter Feature

# Ejecutar tests de un archivo
php artisan test tests/Feature/AuthTest.php

# Ejecutar con output verboso
php artisan test --verbose

# Ejecutar con coverage (si xdebug está habilitado)
php artisan test --coverage
```

## Estructura de Tests

```
backend/tests/
├── Feature/              # Integration tests
│   ├── AuthTest.php      # Autenticación, login, JWT
│   ├── ElementoTest.php  # CRUD elementos, permisos
│   └── InspeccionTest.php # Inspecciones, uploads, novedades
└── Unit/                 # Unit tests (vacío por ahora)
```

## Tests Implementados

### 1. AuthTest.php
Verifica autenticación y seguridad:

- ✅ Health endpoint es público
- ✅ Login rechaza credenciales inválidas
- ✅ Endpoints protegidos requieren token
- ✅ Token inválido es rechazado
- ✅ Rate limiting en login (máx 10 intentos/min)
- ✅ CORS preflight request funciona

**Comandos**:
```bash
php artisan test tests/Feature/AuthTest.php
php artisan test --filter "AuthTest"
php artisan test --filter "test_health_endpoint"
```

### 2. ElementoTest.php
Verifica CRUD de elementos y permisos:

- ✅ Técnico no puede crear elementos
- ✅ Admin puede crear elementos
- ✅ Validación de campos requeridos
- ✅ Elemento con inspecciones no puede eliminarse
- ✅ Elemento sin inspecciones puede eliminarse
- ✅ GET /api/elementos respeta scope
- ✅ UPDATE valida datos

**Comandos**:
```bash
php artisan test tests/Feature/ElementoTest.php
php artisan test --filter "ElementoTest"
php artisan test --filter "test_tecnico_cannot_create"
```

### 3. InspeccionTest.php
Verifica inspecciones y validaciones críticas:

- ✅ Técnico puede crear inspección
- ✅ **M9**: Estado enum validation (enviada|revisada|cerrada)
- ✅ Técnico solo ve sus propias inspecciones
- ✅ Supervisor puede cambiar estado
- ✅ Técnico no puede cambiar estado
- ✅ **M9**: File MIME validation (Word/Excel/ZIP only)
- ✅ File size limits (10MB reports, 50MB images)
- ✅ Images debe ser ZIP
- ✅ Novedades se crean como 'abierta'
- ✅ Cierre pasa novedades a 'resuelta'

**Comandos**:
```bash
php artisan test tests/Feature/InspeccionTest.php
php artisan test --filter "InspeccionTest"
php artisan test --filter "test_inspeccion_estado_enum"
```

## Hallazgos Críticos Testeados

| Hallazgo | Test | Status |
|----------|------|--------|
| M9: Estado enum validation | InspeccionTest::test_inspeccion_estado_enum_validation | ✅ |
| M9: File MIME validation | InspeccionTest::test_inspeccion_file_upload_mime_validation | ✅ |
| M9: File size limit | InspeccionTest::test_inspeccion_file_upload_size_limit | ✅ |
| Permisos por rol | ElementoTest + InspeccionTest | ✅ |
| Scope de datos | ElementoTest::test_elementos_list_respects_scope | ✅ |
| Auth tokens | AuthTest::test_protected_endpoint_requires_token | ✅ |

## Configuración

### .env para Tests
```bash
# En backend/.env
APP_ENV=testing  # Activar cuando ejecutes tests
DB_DATABASE=termovault_test  # DB separada para tests
CACHE_DRIVER=array  # No usar Redis en tests
```

**Nota**: Laravel resetea la DB automáticamente si usas `RefreshDatabase` trait.

### PHPUnit Configuration
Ver `backend/phpunit.xml` para configuración completa.

## Escribir Nuevos Tests

### Template básico
```php
<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MiTest extends TestCase
{
    use RefreshDatabase;  // Reset DB en cada test

    public function test_algo_funciona(): void
    {
        // Arrange
        $usuario = Usuario::factory()->create();

        // Act
        $response = $this->actingAs($usuario)
            ->getJson('/api/endpoint');

        // Assert
        $response->assertStatus(200);
    }
}
```

### Patterns Comunes

**Testear endpoint sin auth**:
```php
$response = $this->getJson('/api/public');
$response->assertStatus(200);
```

**Testear con usuario autenticado**:
```php
$usuario = Usuario::factory()->create();
$response = $this->actingAs($usuario)->getJson('/api/private');
```

**Testear validaciones**:
```php
$response = $this->postJson('/api/endpoint', [
    // datos inválidos
]);
$response->assertStatus(422);
$response->assertJsonPath('message', 'The given data was invalid.');
```

**Testear file uploads**:
```php
$file = UploadedFile::fake()->create('test.xlsx', 100);
$response = $this->postJson('/api/upload', [
    'file' => $file
]);
```

**Testear scope/permisos**:
```php
$admin = Usuario::factory()->create(['local_role' => 'admin']);
$tecnico = Usuario::factory()->create(['local_role' => 'tecnico']);

$response = $this->actingAs($tecnico)
    ->deleteJson("/api/admin/endpoint");
$response->assertStatus(403);
```

## CI/CD Integration

Para agregar a GitHub Actions:

```yaml
# .github/workflows/test-backend.yml
name: Backend Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: php artisan test
```

## Debugging Tests

**Ver output detallado**:
```bash
php artisan test --verbose
```

**Parar en primer error**:
```bash
php artisan test --bail
```

**Debug con artisan tinker**:
```bash
# En test, agregar:
$this->artisan('tinker')->run();  # Pausa ejecución
```

**Inspeccionar respuesta**:
```php
$response = $this->getJson('/api/endpoint');
dd($response->json());  // Dump and die
```

## Métricas

**Tests implementados**: 22  
**Cobertura objetivo**: > 70% rutas críticas  
**Siguiente paso**: Implementar en CI/CD

---

**Nota**: Estos tests cubren los flujos más críticos. El objetivo es tener una base sólida antes de crecer. Se recomienda agregar más tests según nuevas features se implementen.
