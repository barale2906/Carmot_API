# Lista de Verificación - Implementación del Submódulo de Listas de Precios

Este documento contiene el paso a paso numerado de todas las actividades necesarias para implementar el submódulo de Listas de Precios según el diseño establecido en `DISENO_MODULO_FINANCIERO_LISTAS_PRECIOS.md`.

## Comandos de Terminal para Crear Archivos

Ejecuta los siguientes comandos en orden para crear todos los archivos necesarios. Una vez creados, avisa para iniciar con la implementación.

### Crear Estructura de Directorios

```bash
# Crear directorios necesarios
mkdir -p app/Traits/Financiero
mkdir -p app/Models/Financiero/Lp
mkdir -p app/Services/Financiero
mkdir -p app/Http/Controllers/Api/Financiero/Lp
mkdir -p app/Http/Requests/Api/Financiero/Lp
mkdir -p app/Http/Resources/Api/Financiero/Lp
mkdir -p app/Console/Commands/Financiero
```

### Crear Migraciones

```bash
# Migración para tipos de producto
php artisan make:migration create_lp_tipos_producto_table

# Migración para productos
php artisan make:migration create_lp_productos_table

# Migración para listas de precios
php artisan make:migration create_lp_listas_precios_table

# Migración para relación lista_precio_poblacion
php artisan make:migration create_lp_lista_precio_poblacion_table

# Migración para precios de productos
php artisan make:migration create_lp_precios_producto_table
```

### Crear Modelos

```bash
# Modelo TipoProducto
php artisan make:model Financiero/Lp/LpTipoProducto

# Modelo Producto
php artisan make:model Financiero/Lp/LpProducto

# Modelo ListaPrecio
php artisan make:model Financiero/Lp/LpListaPrecio

# Modelo PrecioProducto
php artisan make:model Financiero/Lp/LpPrecioProducto
```

### Crear Seeders

```bash
# Seeder para tipos de producto
php artisan make:seeder LpTipoProductoSeeder

# Seeder opcional para productos
php artisan make:seeder LpProductoSeeder
```

### Crear Requests (Validación)

```bash
# Requests para TipoProducto
php artisan make:request Api/Financiero/Lp/StoreLpTipoProductoRequest
php artisan make:request Api/Financiero/Lp/UpdateLpTipoProductoRequest

# Requests para Producto
php artisan make:request Api/Financiero/Lp/StoreLpProductoRequest
php artisan make:request Api/Financiero/Lp/UpdateLpProductoRequest

# Requests para ListaPrecio
php artisan make:request Api/Financiero/Lp/StoreLpListaPrecioRequest
php artisan make:request Api/Financiero/Lp/UpdateLpListaPrecioRequest

# Requests para PrecioProducto
php artisan make:request Api/Financiero/Lp/StoreLpPrecioProductoRequest
php artisan make:request Api/Financiero/Lp/UpdateLpPrecioProductoRequest
```

### Crear Resources (Transformadores)

```bash
# Resources para transformar respuestas
php artisan make:resource Api/Financiero/Lp/LpTipoProductoResource
php artisan make:resource Api/Financiero/Lp/LpProductoResource
php artisan make:resource Api/Financiero/Lp/LpListaPrecioResource
php artisan make:resource Api/Financiero/Lp/LpPrecioProductoResource
```

### Crear Controladores

```bash
# Controladores con recursos API
php artisan make:controller Api/Financiero/Lp/LpTipoProductoController --api
php artisan make:controller Api/Financiero/Lp/LpProductoController --api
php artisan make:controller Api/Financiero/Lp/LpListaPrecioController --api
php artisan make:controller Api/Financiero/Lp/LpPrecioProductoController --api
```

### Crear Comando de Consola

```bash
# Comando para gestionar estados de listas de precios
php artisan make:command Financiero/ActivarListasPreciosAprobadas
```

### Archivos a Crear Manualmente

Los siguientes archivos deben crearse manualmente (no tienen comando artisan):

```bash
# Trait personalizado para estados de lista de precios
touch app/Traits/Financiero/HasListaPrecioStatus.php

# Servicio de lógica de negocio
touch app/Services/Financiero/LpPrecioProductoService.php

# Archivo de rutas (si no existe)
touch routes/financiero.php
```

### Resumen de Comandos Completos

Copia y pega este bloque completo en tu terminal:

```bash
# Crear directorios
mkdir -p app/Traits/Financiero
mkdir -p app/Models/Financiero/Lp
mkdir -p app/Services/Financiero
mkdir -p app/Http/Controllers/Api/Financiero/Lp
mkdir -p app/Http/Requests/Api/Financiero/Lp
mkdir -p app/Http/Resources/Api/Financiero/Lp
mkdir -p app/Console/Commands/Financiero

# Crear migraciones
php artisan make:migration create_lp_tipos_producto_table
php artisan make:migration create_lp_productos_table
php artisan make:migration create_lp_listas_precios_table
php artisan make:migration create_lp_lista_precio_poblacion_table
php artisan make:migration create_lp_precios_producto_table

# Crear modelos
php artisan make:model Financiero/Lp/LpTipoProducto
php artisan make:model Financiero/Lp/LpProducto
php artisan make:model Financiero/Lp/LpListaPrecio
php artisan make:model Financiero/Lp/LpPrecioProducto

# Crear seeders
php artisan make:seeder LpTipoProductoSeeder
php artisan make:seeder LpProductoSeeder

# Crear requests
php artisan make:request Api/Financiero/Lp/StoreLpTipoProductoRequest
php artisan make:request Api/Financiero/Lp/UpdateLpTipoProductoRequest
php artisan make:request Api/Financiero/Lp/StoreLpProductoRequest
php artisan make:request Api/Financiero/Lp/UpdateLpProductoRequest
php artisan make:request Api/Financiero/Lp/StoreLpListaPrecioRequest
php artisan make:request Api/Financiero/Lp/UpdateLpListaPrecioRequest
php artisan make:request Api/Financiero/Lp/StoreLpPrecioProductoRequest
php artisan make:request Api/Financiero/Lp/UpdateLpPrecioProductoRequest

# Crear resources
php artisan make:resource Api/Financiero/Lp/LpTipoProductoResource
php artisan make:resource Api/Financiero/Lp/LpProductoResource
php artisan make:resource Api/Financiero/Lp/LpListaPrecioResource
php artisan make:resource Api/Financiero/Lp/LpPrecioProductoResource

# Crear controladores
php artisan make:controller Api/Financiero/Lp/LpTipoProductoController --api
php artisan make:controller Api/Financiero/Lp/LpProductoController --api
php artisan make:controller Api/Financiero/Lp/LpListaPrecioController --api
php artisan make:controller Api/Financiero/Lp/LpPrecioProductoController --api

# Crear comando
php artisan make:command Financiero/ActivarListasPreciosAprobadas

# Crear archivos manuales
touch app/Traits/Financiero/HasListaPrecioStatus.php
touch app/Services/Financiero/LpPrecioProductoService.php
touch routes/financiero.php
```

### Verificación de Archivos Creados

Después de ejecutar los comandos, verifica que se hayan creado los siguientes archivos:

**Migraciones (5 archivos):**

-   `database/migrations/YYYY_MM_DD_HHMMSS_create_lp_tipos_producto_table.php`
-   `database/migrations/YYYY_MM_DD_HHMMSS_create_lp_productos_table.php`
-   `database/migrations/YYYY_MM_DD_HHMMSS_create_lp_listas_precios_table.php`
-   `database/migrations/YYYY_MM_DD_HHMMSS_create_lp_lista_precio_poblacion_table.php`
-   `database/migrations/YYYY_MM_DD_HHMMSS_create_lp_precios_producto_table.php`

**Modelos (4 archivos):**

-   `app/Models/Financiero/Lp/LpTipoProducto.php`
-   `app/Models/Financiero/Lp/LpProducto.php`
-   `app/Models/Financiero/Lp/LpListaPrecio.php`
-   `app/Models/Financiero/Lp/LpPrecioProducto.php`

**Seeders (2 archivos):**

-   `database/seeders/LpTipoProductoSeeder.php`
-   `database/seeders/LpProductoSeeder.php`

**Requests (8 archivos):**

-   `app/Http/Requests/Api/Financiero/Lp/StoreLpTipoProductoRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/UpdateLpTipoProductoRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/StoreLpProductoRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/UpdateLpProductoRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/StoreLpListaPrecioRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/UpdateLpListaPrecioRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/StoreLpPrecioProductoRequest.php`
-   `app/Http/Requests/Api/Financiero/Lp/UpdateLpPrecioProductoRequest.php`

**Resources (4 archivos):**

-   `app/Http/Resources/Api/Financiero/Lp/LpTipoProductoResource.php`
-   `app/Http/Resources/Api/Financiero/Lp/LpProductoResource.php`
-   `app/Http/Resources/Api/Financiero/Lp/LpListaPrecioResource.php`
-   `app/Http/Resources/Api/Financiero/Lp/LpPrecioProductoResource.php`

**Controladores (4 archivos):**

-   `app/Http/Controllers/Api/Financiero/Lp/LpTipoProductoController.php`
-   `app/Http/Controllers/Api/Financiero/Lp/LpProductoController.php`
-   `app/Http/Controllers/Api/Financiero/Lp/LpListaPrecioController.php`
-   `app/Http/Controllers/Api/Financiero/Lp/LpPrecioProductoController.php`

**Comandos (1 archivo):**

-   `app/Console/Commands/Financiero/ActivarListasPreciosAprobadas.php`

**Archivos Manuales (3 archivos):**

-   `app/Traits/Financiero/HasListaPrecioStatus.php`
-   `app/Services/Financiero/LpPrecioProductoService.php`
-   `routes/financiero.php` (si no existe)

**Total: 31 archivos a crear**

---

## Fase 1: Preparación y Estructura Base

### 1. Crear estructura de directorios

-   [x] Crear directorio `app/Traits/Financiero/`
-   [x] Crear directorio `app/Models/Financiero/Lp/`
-   [x] Crear directorio `app/Services/Financiero/`
-   [x] Crear directorio `app/Http/Controllers/Api/Financiero/Lp/`
-   [x] Crear directorio `app/Http/Requests/Api/Financiero/Lp/`
-   [x] Crear directorio `app/Http/Resources/Api/Financiero/Lp/`
-   [x] Crear directorio `app/Console/Commands/Financiero/`
-   [x] Crear directorio `database/seeders/` (si no existe)

### 2. Crear Trait de Estados

-   [ ] Crear archivo `app/Traits/Financiero/HasListaPrecioStatus.php`
-   [ ] Implementar método `getStatusOptions()` con los 4 estados (Inactiva, En Proceso, Aprobada, Activa)
-   [ ] Implementar método `getStatusText(?int $status)` con documentación PHPDoc en español
-   [ ] Implementar método `getStatusTextAttribute()` con documentación PHPDoc en español
-   [ ] Implementar método `getStatusValidationRule()` con documentación PHPDoc en español
-   [ ] Implementar método `getStatusValidationMessages()` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeInactiva($query)` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeEnProceso($query)` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeAprobada($query)` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeActiva($query)` con documentación PHPDoc en español
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Verificar que todos los métodos tengan documentación PHPDoc completa

## Fase 2: Migraciones de Base de Datos

### 3. Crear migración para `lp_tipos_producto`

-   [ ] Crear migración: `php artisan make:migration create_lp_tipos_producto_table`
-   [ ] Definir estructura de tabla según diseño:
    -   [ ] Campo `id` (BIGINT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
    -   [ ] Campo `nombre` (VARCHAR(255), NOT NULL, comentario en español)
    -   [ ] Campo `codigo` (VARCHAR(50), UNIQUE, NOT NULL, comentario en español)
    -   [ ] Campo `es_financiable` (BOOLEAN, DEFAULT FALSE, comentario en español)
    -   [ ] Campo `descripcion` (TEXT, NULL)
    -   [ ] Campo `status` (TINYINT, DEFAULT 1, comentario en español)
    -   [ ] Campos `created_at`, `updated_at` (TIMESTAMP, NULL)
    -   [ ] Campo `deleted_at` (TIMESTAMP, NULL) para soft deletes
-   [ ] Agregar índice `idx_codigo` en campo `codigo`
-   [ ] Agregar índice `idx_status` en campo `status`
-   [ ] Configurar engine InnoDB y charset utf8mb4
-   [ ] Agregar comentarios en español en todos los campos
-   [ ] Verificar sintaxis de la migración

### 4. Crear migración para `lp_productos`

-   [ ] Crear migración: `php artisan make:migration create_lp_productos_table`
-   [ ] Definir estructura de tabla según diseño:
    -   [ ] Campo `id` (BIGINT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
    -   [ ] Campo `tipo_producto_id` (BIGINT UNSIGNED, NOT NULL, FK a lp_tipos_producto)
    -   [ ] Campo `nombre` (VARCHAR(255), NOT NULL, comentario en español)
    -   [ ] Campo `codigo` (VARCHAR(100), UNIQUE, NULL, comentario en español)
    -   [ ] Campo `descripcion` (TEXT, NULL)
    -   [ ] Campo `referencia_id` (BIGINT UNSIGNED, NULL, comentario en español)
    -   [ ] Campo `referencia_tipo` (ENUM('curso', 'modulo'), NULL, comentario en español)
    -   [ ] Campo `status` (TINYINT, DEFAULT 1, comentario en español)
    -   [ ] Campos `created_at`, `updated_at` (TIMESTAMP, NULL)
    -   [ ] Campo `deleted_at` (TIMESTAMP, NULL) para soft deletes
-   [ ] Agregar foreign key `tipo_producto_id` -> `lp_tipos_producto(id)` ON DELETE RESTRICT
-   [ ] Agregar índice `idx_tipo_producto` en `tipo_producto_id`
-   [ ] Agregar índice compuesto `idx_referencia` en (`referencia_id`, `referencia_tipo`)
-   [ ] Agregar índice `idx_status` en `status`
-   [ ] Agregar índice `idx_codigo` en `codigo`
-   [ ] Configurar engine InnoDB y charset utf8mb4
-   [ ] Agregar comentarios en español en todos los campos
-   [ ] Verificar sintaxis de la migración

### 5. Crear migración para `lp_listas_precios`

-   [ ] Crear migración: `php artisan make:migration create_lp_listas_precios_table`
-   [ ] Definir estructura de tabla según diseño:
    -   [ ] Campo `id` (BIGINT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
    -   [ ] Campo `nombre` (VARCHAR(255), NOT NULL, comentario en español)
    -   [ ] Campo `codigo` (VARCHAR(100), UNIQUE, NULL, comentario en español)
    -   [ ] Campo `fecha_inicio` (DATE, NOT NULL, comentario en español)
    -   [ ] Campo `fecha_fin` (DATE, NOT NULL, comentario en español)
    -   [ ] Campo `descripcion` (TEXT, NULL)
    -   [ ] Campo `status` (TINYINT, DEFAULT 1, comentario con los 4 estados en español)
    -   [ ] Campos `created_at`, `updated_at` (TIMESTAMP, NULL)
    -   [ ] Campo `deleted_at` (TIMESTAMP, NULL) para soft deletes
-   [ ] Agregar índice compuesto `idx_fechas` en (`fecha_inicio`, `fecha_fin`)
-   [ ] Agregar índice `idx_status` en `status`
-   [ ] Agregar índice `idx_codigo` en `codigo`
-   [ ] Agregar CHECK constraint: `fecha_fin >= fecha_inicio`
-   [ ] Configurar engine InnoDB y charset utf8mb4
-   [ ] Agregar comentarios en español en todos los campos
-   [ ] Verificar sintaxis de la migración

### 6. Crear migración para `lp_lista_precio_poblacion`

-   [ ] Crear migración: `php artisan make:migration create_lp_lista_precio_poblacion_table`
-   [ ] Definir estructura de tabla según diseño:
    -   [ ] Campo `id` (BIGINT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
    -   [ ] Campo `lista_precio_id` (BIGINT UNSIGNED, NOT NULL, FK a lp_listas_precios)
    -   [ ] Campo `poblacion_id` (BIGINT UNSIGNED, NOT NULL, FK a poblacions)
    -   [ ] Campos `created_at`, `updated_at` (TIMESTAMP, NULL)
-   [ ] Agregar foreign key `lista_precio_id` -> `lp_listas_precios(id)` ON DELETE CASCADE
-   [ ] Agregar foreign key `poblacion_id` -> `poblacions(id)` ON DELETE CASCADE
-   [ ] Agregar unique constraint `uk_lista_poblacion` en (`lista_precio_id`, `poblacion_id`)
-   [ ] Agregar índice `idx_lista_precio` en `lista_precio_id`
-   [ ] Agregar índice `idx_poblacion` en `poblacion_id`
-   [ ] Configurar engine InnoDB y charset utf8mb4
-   [ ] Verificar sintaxis de la migración

### 7. Crear migración para `lp_precios_producto`

-   [ ] Crear migración: `php artisan make:migration create_lp_precios_producto_table`
-   [ ] Definir estructura de tabla según diseño:
    -   [ ] Campo `id` (BIGINT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
    -   [ ] Campo `lista_precio_id` (BIGINT UNSIGNED, NOT NULL, FK a lp_listas_precios)
    -   [ ] Campo `producto_id` (BIGINT UNSIGNED, NOT NULL, FK a lp_productos)
    -   [ ] Campo `precio_contado` (DECIMAL(15,2), NOT NULL, DEFAULT 0.00, comentario en español)
    -   [ ] Campo `precio_total` (DECIMAL(15,2), NULL, comentario en español)
    -   [ ] Campo `matricula` (DECIMAL(15,2), NOT NULL, DEFAULT 0.00, comentario en español)
    -   [ ] Campo `numero_cuotas` (INT, NULL, comentario en español)
    -   [ ] Campo `valor_cuota` (DECIMAL(15,2), NULL, comentario en español)
    -   [ ] Campo `observaciones` (TEXT, NULL)
    -   [ ] Campos `created_at`, `updated_at` (TIMESTAMP, NULL)
    -   [ ] Campo `deleted_at` (TIMESTAMP, NULL) para soft deletes
-   [ ] Agregar foreign key `lista_precio_id` -> `lp_listas_precios(id)` ON DELETE CASCADE
-   [ ] Agregar foreign key `producto_id` -> `lp_productos(id)` ON DELETE CASCADE
-   [ ] Agregar unique constraint `uk_lista_producto` en (`lista_precio_id`, `producto_id`)
-   [ ] Agregar índice `idx_lista_precio` en `lista_precio_id`
-   [ ] Agregar índice `idx_producto` en `producto_id`
-   [ ] Agregar CHECK constraint: `precio_contado >= 0`
-   [ ] Agregar CHECK constraint: `precio_total IS NULL OR precio_total >= 0`
-   [ ] Agregar CHECK constraint: `matricula >= 0`
-   [ ] Agregar CHECK constraint: `numero_cuotas IS NULL OR numero_cuotas > 0`
-   [ ] Agregar CHECK constraint: `valor_cuota IS NULL OR valor_cuota >= 0`
-   [ ] Configurar engine InnoDB y charset utf8mb4
-   [ ] Agregar comentarios en español en todos los campos
-   [ ] Verificar sintaxis de la migración

### 8. Ejecutar migraciones

-   [ ] Verificar que todas las migraciones estén correctamente ordenadas
-   [ ] Ejecutar migraciones: `php artisan migrate`
-   [ ] Verificar que no haya errores en la ejecución
-   [ ] Verificar que todas las tablas se hayan creado correctamente
-   [ ] Verificar que todos los índices y foreign keys estén creados

## Fase 3: Modelos Eloquent

### 9. Crear modelo `LpTipoProducto`

-   [ ] Crear archivo: `app/Models/Financiero/Lp/LpTipoProducto.php`
-   [ ] Agregar namespace: `App\Models\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Illuminate\Database\Eloquent\Model`
-   [ ] Usar traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasActiveStatus`
-   [ ] Definir `protected $table = 'lp_tipos_producto'`
-   [ ] Definir `protected $guarded` con campos protegidos
-   [ ] Definir `protected $casts` para `es_financiable` (boolean) y `status` (integer)
-   [ ] Implementar relación `productos()` HasMany con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedSortFields()` con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getDefaultRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getCountableRelations()` con documentación PHPDoc en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos y propiedades
-   [ ] Verificar que el modelo siga la estructura de `Asistencia.php`

### 10. Crear modelo `LpProducto`

-   [ ] Crear archivo: `app/Models/Financiero/Lp/LpProducto.php`
-   [ ] Agregar namespace: `App\Models\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Illuminate\Database\Eloquent\Model`
-   [ ] Usar traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasActiveStatus`
-   [ ] Definir `protected $table = 'lp_productos'`
-   [ ] Definir `protected $guarded` con campos protegidos
-   [ ] Definir `protected $casts` para `status` (integer)
-   [ ] Implementar relación `tipoProducto()` BelongsTo con documentación PHPDoc en español
-   [ ] Implementar relación `referencia()` MorphTo con documentación PHPDoc en español
-   [ ] Implementar relación `precios()` HasMany con documentación PHPDoc en español
-   [ ] Implementar relación `listasPrecios()` BelongsToMany con documentación PHPDoc en español
-   [ ] Implementar método `esFinanciable()` con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedSortFields()` con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getDefaultRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getCountableRelations()` con documentación PHPDoc en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos y propiedades
-   [ ] Verificar que el modelo siga la estructura de `Asistencia.php`

### 11. Crear modelo `LpListaPrecio`

-   [ ] Crear archivo: `app/Models/Financiero/Lp/LpListaPrecio.php`
-   [ ] Agregar namespace: `App\Models\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Illuminate\Database\Eloquent\Model`
-   [ ] Usar traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasListaPrecioStatus`
-   [ ] Definir `protected $table = 'lp_listas_precios'`
-   [ ] Definir `protected $guarded` con campos protegidos
-   [ ] Definir `protected $casts` para `fecha_inicio` (date), `fecha_fin` (date), `status` (integer)
-   [ ] Definir constantes de estado: `STATUS_INACTIVA`, `STATUS_EN_PROCESO`, `STATUS_APROBADA`, `STATUS_ACTIVA`
-   [ ] Implementar relación `poblaciones()` BelongsToMany con documentación PHPDoc en español
-   [ ] Implementar relación `preciosProductos()` HasMany con documentación PHPDoc en español
-   [ ] Implementar relación `productos()` BelongsToMany con documentación PHPDoc en español
-   [ ] Implementar método `estaVigente(?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeVigentes($query, ?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeAprobadasParaActivar($query, ?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar scope `scopeActivasParaInactivar($query, ?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar método estático `activarListasAprobadas(?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar método estático `inactivarListasVencidas(?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedSortFields()` con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getDefaultRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getCountableRelations()` con documentación PHPDoc en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos y propiedades
-   [ ] Verificar que el modelo siga la estructura de `Asistencia.php`

### 12. Crear modelo `LpPrecioProducto`

-   [ ] Crear archivo: `app/Models/Financiero/Lp/LpPrecioProducto.php`
-   [ ] Agregar namespace: `App\Models\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Illuminate\Database\Eloquent\Model`
-   [ ] Usar traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`
-   [ ] Definir `protected $table = 'lp_precios_producto'`
-   [ ] Definir `protected $guarded` con campos protegidos
-   [ ] Definir `protected $casts` para todos los campos decimales e integer
-   [ ] Implementar relación `listaPrecio()` BelongsTo con documentación PHPDoc en español
-   [ ] Implementar relación `producto()` BelongsTo con documentación PHPDoc en español
-   [ ] Implementar método `calcularValorCuota()` con documentación PHPDoc en español
-   [ ] Implementar método `boot()` con event listener `saving` para cálculo automático de cuotas
-   [ ] Implementar método `getAllowedSortFields()` con documentación PHPDoc en español
-   [ ] Implementar método `getAllowedRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getDefaultRelations()` con documentación PHPDoc en español
-   [ ] Implementar método `getCountableRelations()` con documentación PHPDoc en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos y propiedades
-   [ ] Verificar que el cálculo de cuotas funcione correctamente con redondeo al 100
-   [ ] Verificar que el modelo siga la estructura de `Asistencia.php`

## Fase 4: Seeders

### 13. Crear seeder `LpTipoProductoSeeder`

-   [ ] Crear archivo: `database/seeders/LpTipoProductoSeeder.php`
-   [ ] Agregar namespace: `Database\Seeders`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Seeder`
-   [ ] Implementar método `run()` con documentación PHPDoc en español
-   [ ] Crear tipo "curso" con `es_financiable = true`
-   [ ] Crear tipo "modulo" con `es_financiable = true`
-   [ ] Crear tipo "complementario" con `es_financiable = false`
-   [ ] Agregar manejo de errores con try-catch
-   [ ] Agregar logs de información
-   [ ] Verificar que los datos se creen correctamente

### 14. Actualizar `DatabaseSeeder`

-   [ ] Abrir archivo `database/seeders/DatabaseSeeder.php`
-   [ ] Agregar llamada a `LpTipoProductoSeeder` en el método `run()`
-   [ ] Agregar comentario explicativo en español
-   [ ] Verificar que el seeder se ejecute correctamente

## Fase 5: Servicios

### 15. Crear servicio `LpPrecioProductoService`

-   [ ] Crear archivo: `app/Services/Financiero/LpPrecioProductoService.php`
-   [ ] Agregar namespace: `App\Services\Financiero`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Implementar método `redondearACien(float $valor)` con documentación PHPDoc en español
-   [ ] Implementar método `calcularCuota(float $precioTotal, float $matricula, int $numeroCuotas)` con documentación PHPDoc en español
-   [ ] Implementar método `obtenerPrecio(int $productoId, int $poblacionId, ?Carbon $fecha)` con documentación PHPDoc en español
-   [ ] Implementar método `validarSolapamientoVigencia(int $poblacionId, Carbon $fechaInicio, Carbon $fechaFin, ?int $excluirListaId)` con documentación PHPDoc en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Agregar validaciones de parámetros
-   [ ] Agregar manejo de errores
-   [ ] Verificar que todos los métodos funcionen correctamente

## Fase 6: Comandos de Consola

### 16. Crear comando `ActivarListasPreciosAprobadas`

-   [ ] Crear archivo: `app/Console/Commands/Financiero/ActivarListasPreciosAprobadas.php`
-   [ ] Agregar namespace: `App\Console\Commands\Financiero`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Command`
-   [ ] Definir `protected $signature = 'financiero:gestionar-listas-precios'`
-   [ ] Definir `protected $description` con descripción en español
-   [ ] Implementar método `handle()` con documentación PHPDoc en español
-   [ ] Implementar lógica para activar listas aprobadas
-   [ ] Implementar lógica para inactivar listas vencidas
-   [ ] Agregar mensajes informativos en español
-   [ ] Agregar manejo de errores
-   [ ] Verificar que el comando funcione correctamente

### 17. Configurar comando en `Kernel.php`

-   [ ] Abrir archivo `app/Console/Kernel.php`
-   [ ] Agregar import del comando
-   [ ] Agregar comando programado en método `schedule()`: `$schedule->command('financiero:gestionar-listas-precios')->daily();`
-   [ ] Agregar comentario explicativo en español
-   [ ] Verificar sintaxis

## Fase 7: Requests (Validación)

### 18. Crear Request `StoreLpTipoProductoRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/StoreLpTipoProductoRequest.php`
-   [ ] Agregar namespace: `App\Http\Requests\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `FormRequest`
-   [ ] Usar traits: `HasActiveStatus`, `HasActiveStatusValidation`
-   [ ] Implementar método `authorize()` con documentación PHPDoc en español
-   [ ] Implementar método `rules()` con todas las validaciones requeridas
-   [ ] Usar `self::getStatusValidationRule()` para validar status
-   [ ] Implementar método `messages()` con mensajes en español
-   [ ] Usar `self::getStatusValidationMessages()` para mensajes de status
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que todas las validaciones estén correctas

### 19. Crear Request `UpdateLpTipoProductoRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/UpdateLpTipoProductoRequest.php`
-   [ ] Agregar namespace: `App\Http\Requests\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `FormRequest`
-   [ ] Usar traits: `HasActiveStatus`, `HasActiveStatusValidation`
-   [ ] Implementar método `authorize()` con documentación PHPDoc en español
-   [ ] Implementar método `rules()` con validaciones de actualización (sometimes)
-   [ ] Usar `self::getStatusValidationRule()` para validar status
-   [ ] Implementar método `messages()` con mensajes en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que todas las validaciones estén correctas

### 20. Crear Request `StoreLpProductoRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/StoreLpProductoRequest.php`
-   [ ] Agregar namespace: `App\Http\Requests\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `FormRequest`
-   [ ] Usar traits: `HasActiveStatus`, `HasActiveStatusValidation`
-   [ ] Implementar método `authorize()` con documentación PHPDoc en español
-   [ ] Implementar método `rules()` con todas las validaciones requeridas
-   [ ] Validar `tipo_producto_id` (required, exists:lp_tipos_producto)
-   [ ] Validar `referencia_id` y `referencia_tipo` con reglas condicionales
-   [ ] Usar `self::getStatusValidationRule()` para validar status
-   [ ] Implementar método `messages()` con mensajes en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que todas las validaciones estén correctas

### 21. Crear Request `UpdateLpProductoRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/UpdateLpProductoRequest.php`
-   [ ] Seguir los mismos pasos que `StoreLpProductoRequest` pero con validaciones `sometimes`
-   [ ] Agregar documentación PHPDoc en español a todos los métodos

### 22. Crear Request `StoreLpListaPrecioRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/StoreLpListaPrecioRequest.php`
-   [ ] Agregar namespace: `App\Http\Requests\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `FormRequest`
-   [ ] Usar trait: `HasListaPrecioStatus`
-   [ ] Implementar método `authorize()` con documentación PHPDoc en español
-   [ ] Implementar método `rules()` con todas las validaciones requeridas
-   [ ] Validar `fecha_fin` con `after_or_equal:fecha_inicio`
-   [ ] Validar `poblaciones` (required, array, exists:poblacions,id)
-   [ ] Usar `self::getStatusValidationRule()` para validar status
-   [ ] Implementar método `messages()` con mensajes en español
-   [ ] Agregar validación personalizada para solapamiento de vigencia
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que todas las validaciones estén correctas

### 23. Crear Request `UpdateLpListaPrecioRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/UpdateLpListaPrecioRequest.php`
-   [ ] Seguir los mismos pasos que `StoreLpListaPrecioRequest` pero con validaciones `sometimes`
-   [ ] Agregar documentación PHPDoc en español a todos los métodos

### 24. Crear Request `StoreLpPrecioProductoRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/StoreLpPrecioProductoRequest.php`
-   [ ] Agregar namespace: `App\Http\Requests\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `FormRequest`
-   [ ] Implementar método `authorize()` con documentación PHPDoc en español
-   [ ] Implementar método `rules()` con todas las validaciones requeridas
-   [ ] Validar campos financiables con `required_if` basado en tipo de producto
-   [ ] Validar `matricula` como required (puede ser 0)
-   [ ] Implementar método `messages()` con mensajes en español
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que todas las validaciones estén correctas

### 25. Crear Request `UpdateLpPrecioProductoRequest`

-   [ ] Crear archivo: `app/Http/Requests/Api/Financiero/Lp/UpdateLpPrecioProductoRequest.php`
-   [ ] Seguir los mismos pasos que `StoreLpPrecioProductoRequest` pero con validaciones `sometimes`
-   [ ] Agregar documentación PHPDoc en español a todos los métodos

## Fase 8: Resources (Transformadores)

### 26. Crear Resource `LpTipoProductoResource`

-   [ ] Crear archivo: `app/Http/Resources/Api/Financiero/Lp/LpTipoProductoResource.php`
-   [ ] Agregar namespace: `App\Http\Resources\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `JsonResource`
-   [ ] Usar trait: `HasActiveStatus`
-   [ ] Implementar método `toArray($request)` con documentación PHPDoc en español
-   [ ] Incluir todos los campos necesarios: id, nombre, codigo, es_financiable, descripcion, status
-   [ ] Incluir `status_text` usando `self::getActiveStatusText($this->status)`
-   [ ] Incluir timestamps
-   [ ] Agregar documentación PHPDoc en español al método

### 27. Crear Resource `LpProductoResource`

-   [ ] Crear archivo: `app/Http/Resources/Api/Financiero/Lp/LpProductoResource.php`
-   [ ] Agregar namespace: `App\Http\Resources\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `JsonResource`
-   [ ] Usar trait: `HasActiveStatus`
-   [ ] Implementar método `toArray($request)` con documentación PHPDoc en español
-   [ ] Incluir todos los campos necesarios: id, tipo_producto_id, nombre, codigo, descripcion, referencia_id, referencia_tipo, status
-   [ ] Incluir relación `tipo_producto` cuando esté cargada
-   [ ] Incluir relación `referencia` cuando esté cargada
-   [ ] Incluir `status_text` usando `self::getActiveStatusText($this->status)`
-   [ ] Incluir timestamps
-   [ ] Agregar documentación PHPDoc en español al método

### 28. Crear Resource `LpListaPrecioResource`

-   [ ] Crear archivo: `app/Http/Resources/Api/Financiero/Lp/LpListaPrecioResource.php`
-   [ ] Agregar namespace: `App\Http\Resources\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `JsonResource`
-   [ ] Usar trait: `HasListaPrecioStatus`
-   [ ] Implementar método `toArray($request)` con documentación PHPDoc en español
-   [ ] Incluir todos los campos necesarios: id, nombre, codigo, fecha_inicio, fecha_fin, descripcion, status
-   [ ] Incluir relación `poblaciones` cuando esté cargada
-   [ ] Incluir relación `precios_productos` cuando esté cargada
-   [ ] Incluir `status_text` usando `self::getStatusText($this->status)`
-   [ ] Incluir timestamps
-   [ ] Agregar documentación PHPDoc en español al método

### 29. Crear Resource `LpPrecioProductoResource`

-   [ ] Crear archivo: `app/Http/Resources/Api/Financiero/Lp/LpPrecioProductoResource.php`
-   [ ] Agregar namespace: `App\Http\Resources\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `JsonResource`
-   [ ] Implementar método `toArray($request)` con documentación PHPDoc en español
-   [ ] Incluir todos los campos necesarios: id, lista_precio_id, producto_id, precio_contado, precio_total, matricula, numero_cuotas, valor_cuota, observaciones
-   [ ] Incluir relación `lista_precio` cuando esté cargada
-   [ ] Incluir relación `producto` cuando esté cargada
-   [ ] Incluir timestamps
-   [ ] Agregar documentación PHPDoc en español al método

## Fase 9: Controladores

### 30. Crear Controlador `LpTipoProductoController`

-   [ ] Crear archivo: `app/Http/Controllers/Api/Financiero/Lp/LpTipoProductoController.php`
-   [ ] Agregar namespace: `App\Http\Controllers\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Controller`
-   [ ] Implementar método `index()` con documentación PHPDoc en español
-   [ ] Implementar filtros, ordenamiento y relaciones usando traits
-   [ ] Implementar método `store(StoreLpTipoProductoRequest $request)` con documentación PHPDoc en español
-   [ ] Implementar método `show($id)` con documentación PHPDoc en español
-   [ ] Implementar método `update(UpdateLpTipoProductoRequest $request, $id)` con documentación PHPDoc en español
-   [ ] Implementar método `destroy($id)` con documentación PHPDoc en español
-   [ ] Usar Resources para respuestas
-   [ ] Agregar manejo de errores apropiado
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que siga la estructura de otros controladores existentes

### 31. Crear Controlador `LpProductoController`

-   [ ] Crear archivo: `app/Http/Controllers/Api/Financiero/Lp/LpProductoController.php`
-   [ ] Agregar namespace: `App\Http\Controllers\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Controller`
-   [ ] Implementar método `index()` con documentación PHPDoc en español
-   [ ] Implementar filtros por tipo_producto_id, referencia_tipo, es_financiable
-   [ ] Implementar método `store(StoreLpProductoRequest $request)` con documentación PHPDoc en español
-   [ ] Implementar método `show($id)` con documentación PHPDoc en español
-   [ ] Implementar método `update(UpdateLpProductoRequest $request, $id)` con documentación PHPDoc en español
-   [ ] Implementar método `destroy($id)` con documentación PHPDoc en español
-   [ ] Usar Resources para respuestas
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que siga la estructura de otros controladores existentes

### 32. Crear Controlador `LpListaPrecioController`

-   [ ] Crear archivo: `app/Http/Controllers/Api/Financiero/Lp/LpListaPrecioController.php`
-   [ ] Agregar namespace: `App\Http\Controllers\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Controller`
-   [ ] Implementar método `index()` con documentación PHPDoc en español
-   [ ] Implementar filtros por status, fecha_inicio, fecha_fin, poblacion_id
-   [ ] Implementar método `store(StoreLpListaPrecioRequest $request)` con documentación PHPDoc en español
-   [ ] Validar solapamiento de vigencia en store
-   [ ] Implementar método `show($id)` con documentación PHPDoc en español
-   [ ] Implementar método `update(UpdateLpListaPrecioRequest $request, $id)` con documentación PHPDoc en español
-   [ ] Validar solapamiento de vigencia en update
-   [ ] Implementar método `destroy($id)` con documentación PHPDoc en español
-   [ ] Implementar método `aprobar($id)` con documentación PHPDoc en español
-   [ ] Implementar método `activar($id)` con documentación PHPDoc en español
-   [ ] Implementar método `inactivar($id)` con documentación PHPDoc en español
-   [ ] Usar Resources para respuestas
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que siga la estructura de otros controladores existentes

### 33. Crear Controlador `LpPrecioProductoController`

-   [ ] Crear archivo: `app/Http/Controllers/Api/Financiero/Lp/LpPrecioProductoController.php`
-   [ ] Agregar namespace: `App\Http\Controllers\Api\Financiero\Lp`
-   [ ] Agregar bloque de documentación de clase con descripción en español
-   [ ] Extender de `Controller`
-   [ ] Implementar método `index()` con documentación PHPDoc en español
-   [ ] Implementar filtros por lista_precio_id, producto_id
-   [ ] Implementar método `store(StoreLpPrecioProductoRequest $request)` con documentación PHPDoc en español
-   [ ] Implementar método `show($id)` con documentación PHPDoc en español
-   [ ] Implementar método `update(UpdateLpPrecioProductoRequest $request, $id)` con documentación PHPDoc en español
-   [ ] Implementar método `destroy($id)` con documentación PHPDoc en español
-   [ ] Implementar método `obtenerPrecio(Request $request)` con documentación PHPDoc en español
-   [ ] Usar servicio `LpPrecioProductoService` para obtener precio
-   [ ] Usar Resources para respuestas
-   [ ] Agregar documentación PHPDoc en español a todos los métodos
-   [ ] Verificar que siga la estructura de otros controladores existentes

## Fase 10: Rutas

### 34. Configurar rutas del módulo

-   [ ] Verificar si existe archivo `routes/financiero.php`, si no existe, crearlo
-   [ ] Agregar imports de todos los controladores
-   [ ] Agregar grupo de rutas con prefijo `financiero/lp` y middleware `auth:sanctum`
-   [ ] Agregar rutas API resource para tipos-producto
-   [ ] Agregar rutas API resource para productos
-   [ ] Agregar rutas API resource para listas-precios
-   [ ] Agregar ruta POST para aprobar lista de precios: `listas-precios/{id}/aprobar`
-   [ ] Agregar ruta POST para activar lista de precios: `listas-precios/{id}/activar`
-   [ ] Agregar ruta POST para inactivar lista de precios: `listas-precios/{id}/inactivar`
-   [ ] Agregar rutas API resource para precios-producto
-   [ ] Agregar ruta GET para obtener precio: `precios-producto/obtener-precio`
-   [ ] Agregar comentarios explicativos en español
-   [ ] Verificar que todas las rutas estén correctamente definidas

### 35. Registrar rutas en `RouteServiceProvider` (si es necesario)

-   [ ] Abrir archivo `app/Providers/RouteServiceProvider.php`
-   [ ] Verificar si se necesita registrar el archivo `financiero.php`
-   [ ] Agregar registro si es necesario
-   [ ] Agregar comentario explicativo en español

## Fase 11: Permisos y Seguridad

### 36. Actualizar `RolesAndPermissionsSeeder`

-   [ ] Abrir archivo `database/seeders/RolesAndPermissionsSeeder.php`
-   [ ] Agregar permisos para tipos de producto:
    -   [ ] `fin_lp_tipos_producto` (ver tipos de producto)
    -   [ ] `fin_lp_tipoProductoCrear` (crear tipo de producto)
    -   [ ] `fin_lp_tipoProductoEditar` (editar tipo de producto)
    -   [ ] `fin_lp_tipoProductoInactivar` (inactivar tipo de producto)
-   [ ] Agregar permisos para productos:
    -   [ ] `fin_lp_productos` (ver productos)
    -   [ ] `fin_lp_productoCrear` (crear producto)
    -   [ ] `fin_lp_productoEditar` (editar producto)
    -   [ ] `fin_lp_productoInactivar` (inactivar producto)
-   [ ] Agregar permisos para listas de precios:
    -   [ ] `fin_lp_listas_precios` (ver listas de precios)
    -   [ ] `fin_lp_listaPrecioCrear` (crear lista de precios)
    -   [ ] `fin_lp_listaPrecioEditar` (editar lista de precios)
    -   [ ] `fin_lp_listaPrecioInactivar` (inactivar lista de precios)
    -   [ ] `fin_lp_listaPrecioAprobar` (aprobar lista de precios)
-   [ ] Agregar permisos para precios de productos:
    -   [ ] `fin_lp_precios_producto` (ver precios de productos)
    -   [ ] `fin_lp_precioProductoCrear` (crear precio de producto)
    -   [ ] `fin_lp_precioProductoEditar` (editar precio de producto)
    -   [ ] `fin_lp_precioProductoInactivar` (inactivar precio de producto)
-   [ ] Asignar permisos a roles: superusuario, financiero, coordinador
-   [ ] Agregar descripciones en español para todos los permisos
-   [ ] Ejecutar seeder: `php artisan db:seed --class=RolesAndPermissionsSeeder`

### 37. Agregar middleware de permisos a controladores

-   [ ] Revisar cada controlador y agregar middleware de autorización en métodos
-   [ ] Usar `authorize()` o `Gate::authorize()` según corresponda
-   [ ] Verificar que todos los métodos estén protegidos con permisos apropiados
-   [ ] Agregar documentación PHPDoc en español sobre los permisos requeridos

## Fase 12: Pruebas y Validación

### 38. Pruebas de migraciones

-   [ ] Ejecutar `php artisan migrate:fresh` para probar migraciones desde cero
-   [ ] Verificar que todas las tablas se creen correctamente
-   [ ] Verificar que todos los índices estén creados
-   [ ] Verificar que todos los foreign keys estén creados
-   [ ] Verificar que todos los constraints estén funcionando

### 39. Pruebas de modelos

-   [ ] Probar creación de `LpTipoProducto`
-   [ ] Probar creación de `LpProducto` con referencia a curso
-   [ ] Probar creación de `LpProducto` con referencia a módulo
-   [ ] Probar creación de `LpProducto` complementario
-   [ ] Probar creación de `LpListaPrecio` en estado "en proceso"
-   [ ] Probar cambio de estado de lista a "aprobada"
-   [ ] Probar creación de `LpPrecioProducto` para producto financiable
-   [ ] Probar cálculo automático de `valor_cuota` con redondeo al 100
-   [ ] Probar creación de `LpPrecioProducto` para producto no financiable
-   [ ] Probar relaciones entre modelos
-   [ ] Probar scopes de filtrado

### 40. Pruebas de servicio

-   [ ] Probar método `redondearACien()` con diferentes valores
-   [ ] Probar método `calcularCuota()` con diferentes escenarios
-   [ ] Probar método `obtenerPrecio()` con población y fecha válidas
-   [ ] Probar método `obtenerPrecio()` cuando no existe lista vigente
-   [ ] Probar método `validarSolapamientoVigencia()` con solapamiento
-   [ ] Probar método `validarSolapamientoVigencia()` sin solapamiento

### 41. Pruebas de comando programado

-   [ ] Ejecutar comando manualmente: `php artisan financiero:gestionar-listas-precios`
-   [ ] Verificar que active listas aprobadas cuando fecha_inicio <= fecha_actual
-   [ ] Verificar que inactive listas activas cuando fecha_fin < fecha_actual
-   [ ] Verificar mensajes informativos en español
-   [ ] Verificar que no haya errores en la ejecución

### 42. Pruebas de API - Tipos de Producto

-   [ ] Probar GET `/api/financiero/lp/tipos-producto` (listar)
-   [ ] Probar GET `/api/financiero/lp/tipos-producto/{id}` (mostrar)
-   [ ] Probar POST `/api/financiero/lp/tipos-producto` (crear)
-   [ ] Probar PUT `/api/financiero/lp/tipos-producto/{id}` (actualizar)
-   [ ] Probar DELETE `/api/financiero/lp/tipos-producto/{id}` (eliminar)
-   [ ] Probar filtros y ordenamiento
-   [ ] Probar relaciones
-   [ ] Verificar respuestas con Resources
-   [ ] Verificar validaciones de request
-   [ ] Verificar permisos y autenticación

### 43. Pruebas de API - Productos

-   [ ] Probar GET `/api/financiero/lp/productos` (listar)
-   [ ] Probar GET `/api/financiero/lp/productos/{id}` (mostrar)
-   [ ] Probar POST `/api/financiero/lp/productos` (crear)
-   [ ] Probar PUT `/api/financiero/lp/productos/{id}` (actualizar)
-   [ ] Probar DELETE `/api/financiero/lp/productos/{id}` (eliminar)
-   [ ] Probar filtros por tipo_producto_id, referencia_tipo, es_financiable
-   [ ] Verificar respuestas con Resources
-   [ ] Verificar validaciones de request
-   [ ] Verificar permisos y autenticación

### 44. Pruebas de API - Listas de Precios

-   [ ] Probar GET `/api/financiero/lp/listas-precios` (listar)
-   [ ] Probar GET `/api/financiero/lp/listas-precios/{id}` (mostrar)
-   [ ] Probar POST `/api/financiero/lp/listas-precios` (crear)
-   [ ] Probar PUT `/api/financiero/lp/listas-precios/{id}` (actualizar)
-   [ ] Probar DELETE `/api/financiero/lp/listas-precios/{id}` (eliminar)
-   [ ] Probar POST `/api/financiero/lp/listas-precios/{id}/aprobar` (aprobar)
-   [ ] Probar POST `/api/financiero/lp/listas-precios/{id}/activar` (activar)
-   [ ] Probar POST `/api/financiero/lp/listas-precios/{id}/inactivar` (inactivar)
-   [ ] Probar filtros por status, fecha_inicio, fecha_fin, poblacion_id
-   [ ] Probar validación de solapamiento de vigencia
-   [ ] Verificar respuestas con Resources
-   [ ] Verificar validaciones de request
-   [ ] Verificar permisos y autenticación

### 45. Pruebas de API - Precios de Productos

-   [ ] Probar GET `/api/financiero/lp/precios-producto` (listar)
-   [ ] Probar GET `/api/financiero/lp/precios-producto/{id}` (mostrar)
-   [ ] Probar POST `/api/financiero/lp/precios-producto` (crear)
-   [ ] Probar PUT `/api/financiero/lp/precios-producto/{id}` (actualizar)
-   [ ] Probar DELETE `/api/financiero/lp/precios-producto/{id}` (eliminar)
-   [ ] Probar GET `/api/financiero/lp/precios-producto/obtener-precio` (obtener precio)
-   [ ] Probar cálculo automático de valor_cuota al crear/actualizar
-   [ ] Verificar que valor_cuota NO se recalcule al consultar
-   [ ] Verificar respuestas con Resources
-   [ ] Verificar validaciones de request
-   [ ] Verificar permisos y autenticación

### 46. Pruebas de cálculo de cuotas

-   [ ] Probar cálculo con valor que redondea hacia abajo (ej: 5530 → 5500)
-   [ ] Probar cálculo con valor que redondea hacia arriba (ej: 6580 → 6600)
-   [ ] Probar cálculo con matrícula = 0
-   [ ] Probar cálculo con diferentes números de cuotas
-   [ ] Verificar que el cálculo se almacene correctamente en la base de datos
-   [ ] Verificar que el cálculo NO se recalcule al consultar

### 47. Pruebas de flujo de estados

-   [ ] Crear lista en estado "en proceso"
-   [ ] Verificar que no se pueda activar directamente desde "en proceso"
-   [ ] Cambiar a estado "aprobada"
-   [ ] Verificar que se active automáticamente cuando fecha_inicio <= fecha_actual
-   [ ] Verificar que se inactive automáticamente cuando fecha_fin < fecha_actual
-   [ ] Probar cambio manual de estados según reglas de negocio

### 48. Pruebas de validaciones

-   [ ] Probar validación de solapamiento de vigencia
-   [ ] Probar validación de fecha_fin >= fecha_inicio
-   [ ] Probar validación de matrícula obligatoria para cursos y módulos
-   [ ] Probar validación de campos financiables
-   [ ] Probar validación de campos no financiables
-   [ ] Probar validación de códigos únicos
-   [ ] Probar validación de foreign keys
-   [ ] Verificar mensajes de error en español

## Fase 13: Documentación PHPDoc

### 49. Revisar documentación PHPDoc en todos los archivos

-   [ ] Verificar que todas las clases tengan bloque de documentación con descripción en español
-   [ ] Verificar que todos los métodos públicos tengan documentación PHPDoc en español
-   [ ] Verificar que todos los métodos protegidos importantes tengan documentación PHPDoc en español
-   [ ] Verificar que todas las propiedades públicas tengan documentación PHPDoc en español
-   [ ] Verificar que todos los parámetros estén documentados con `@param` en español
-   [ ] Verificar que todos los valores de retorno estén documentados con `@return` en español
-   [ ] Verificar que se documenten excepciones con `@throws` cuando corresponda
-   [ ] Verificar que se usen tags `@property`, `@property-read` en modelos cuando corresponda
-   [ ] Verificar formato consistente de documentación en todos los archivos
-   [ ] Corregir cualquier documentación faltante o incompleta

### 50. Generar documentación con PHPDoc (opcional)

-   [ ] Instalar herramienta de documentación PHPDoc si se desea
-   [ ] Generar documentación HTML/PDF del módulo
-   [ ] Verificar que la documentación generada esté completa y en español

## Fase 14: Optimización y Limpieza

### 51. Optimización de consultas

-   [ ] Revisar consultas N+1 en controladores
-   [ ] Agregar eager loading donde sea necesario
-   [ ] Optimizar consultas con índices apropiados
-   [ ] Verificar que los scopes sean eficientes

### 52. Limpieza de código

-   [ ] Eliminar código comentado innecesario
-   [ ] Eliminar imports no utilizados
-   [ ] Verificar que no haya código duplicado
-   [ ] Verificar que se sigan las convenciones de Laravel
-   [ ] Verificar que se sigan las convenciones del proyecto

### 53. Revisión final

-   [ ] Revisar que todos los archivos estén creados según el diseño
-   [ ] Revisar que todas las funcionalidades estén implementadas
-   [ ] Revisar que todas las validaciones estén implementadas
-   [ ] Revisar que todos los permisos estén configurados
-   [ ] Revisar que la documentación PHPDoc esté completa
-   [ ] Revisar que los mensajes estén en español
-   [ ] Revisar que no haya errores de sintaxis
-   [ ] Revisar que no haya warnings del linter

## Fase 15: Documentación Final

### 54. Actualizar documentación del módulo

-   [ ] Revisar `DISENO_MODULO_FINANCIERO_LISTAS_PRECIOS.md` y verificar que todo esté implementado
-   [ ] Crear documentación de API si es necesario
-   [ ] Documentar casos de uso comunes
-   [ ] Documentar ejemplos de integración

### 55. Crear guía de uso (opcional)

-   [ ] Crear documento con ejemplos de uso del módulo
-   [ ] Documentar flujos de trabajo comunes
-   [ ] Documentar troubleshooting común

---

## Notas Importantes

-   Todos los comentarios y documentación deben estar en **español**
-   Todos los mensajes de error y validación deben estar en **español**
-   La documentación PHPDoc debe seguir el estándar y estar completa
-   Todos los archivos deben seguir la estructura establecida en el diseño
-   Todas las pruebas deben validar el comportamiento esperado según el diseño

## Checklist de Verificación Final

Antes de considerar el módulo completo, verificar:

-   [ ] Todas las migraciones ejecutadas sin errores
-   [ ] Todos los modelos creados y funcionando
-   [ ] Todos los controladores implementados
-   [ ] Todas las rutas configuradas y funcionando
-   [ ] Todos los permisos configurados
-   [ ] Todas las validaciones implementadas
-   [ ] Todas las pruebas pasando
-   [ ] Documentación PHPDoc completa en español
-   [ ] Código siguiendo convenciones del proyecto
-   [ ] Sin errores de sintaxis o warnings del linter
