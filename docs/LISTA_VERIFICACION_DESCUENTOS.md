# Lista de Verificación - Implementación del Módulo de Descuentos

Este documento contiene el paso a paso numerado de todas las actividades necesarias para implementar el módulo de Descuentos según el diseño establecido en `DISENO_MODULO_FINANCIERO_DESCUENTOS.md`.

## Comandos de Terminal para Crear Archivos

Ejecuta los siguientes comandos en orden para crear todos los archivos necesarios. Una vez creados, avisa para iniciar con la implementación.

### Crear Estructura de Directorios

```bash
# Crear directorios necesarios
mkdir -p app/Traits/Financiero
mkdir -p app/Models/Financiero/Descuento
mkdir -p app/Services/Financiero
mkdir -p app/Http/Controllers/Api/Financiero/Descuento
mkdir -p app/Http/Requests/Api/Financiero/Descuento
mkdir -p app/Http/Resources/Api/Financiero/Descuento
mkdir -p app/Console/Commands/Financiero
mkdir -p database/factories/Financiero/Descuento
```

### Crear Migraciones

```bash
# Migración para tabla descuentos
php artisan make:migration create_descuentos_table

# Migración para relación descuento_lista_precio (many-to-many)
php artisan make:migration create_descuento_lista_precio_table

# Migración para relación descuento_producto (many-to-many)
php artisan make:migration create_descuento_producto_table

# Migración para relación descuento_sede (many-to-many)
php artisan make:migration create_descuento_sede_table

# Migración para relación descuento_poblacion (many-to-many)
php artisan make:migration create_descuento_poblacion_table

# Migración para tabla descuento_aplicado (historial)
php artisan make:migration create_descuento_aplicado_table
```

### Crear Modelos

```bash
# Modelo Descuento
php artisan make:model Financiero/Descuento/Descuento

# Modelo DescuentoAplicado
php artisan make:model Financiero/Descuento/DescuentoAplicado
```

### Crear Requests (Validación)

```bash
# Request para crear descuento
php artisan make:request Api/Financiero/Descuento/StoreDescuentoRequest

# Request para actualizar descuento
php artisan make:request Api/Financiero/Descuento/UpdateDescuentoRequest
```

### Crear Resources

```bash
# Resource para Descuento
php artisan make:resource Api/Financiero/Descuento/DescuentoResource

# Resource para DescuentoAplicado
php artisan make:resource Api/Financiero/Descuento/DescuentoAplicadoResource
```

### Crear Controller

```bash
# Controller para Descuento
php artisan make:controller Api/Financiero/Descuento/DescuentoController --resource
```

### Crear Service

```bash
# Service para lógica de negocio de descuentos
# Nota: Los services se crean manualmente, no hay comando artisan
```

### Crear Command

```bash
# Command para gestión automática de estados
php artisan make:command Financiero/GestionarEstadosDescuentos
```

### Crear Factory

```bash
# Factory para Descuento
php artisan make:factory Financiero/Descuento/DescuentoFactory --model=Financiero/Descuento/Descuento

# Factory para DescuentoAplicado
php artisan make:factory Financiero/Descuento/DescuentoAplicadoFactory --model=Financiero/Descuento/DescuentoAplicado
```

---

## Fase 1: Preparación y Estructura Base

### 1. Crear estructura de directorios

-   [ ] Crear directorio `app/Traits/Financiero/` (si no existe)
-   [ ] Crear directorio `app/Models/Financiero/Descuento/`
-   [ ] Crear directorio `app/Services/Financiero/` (si no existe)
-   [ ] Crear directorio `app/Http/Controllers/Api/Financiero/Descuento/`
-   [ ] Crear directorio `app/Http/Requests/Api/Financiero/Descuento/`
-   [ ] Crear directorio `app/Http/Resources/Api/Financiero/Descuento/`
-   [ ] Crear directorio `app/Console/Commands/Financiero/` (si no existe)
-   [ ] Crear directorio `database/factories/Financiero/Descuento/`

### 2. Crear Trait de Estados

-   [x] Crear archivo `app/Traits/Financiero/HasDescuentoStatus.php` ✅
-   [x] Implementar constante `STATUS_INACTIVO = 0` ✅
-   [x] Implementar constante `STATUS_EN_PROCESO = 1` ✅
-   [x] Implementar constante `STATUS_APROBADO = 2` ✅
-   [x] Implementar constante `STATUS_ACTIVO = 3` ✅
-   [x] Implementar método `getStatusOptions()` con los 4 estados ✅
-   [x] Implementar método `getStatusText(?int $status)` con documentación PHPDoc en español ✅
-   [x] Implementar método `getStatusTextAttribute()` con documentación PHPDoc en español ✅
-   [x] Implementar método `getStatusValidationRule()` con documentación PHPDoc en español ✅
-   [x] Implementar método `getStatusValidationMessages()` con documentación PHPDoc en español ✅
-   [x] Implementar scopes: `scopeInactivo()`, `scopeEnProceso()`, `scopeAprobado()`, `scopeActivo()` ✅
-   [x] Implementar scope `scopeAprobadosParaActivar(?Carbon $fecha = null)` ✅
-   [x] Implementar scope `scopeActivosParaInactivar(?Carbon $fecha = null)` ✅
-   [x] Implementar método estático `activarDescuentosAprobados(?Carbon $fecha = null)` ✅
-   [x] Implementar método estático `inactivarDescuentosVencidos(?Carbon $fecha = null)` ✅

---

## Fase 2: Base de Datos (Migraciones)

### 3. Migración: Tabla `descuentos`

-   [x] Crear migración `create_descuentos_table` ✅
-   [x] Definir columna `id` (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ✅
-   [x] Definir columna `nombre` (VARCHAR(255) NOT NULL) ✅
-   [x] Definir columna `codigo_descuento` (VARCHAR(50) UNIQUE NULL) ✅
-   [x] Definir columna `descripcion` (TEXT NULL) ✅
-   [x] Definir columna `tipo` (ENUM: 'porcentual', 'valor_fijo') ✅
-   [x] Definir columna `valor` (DECIMAL(15, 2) NOT NULL) ✅
-   [x] Definir columna `aplicacion` (ENUM: 'valor_total', 'matricula', 'cuota') ✅
-   [x] Definir columna `tipo_activacion` (ENUM: 'pago_anticipado', 'promocion_matricula', 'codigo_promocional') ✅
-   [x] Definir columna `dias_anticipacion` (INT UNSIGNED NULL) ✅
-   [x] Definir columna `permite_acumulacion` (BOOLEAN DEFAULT FALSE) ✅
-   [x] Definir columna `fecha_inicio` (DATE NOT NULL) ✅
-   [x] Definir columna `fecha_fin` (DATE NOT NULL) ✅
-   [x] Definir columna `status` (TINYINT DEFAULT 1) ✅
-   [x] Definir columnas `created_at` y `updated_at` (TIMESTAMP NULL) ✅
-   [x] Agregar índice `idx_codigo_descuento` en `codigo_descuento` ✅
-   [x] Agregar índice `idx_status` en `status` ✅
-   [x] Agregar índice `idx_fecha_inicio` en `fecha_inicio` ✅
-   [x] Agregar índice `idx_fecha_fin` en `fecha_fin` ✅
-   [x] Agregar índice `idx_tipo_activacion` en `tipo_activacion` ✅
-   [x] Agregar CHECK constraint: `valor >= 0` ✅
-   [x] Agregar CHECK constraint: `tipo_activacion = 'pago_anticipado' AND dias_anticipacion IS NOT NULL OR tipo_activacion != 'pago_anticipado'` ✅
-   [x] Agregar CHECK constraint: `tipo_activacion = 'codigo_promocional' AND codigo_descuento IS NOT NULL OR tipo_activacion != 'codigo_promocional'` ✅
-   [x] Agregar CHECK constraint: `fecha_fin >= fecha_inicio` ✅

### 4. Migración: Tabla `descuento_lista_precio` (many-to-many)

-   [x] Crear migración `create_descuento_lista_precio_table` ✅
-   [x] Definir columna `id` (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ✅
-   [x] Definir columna `descuento_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columna `lista_precio_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columnas `created_at` y `updated_at` (TIMESTAMP NULL) ✅
-   [x] Agregar foreign key `descuento_id` -> `descuentos(id)` ON DELETE CASCADE ✅
-   [x] Agregar foreign key `lista_precio_id` -> `lp_listas_precios(id)` ON DELETE CASCADE ✅
-   [x] Agregar índice único `idx_descuento_lista_precio` en (`descuento_id`, `lista_precio_id`) ✅

### 5. Migración: Tabla `descuento_producto` (many-to-many)

-   [x] Crear migración `create_descuento_producto_table` ✅
-   [x] Definir columna `id` (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ✅
-   [x] Definir columna `descuento_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columna `producto_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columnas `created_at` y `updated_at` (TIMESTAMP NULL) ✅
-   [x] Agregar foreign key `descuento_id` -> `descuentos(id)` ON DELETE CASCADE ✅
-   [x] Agregar foreign key `producto_id` -> `lp_productos(id)` ON DELETE CASCADE ✅
-   [x] Agregar índice único `idx_descuento_producto` en (`descuento_id`, `producto_id`) ✅

### 6. Migración: Tabla `descuento_sede` (many-to-many)

-   [x] Crear migración `create_descuento_sede_table` ✅
-   [x] Definir columna `id` (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ✅
-   [x] Definir columna `descuento_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columna `sede_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columnas `created_at` y `updated_at` (TIMESTAMP NULL) ✅
-   [x] Agregar foreign key `descuento_id` -> `descuentos(id)` ON DELETE CASCADE ✅
-   [x] Agregar foreign key `sede_id` -> `sedes(id)` ON DELETE CASCADE ✅
-   [x] Agregar índice único `idx_descuento_sede` en (`descuento_id`, `sede_id`) ✅

### 7. Migración: Tabla `descuento_poblacion` (many-to-many)

-   [x] Crear migración `create_descuento_poblacion_table` ✅
-   [x] Definir columna `id` (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ✅
-   [x] Definir columna `descuento_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columna `poblacion_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columnas `created_at` y `updated_at` (TIMESTAMP NULL) ✅
-   [x] Agregar foreign key `descuento_id` -> `descuentos(id)` ON DELETE CASCADE ✅
-   [x] Agregar foreign key `poblacion_id` -> `poblaciones(id)` ON DELETE CASCADE ✅
-   [x] Agregar índice único `idx_descuento_poblacion` en (`descuento_id`, `poblacion_id`) ✅

### 8. Migración: Tabla `descuento_aplicado` (historial)

-   [x] Crear migración `create_descuento_aplicado_table` ✅
-   [x] Definir columna `id` (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ✅
-   [x] Definir columna `descuento_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columna `concepto_tipo` (VARCHAR(255) NOT NULL) ✅
-   [x] Definir columna `concepto_id` (BIGINT UNSIGNED NOT NULL) ✅
-   [x] Definir columna `valor_original` (DECIMAL(15, 2) NOT NULL) ✅
-   [x] Definir columna `valor_descuento` (DECIMAL(15, 2) NOT NULL) ✅
-   [x] Definir columna `valor_final` (DECIMAL(15, 2) NOT NULL) ✅
-   [x] Definir columna `producto_id` (BIGINT UNSIGNED NULL) ✅
-   [x] Definir columna `lista_precio_id` (BIGINT UNSIGNED NULL) ✅
-   [x] Definir columna `sede_id` (BIGINT UNSIGNED NULL) ✅
-   [x] Definir columna `observaciones` (TEXT NULL) ✅
-   [x] Definir columnas `created_at` y `updated_at` (TIMESTAMP NULL) ✅
-   [x] Agregar foreign key `descuento_id` -> `descuentos(id)` ON DELETE RESTRICT ✅
-   [x] Agregar foreign key `producto_id` -> `lp_productos(id)` ON DELETE SET NULL ✅
-   [x] Agregar foreign key `lista_precio_id` -> `lp_listas_precios(id)` ON DELETE SET NULL ✅
-   [x] Agregar foreign key `sede_id` -> `sedes(id)` ON DELETE SET NULL ✅
-   [x] Agregar índice `idx_descuento` en `descuento_id` ✅
-   [x] Agregar índice `idx_concepto` en (`concepto_tipo`, `concepto_id`) ✅
-   [x] Agregar índice `idx_producto` en `producto_id` ✅
-   [x] Agregar índice `idx_lista_precio` en `lista_precio_id` ✅
-   [x] Agregar índice `idx_sede` en `sede_id` ✅
-   [x] Agregar índice `idx_created_at` en `created_at` ✅
-   [x] Agregar CHECK constraint: `valor_original >= 0` ✅
-   [x] Agregar CHECK constraint: `valor_descuento >= 0` ✅
-   [x] Agregar CHECK constraint: `valor_final >= 0` ✅
-   [x] Agregar CHECK constraint: `valor_final = valor_original - valor_descuento` ✅

---

## Fase 3: Modelos Eloquent

### 9. Modelo `Descuento`

-   [x] Crear archivo `app/Models/Financiero/Descuento/Descuento.php` ✅
-   [x] Definir namespace `App\Models\Financiero\Descuento` ✅
-   [x] Extender de `Illuminate\Database\Eloquent\Model` ✅
-   [x] Usar traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasDescuentoStatus` ✅
-   [x] Definir `protected $table = 'descuentos'` ✅
-   [x] Definir `protected $guarded = ['id', 'created_at', 'updated_at']` ✅
-   [x] Definir `protected $casts` con:
    -   `fecha_inicio` => `'date'` ✅
    -   `fecha_fin` => `'date'` ✅
    -   `status` => `'integer'` ✅
    -   `valor` => `'decimal:2'` ✅
    -   `permite_acumulacion` => `'boolean'` ✅
    -   `dias_anticipacion` => `'integer'` ✅
-   [x] Definir constantes:
    -   `TIPO_PORCENTUAL = 'porcentual'` ✅
    -   `TIPO_VALOR_FIJO = 'valor_fijo'` ✅
    -   `APLICACION_VALOR_TOTAL = 'valor_total'` ✅
    -   `APLICACION_MATRICULA = 'matricula'` ✅
    -   `APLICACION_CUOTA = 'cuota'` ✅
    -   `ACTIVACION_PAGO_ANTICIPADO = 'pago_anticipado'` ✅
    -   `ACTIVACION_PROMOCION_MATRICULA = 'promocion_matricula'` ✅
    -   `ACTIVACION_CODIGO_PROMOCIONAL = 'codigo_promocional'` ✅
-   [x] Implementar relación `listasPrecios()` (BelongsToMany) ✅
-   [x] Implementar relación `productos()` (BelongsToMany) ✅
-   [x] Implementar relación `sedes()` (BelongsToMany) ✅
-   [x] Implementar relación `poblaciones()` (BelongsToMany) ✅
-   [x] Implementar relación `descuentosAplicados()` (HasMany) ✅
-   [x] Implementar método `estaVigente(?Carbon $fecha = null): bool` ✅
-   [x] Implementar método `puedeActivar(?Carbon $fecha = null, ?string $codigoPromocional = null, ?Carbon $fechaPago = null, ?Carbon $fechaProgramada = null): bool` ✅
-   [x] Implementar método `puedeActivarPorCodigo(string $codigo): bool` ✅
-   [x] Implementar método `calcularDescuento(float $valor): float` ✅
-   [x] Implementar scope `scopeVigentes($query, ?Carbon $fecha = null)` ✅
-   [x] Implementar scope `scopePorTipo($query, string $tipo)` ✅
-   [x] Implementar scope `scopePorAplicacion($query, string $aplicacion)` ✅
-   [x] Implementar scope `scopePorTipoActivacion($query, string $tipoActivacion)` ✅
-   [x] Implementar método `getAllowedSortFields(): array` ✅
-   [x] Implementar método `getAllowedRelations(): array` ✅
-   [x] Implementar método `getDefaultRelations(): array` ✅
-   [x] Implementar método `getCountableRelations(): array` ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

### 10. Modelo `DescuentoAplicado`

-   [x] Crear archivo `app/Models/Financiero/Descuento/DescuentoAplicado.php` ✅
-   [x] Definir namespace `App\Models\Financiero\Descuento` ✅
-   [x] Extender de `Illuminate\Database\Eloquent\Model` ✅
-   [x] Usar traits: `HasFactory`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes` ✅
-   [x] Definir `protected $table = 'descuento_aplicado'` ✅
-   [x] Definir `protected $guarded = ['id', 'created_at', 'updated_at']` ✅
-   [x] Definir `protected $casts` con:
    -   `valor_original` => `'decimal:2'` ✅
    -   `valor_descuento` => `'decimal:2'` ✅
    -   `valor_final` => `'decimal:2'` ✅
    -   `created_at` => `'datetime'` ✅
    -   `updated_at` => `'datetime'` ✅
-   [x] Implementar relación `descuento()` (BelongsTo) ✅
-   [x] Implementar relación `producto()` (BelongsTo) ✅
-   [x] Implementar relación `listaPrecio()` (BelongsTo) ✅
-   [x] Implementar relación `sede()` (BelongsTo) ✅
-   [x] Implementar método `getAllowedSortFields(): array` ✅
-   [x] Implementar método `getAllowedRelations(): array` ✅
-   [x] Implementar método `getDefaultRelations(): array` ✅
-   [x] Implementar método `getCountableRelations(): array` ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

---

## Fase 4: Requests (Validación)

### 11. Request `StoreDescuentoRequest`

-   [x] Crear archivo `app/Http/Requests/Api/Financiero/Descuento/StoreDescuentoRequest.php` ✅
-   [x] Implementar método `authorize(): bool` (retornar true) ✅
-   [x] Implementar método `rules(): array` con validaciones:
    -   `nombre`: required|string|max:255 ✅
    -   `codigo_descuento`: nullable|string|max:50|unique:descuentos,codigo_descuento ✅
    -   `descripcion`: nullable|string ✅
    -   `tipo`: required|in:porcentual,valor_fijo ✅
    -   `valor`: required|numeric|min:0 ✅
    -   `aplicacion`: required|in:valor_total,matricula,cuota ✅
    -   `tipo_activacion`: required|in:pago_anticipado,promocion_matricula,codigo_promocional ✅
    -   `dias_anticipacion`: required_if:tipo_activacion,pago_anticipado|integer|min:1 ✅
    -   `permite_acumulacion`: boolean ✅
    -   `fecha_inicio`: required|date ✅
    -   `fecha_fin`: required|date|after_or_equal:fecha_inicio ✅
    -   `status`: usar `Descuento::getStatusValidationRule()` ✅
    -   `listas_precios`: nullable|array ✅
    -   `listas_precios.*`: exists:lp_listas_precios,id ✅
    -   `productos`: nullable|array ✅
    -   `productos.*`: exists:lp_productos,id ✅
    -   `sedes`: nullable|array ✅
    -   `sedes.*`: exists:sedes,id ✅
    -   `poblaciones`: nullable|array ✅
    -   `poblaciones.*`: exists:poblaciones,id ✅
-   [x] Implementar método `messages(): array` con mensajes en español ✅
-   [x] Agregar validación condicional: si `tipo_activacion = 'codigo_promocional'`, entonces `codigo_descuento` es required ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

### 12. Request `UpdateDescuentoRequest`

-   [x] Crear archivo `app/Http/Requests/Api/Financiero/Descuento/UpdateDescuentoRequest.php` ✅
-   [x] Implementar método `authorize(): bool` (retornar true) ✅
-   [x] Implementar método `rules(): array` con validaciones (similar a Store pero con `sometimes`):
    -   `nombre`: sometimes|string|max:255 ✅
    -   `codigo_descuento`: nullable|string|max:50|unique:descuentos,codigo_descuento,{id} ✅
    -   `descripcion`: nullable|string ✅
    -   `tipo`: sometimes|in:porcentual,valor_fijo ✅
    -   `valor`: sometimes|numeric|min:0 ✅
    -   `aplicacion`: sometimes|in:valor_total,matricula,cuota ✅
    -   `tipo_activacion`: sometimes|in:pago_anticipado,promocion_matricula,codigo_promocional ✅
    -   `dias_anticipacion`: required_if:tipo_activacion,pago_anticipado|integer|min:1 ✅
    -   `permite_acumulacion`: boolean ✅
    -   `fecha_inicio`: sometimes|date ✅
    -   `fecha_fin`: sometimes|date|after_or_equal:fecha_inicio ✅
    -   `status`: usar `Descuento::getStatusValidationRule()` ✅
    -   `listas_precios`: nullable|array ✅
    -   `listas_precios.*`: exists:lp_listas_precios,id ✅
    -   `productos`: nullable|array ✅
    -   `productos.*`: exists:lp_productos,id ✅
    -   `sedes`: nullable|array ✅
    -   `sedes.*`: exists:sedes,id ✅
    -   `poblaciones`: nullable|array ✅
    -   `poblaciones.*`: exists:poblaciones,id ✅
-   [x] Implementar método `messages(): array` con mensajes en español ✅
-   [x] Agregar validación condicional: si `tipo_activacion = 'codigo_promocional'`, entonces `codigo_descuento` es required ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

---

## Fase 5: Resources

### 13. Resource `DescuentoResource`

-   [x] Crear archivo `app/Http/Resources/Api/Financiero/Descuento/DescuentoResource.php` ✅
-   [x] Extender de `JsonResource` ✅
-   [x] Implementar método `toArray(Request $request): array` con:
    -   `id` ✅
    -   `nombre` ✅
    -   `codigo_descuento` ✅
    -   `descripcion` ✅
    -   `tipo` y `tipo_text` (accessor) ✅
    -   `valor` y `valor_formatted` ✅
    -   `aplicacion` y `aplicacion_text` (accessor) ✅
    -   `tipo_activacion` y `tipo_activacion_text` (accessor) ✅
    -   `dias_anticipacion` ✅
    -   `permite_acumulacion` ✅
    -   `fecha_inicio` (formateada) ✅
    -   `fecha_fin` (formateada) ✅
    -   `status` y `status_text` (del trait) ✅
    -   `esta_vigente` (método del modelo) ✅
    -   `listas_precios` (si está cargado) ✅
    -   `productos` (si está cargado) ✅
    -   `sedes` (si está cargado) ✅
    -   `poblaciones` (si está cargado) ✅
    -   `created_at` (formateada) ✅
    -   `updated_at` (formateada) ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

### 14. Resource `DescuentoAplicadoResource`

-   [x] Crear archivo `app/Http/Resources/Api/Financiero/Descuento/DescuentoAplicadoResource.php` ✅
-   [x] Extender de `JsonResource` ✅
-   [x] Implementar método `toArray(Request $request): array` con:
    -   `id` ✅
    -   `descuento_id` y `descuento` (si está cargado) ✅
    -   `concepto_tipo` ✅
    -   `concepto_id` ✅
    -   `valor_original` y `valor_original_formatted` ✅
    -   `valor_descuento` y `valor_descuento_formatted` ✅
    -   `valor_final` y `valor_final_formatted` ✅
    -   `producto_id` y `producto` (si está cargado) ✅
    -   `lista_precio_id` y `lista_precio` (si está cargado) ✅
    -   `sede_id` y `sede` (si está cargado) ✅
    -   `observaciones` ✅
    -   `created_at` (formateada) ✅
    -   `updated_at` (formateada) ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

---

## Fase 6: Service (Lógica de Negocio)

### 15. Service `DescuentoService`

-   [x] Crear archivo `app/Services/Financiero/DescuentoService.php` ✅
-   [x] Definir namespace `App\Services\Financiero` ✅
-   [x] Implementar método `obtenerDescuentosAplicables(...)` con parámetros:
    -   `int $productoId` ✅
    -   `int $listaPrecioId` ✅
    -   `?int $sedeId = null` ✅
    -   `?int $poblacionId = null` ✅
    -   `?Carbon $fecha = null` ✅
    -   `?string $codigoPromocional = null` ✅
    -   `?Carbon $fechaPago = null` ✅
    -   `?Carbon $fechaProgramada = null` ✅
-   [x] Implementar método `calcularPrecioConDescuentos(...)` con parámetros:
    -   `float $precioTotal` ✅
    -   `float $matricula` ✅
    -   `float $valorCuota` ✅
    -   `int $productoId` ✅
    -   `int $listaPrecioId` ✅
    -   `?int $sedeId = null` ✅
    -   `?int $poblacionId = null` ✅
    -   `?string $codigoPromocional = null` ✅
    -   `?Carbon $fechaPago = null` ✅
    -   `?Carbon $fechaProgramada = null` ✅
-   [x] Implementar lógica de acumulación de descuentos ✅
-   [x] Implementar lógica para seleccionar el descuento de mayor valor cuando no son acumulables ✅
-   [x] Implementar validación de que el valor final nunca sea menor a cero ✅
-   [x] Implementar registro en `descuento_aplicado` cuando se aplica un descuento ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

---

## Fase 7: Controller

### 16. Controller `DescuentoController`

-   [x] Crear archivo `app/Http/Controllers/Api/Financiero/Descuento/DescuentoController.php` ✅
-   [x] Extender de `Controller` ✅
-   [x] Implementar constructor con middlewares de autenticación y permisos ✅
-   [x] Implementar método `index(Request $request): JsonResponse`
    -   Aplicar filtros (search, tipo, aplicacion, tipo_activacion, status, fecha_inicio, fecha_fin) ✅
    -   Aplicar ordenamiento ✅
    -   Cargar relaciones opcionales ✅
    -   Paginar resultados ✅
    -   Retornar JSON con datos y meta ✅
-   [x] Implementar método `store(StoreDescuentoRequest $request): JsonResponse`
    -   Crear descuento ✅
    -   Sincronizar relaciones many-to-many (listas_precios, productos, sedes, poblaciones) ✅
    -   Retornar JSON con mensaje y datos ✅
-   [x] Implementar método `show(Request $request, Descuento $descuento): JsonResponse`
    -   Cargar relaciones opcionales ✅
    -   Retornar JSON con datos ✅
-   [x] Implementar método `update(UpdateDescuentoRequest $request, Descuento $descuento): JsonResponse`
    -   Actualizar descuento ✅
    -   Sincronizar relaciones many-to-many si están presentes ✅
    -   Retornar JSON con mensaje y datos ✅
-   [x] Implementar método `destroy(Descuento $descuento): JsonResponse`
    -   Soft delete del descuento ✅
    -   Retornar JSON con mensaje ✅
-   [x] Implementar método `aprobar(Descuento $descuento): JsonResponse`
    -   Cambiar status a `STATUS_APROBADO` ✅
    -   Retornar JSON con mensaje y datos ✅
-   [x] Implementar método `aplicarDescuento(Request $request): JsonResponse`
    -   Validar parámetros ✅
    -   Usar `DescuentoService::calcularPrecioConDescuentos()` ✅
    -   Retornar JSON con precios calculados ✅
-   [x] Implementar método `historial(Request $request): JsonResponse`
    -   Listar registros de `DescuentoAplicado` con filtros ✅
    -   Paginar resultados ✅
    -   Retornar JSON con datos y meta ✅
-   [x] Agregar documentación PHPDoc completa en español ✅

---

## Fase 8: Command (Gestión Automática de Estados)

### 17. Command `GestionarEstadosDescuentos`

-   [x] Crear archivo `app/Console/Commands/Financiero/GestionarEstadosDescuentos.php` ✅
-   [x] Extender de `Command` ✅
-   [x] Definir `$signature = 'financiero:gestionar-descuentos'` ✅
-   [x] Definir `$description = 'Gestiona automáticamente los estados de los descuentos'` ✅
-   [x] Implementar método `handle(): int`
    -   Activar descuentos aprobados que deben activarse ✅
    -   Inactivar descuentos activos que han perdido vigencia ✅
    -   Mostrar mensajes informativos ✅
    -   Retornar código de salida ✅
-   [x] Agregar documentación PHPDoc completa en español ✅
-   [x] Registrar el comando en `app/Console/Kernel.php` para ejecución diaria ✅

---

## Fase 9: Permisos y Rutas

### 18. Permisos en `RolesAndPermissionsSeeder`

-   [x] Agregar permisos para descuentos:
    -   `fin_descuentos` (ver descuentos) -> superusuario, financiero, coordinador ✅
    -   `fin_descuentoCrear` (crear descuento) -> superusuario, financiero, coordinador ✅
    -   `fin_descuentoEditar` (editar descuento) -> superusuario, financiero, coordinador ✅
    -   `fin_descuentoInactivar` (inactivar descuento) -> superusuario, financiero, coordinador ✅
    -   `fin_descuentoAprobar` (aprobar descuento) -> superusuario, financiero ✅
    -   `fin_descuentoAplicar` (aplicar descuento) -> superusuario, financiero, coordinador, auxiliar ✅
    -   `fin_descuentoHistorial` (ver historial) -> superusuario, financiero, coordinador ✅

### 19. Rutas en `routes/financiero.php`

-   [x] Agregar grupo de rutas para descuentos:
    -   `Route::apiResource('descuentos', DescuentoController::class)` ✅
    -   `Route::post('descuentos/{id}/aprobar', [DescuentoController::class, 'aprobar'])` ✅
    -   `Route::post('descuentos/aplicar', [DescuentoController::class, 'aplicarDescuento'])` ✅
    -   `Route::get('descuentos/historial', [DescuentoController::class, 'historial'])` ✅

---

## Fase 10: Factories y Seeders (Opcional)

### 20. Factory `DescuentoFactory`

-   [x] Crear archivo `database/factories/Financiero/Descuento/DescuentoFactory.php` ✅
-   [x] Definir atributos con Faker ✅
-   [x] Implementar estados para diferentes tipos de descuentos ✅

### 21. Factory `DescuentoAplicadoFactory`

-   [x] Crear archivo `database/factories/Financiero/Descuento/DescuentoAplicadoFactory.php` ✅
-   [x] Definir atributos con Faker ✅

### 22. Seeder `DescuentoSeeder` (Opcional)

-   [ ] Crear archivo `database/seeders/DescuentoSeeder.php`
-   [ ] Crear descuentos de ejemplo para pruebas

---

## Fase 11: Testing y Documentación

### 23. Verificación Final

-   [ ] Verificar que todas las migraciones se ejecutan correctamente
-   [ ] Verificar que los modelos tienen todas las relaciones correctas
-   [ ] Verificar que los requests validan correctamente
-   [ ] Verificar que el controller maneja todos los casos
-   [ ] Verificar que el service calcula correctamente los descuentos
-   [ ] Verificar que el command gestiona correctamente los estados
-   [ ] Verificar que los permisos están correctamente asignados
-   [ ] Verificar que las rutas están correctamente definidas
-   [ ] Probar acumulación de descuentos
-   [ ] Probar descuentos no acumulables
-   [ ] Probar aplicación de descuentos por código promocional
-   [ ] Probar aplicación de descuentos por pago anticipado
-   [ ] Probar aplicación de descuentos por promoción de matrícula
-   [ ] Verificar que el valor final nunca sea menor a cero
-   [ ] Verificar que cada cuota recibe el descuento una sola vez

---

## Resumen de Archivos a Crear

**Traits (1 archivo):** ✅ COMPLETADO

-   `app/Traits/Financiero/HasDescuentoStatus.php` ✅

**Modelos (2 archivos):** ✅ COMPLETADO

-   `app/Models/Financiero/Descuento/Descuento.php` ✅
-   `app/Models/Financiero/Descuento/DescuentoAplicado.php` ✅

**Migraciones (6 archivos):** ✅ COMPLETADO

-   `database/migrations/2025_12_02_100000_create_descuentos_table.php` ✅
-   `database/migrations/2025_12_02_100001_create_descuento_lista_precio_table.php` ✅
-   `database/migrations/2025_12_02_100002_create_descuento_producto_table.php` ✅
-   `database/migrations/2025_12_02_100003_create_descuento_sede_table.php` ✅
-   `database/migrations/2025_12_02_100004_create_descuento_poblacion_table.php` ✅
-   `database/migrations/2025_12_02_100005_create_descuento_aplicado_table.php` ✅

**Requests (2 archivos):** ✅ COMPLETADO

-   `app/Http/Requests/Api/Financiero/Descuento/StoreDescuentoRequest.php` ✅
-   `app/Http/Requests/Api/Financiero/Descuento/UpdateDescuentoRequest.php` ✅

**Resources (2 archivos):** ✅ COMPLETADO

-   `app/Http/Resources/Api/Financiero/Descuento/DescuentoResource.php` ✅
-   `app/Http/Resources/Api/Financiero/Descuento/DescuentoAplicadoResource.php` ✅

**Controladores (1 archivo):** ✅ COMPLETADO

-   `app/Http/Controllers/Api/Financiero/Descuento/DescuentoController.php` ✅

**Services (1 archivo):** ✅ COMPLETADO

-   `app/Services/Financiero/DescuentoService.php` ✅

**Commands (1 archivo):** ✅ COMPLETADO

-   `app/Console/Commands/Financiero/GestionarEstadosDescuentos.php` ✅

**Factories (2 archivos):** ✅ COMPLETADO

-   `database/factories/Financiero/Descuento/DescuentoFactory.php` ✅
-   `database/factories/Financiero/Descuento/DescuentoAplicadoFactory.php` ✅

**Archivos a Modificar:** ✅ COMPLETADO

-   `database/seeders/RolesAndPermissionsSeeder.php` (agregar permisos) ✅
-   `routes/financiero.php` (agregar rutas) ✅
-   `app/Console/Kernel.php` (registrar comando programado) ✅

**Total: 18 archivos nuevos + 3 archivos a modificar**
