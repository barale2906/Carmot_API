# Diseño del Módulo Financiero - Submódulo de Listas de Precios

## 1. Introducción

Este documento describe el diseño del submódulo de **Listas de Precios** dentro del módulo financiero del sistema. Este submódulo permite gestionar los precios de los diferentes productos ofrecidos por la institución, con capacidad de variación por ciudad y vigencia temporal.

## 2. Requisitos Funcionales

### 2.1 Tipos de Productos

El sistema debe manejar tres tipos de productos:

1. **Cursos** (Producto Principal)

    - Pueden ser financiados
    - Requieren configuración de matrícula y cuotas

2. **Módulos Específicos**

    - Pueden ofrecerse independientemente de un curso completo
    - Pueden ser financiados
    - Requieren configuración de matrícula y cuotas

3. **Productos Complementarios**
    - No se financian
    - Ejemplos: certificados de estudios, materiales, etc.
    - Solo tienen precio de contado

### 2.2 Características de Financiación

Para productos financiables (Cursos y Módulos):

-   **Valor Total**: Precio completo del producto
-   **Matrícula**: Valor inicial a pagar al momento de la inscripción (obligatorio, puede ser 0)
-   **Cuotas**: Número de pagos mensuales
-   **Valor de Cuota**: Se calcula y almacena automáticamente al crear/actualizar la lista de precios según:
    -   Valor total del producto
    -   Valor de matrícula pagada
    -   Número de cuotas
    -   **Redondeo al 100 más cercano** (ej: $5,530 → $5,500; $6,580 → $6,600)
-   **Pago de Contado**: Valor especial para pago único

### 2.3 Variación por Ubicación

-   Las listas de precios pueden variar según la **ciudad** (Población)
-   Cada lista de precios está asociada a una o más poblaciones

### 2.4 Vigencia y Estados

-   Cada lista de precios tiene una **fecha de inicio** y **fecha de fin**
-   La vigencia es editable
-   El sistema debe validar que no existan solapamientos de vigencia para la misma población y producto
-   **Estados de la lista de precios:**
    -   **0 - Inactiva**: Lista desactivada manualmente
    -   **1 - En Proceso**: Lista en edición, no se activará hasta que pase a aprobada
    -   **2 - Aprobada**: Lista aprobada, cambiará automáticamente a activa cuando inicie su período de vigencia
    -   **3 - Activa**: Lista en uso, solo las listas activas se utilizan para consultas de precios

## 3. Modelo de Datos

### 3.1 Estructura de Tablas

#### 3.1.1 Tabla: `lp_tipos_producto`

Define los tipos de productos disponibles en el sistema.

```sql
CREATE TABLE lp_tipos_producto (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre del tipo de producto',
    codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único del tipo (curso, modulo, complementario)',
    es_financiable BOOLEAN DEFAULT FALSE COMMENT 'Indica si el producto puede ser financiado',
    descripcion TEXT NULL,
    status TINYINT DEFAULT 1 COMMENT '0: inactivo, 1: activo',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_codigo (codigo),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Valores iniciales:**

-   `curso` - es_financiable: true
-   `modulo` - es_financiable: true
-   `complementario` - es_financiable: false

#### 3.1.2 Tabla: `lp_productos`

Catálogo general de productos (cursos, módulos y productos complementarios).

```sql
CREATE TABLE lp_productos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_producto_id BIGINT UNSIGNED NOT NULL COMMENT 'Tipo de producto',
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre del producto',
    codigo VARCHAR(100) UNIQUE NULL COMMENT 'Código único del producto',
    descripcion TEXT NULL,
    referencia_id BIGINT UNSIGNED NULL COMMENT 'ID del curso o módulo relacionado (si aplica)',
    referencia_tipo ENUM('curso', 'modulo') NULL COMMENT 'Tipo de referencia',
    status TINYINT DEFAULT 1 COMMENT '0: inactivo, 1: activo',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (tipo_producto_id) REFERENCES lp_tipos_producto(id) ON DELETE RESTRICT,
    INDEX idx_tipo_producto (tipo_producto_id),
    INDEX idx_referencia (referencia_id, referencia_tipo),
    INDEX idx_status (status),
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notas:**

-   `referencia_id` y `referencia_tipo` se usan para vincular productos con cursos o módulos existentes
-   Para productos complementarios, estos campos pueden ser NULL

#### 3.1.3 Tabla: `lp_listas_precios`

Define las listas de precios con su vigencia y alcance geográfico.

```sql
CREATE TABLE lp_listas_precios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo de la lista',
    codigo VARCHAR(100) UNIQUE NULL COMMENT 'Código único de la lista',
    fecha_inicio DATE NOT NULL COMMENT 'Fecha de inicio de vigencia',
    fecha_fin DATE NOT NULL COMMENT 'Fecha de fin de vigencia',
    descripcion TEXT NULL,
    status TINYINT DEFAULT 1 COMMENT '0: inactiva, 1: en proceso, 2: aprobada, 3: activa',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_fechas (fecha_inicio, fecha_fin),
    INDEX idx_status (status),
    INDEX idx_codigo (codigo),
    CHECK (fecha_fin >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Estados de la lista:**

-   **0 - Inactiva**: Lista desactivada manualmente
-   **1 - En Proceso**: Lista en edición, no se activará hasta que pase a aprobada
-   **2 - Aprobada**: Lista aprobada, cambiará automáticamente a activa cuando inicie su período de vigencia
-   **3 - Activa**: Lista en uso, solo las listas activas se utilizan para consultas de precios

#### 3.1.4 Tabla: `lp_lista_precio_poblacion`

Relación muchos a muchos entre listas de precios y poblaciones (ciudades).

```sql
CREATE TABLE lp_lista_precio_poblacion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lista_precio_id BIGINT UNSIGNED NOT NULL,
    poblacion_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (lista_precio_id) REFERENCES lp_listas_precios(id) ON DELETE CASCADE,
    FOREIGN KEY (poblacion_id) REFERENCES poblacions(id) ON DELETE CASCADE,
    UNIQUE KEY uk_lista_poblacion (lista_precio_id, poblacion_id),
    INDEX idx_lista_precio (lista_precio_id),
    INDEX idx_poblacion (poblacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.5 Tabla: `lp_precios_producto`

Define los precios de cada producto dentro de una lista de precios.

```sql
CREATE TABLE lp_precios_producto (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lista_precio_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,

    -- Precio de contado
    precio_contado DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio para pago de contado',

    -- Financiación (solo para productos financiables)
    precio_total DECIMAL(15, 2) NULL COMMENT 'Precio total del producto (para financiación)',
    matricula DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de la matrícula (obligatorio para cursos y módulos, puede ser 0)',
    numero_cuotas INT NULL COMMENT 'Número de cuotas',
    valor_cuota DECIMAL(15, 2) NULL COMMENT 'Valor calculado de cada cuota (redondeado al 100) - se calcula al crear/actualizar',

    -- Metadatos
    observaciones TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (lista_precio_id) REFERENCES lp_listas_precios(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES lp_productos(id) ON DELETE CASCADE,

    UNIQUE KEY uk_lista_producto (lista_precio_id, producto_id),
    INDEX idx_lista_precio (lista_precio_id),
    INDEX idx_producto (producto_id),

    -- Validaciones
    CHECK (precio_contado >= 0),
    CHECK (precio_total IS NULL OR precio_total >= 0),
    CHECK (matricula >= 0),
    CHECK (numero_cuotas IS NULL OR numero_cuotas > 0),
    CHECK (valor_cuota IS NULL OR valor_cuota >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas de negocio:**

-   Para productos financiables (cursos y módulos): `precio_total`, `matricula` (obligatorio, puede ser 0), `numero_cuotas` y `valor_cuota` son obligatorios
-   Para productos no financiables: solo `precio_contado` es obligatorio
-   `valor_cuota` se calcula y almacena automáticamente al crear/actualizar la lista de precios: `(precio_total - matricula) / numero_cuotas` y se redondea al 100 más cercano
-   Los valores de financiación y cuotas NO se recalculan al consultar, solo se almacenan al crear/actualizar

## 4. Traits Personalizados

### 4.1 Trait: `HasListaPrecioStatus`

Trait específico para manejar los estados de las listas de precios.

**Archivo:** `app/Traits/Financiero/HasListaPrecioStatus.php`

```php
<?php

namespace App\Traits\Financiero;

trait HasListaPrecioStatus
{
    /**
     * Obtiene las opciones de estado para Lista de Precios.
     *
     * @return array<string, string> Array con los estados disponibles
     */
    public static function getStatusOptions(): array
    {
        return [
            0 => 'Inactiva',
            1 => 'En Proceso',
            2 => 'Aprobada',
            3 => 'Activa',
        ];
    }

    /**
     * Obtiene el texto del estado basado en el número de estado.
     *
     * @param int|null $status Número del estado
     * @return string Descripción del estado
     */
    public static function getStatusText(?int $status): string
    {
        $statusOptions = self::getStatusOptions();

        return $statusOptions[$status] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del estado para la instancia actual del modelo.
     *
     * @return string Descripción del estado
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusText($this->status);
    }

    /**
     * Obtiene las opciones de estado en formato para validación.
     *
     * @return string String con los valores válidos para validación
     */
    public static function getStatusValidationRule(): string
    {
        $statuses = array_keys(self::getStatusOptions());
        return 'sometimes|integer|in:' . implode(',', $statuses);
    }

    /**
     * Obtiene los mensajes de error para el campo status.
     *
     * @return array<string, string>
     */
    public static function getStatusValidationMessages(): array
    {
        $statusOptions = self::getStatusOptions();
        $statusList = [];

        foreach ($statusOptions as $key => $value) {
            $statusList[] = "$key ($value)";
        }

        return [
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in' => 'El estado debe ser uno de los valores válidos: ' . implode(', ', $statusList) . '.',
        ];
    }

    /**
     * Scope para filtrar por estado inactiva.
     */
    public function scopeInactiva($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para filtrar por estado en proceso.
     */
    public function scopeEnProceso($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para filtrar por estado aprobada.
     */
    public function scopeAprobada($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope para filtrar por estado activa.
     */
    public function scopeActiva($query)
    {
        return $query->where('status', 3);
    }
}
```

## 5. Modelos Eloquent

### 5.1 Modelo: `LpTipoProducto`

```php
<?php

namespace App\Models\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LpTipoProducto extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $table = 'lp_tipos_producto';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'es_financiable' => 'boolean',
        'status' => 'integer',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(LpProducto::class, 'tipo_producto_id');
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'codigo',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     */
    protected function getAllowedRelations(): array
    {
        return [
            'productos'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return [];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['productos'];
    }
}
```

### 5.2 Modelo: `LpProducto`

```php
<?php

namespace App\Models\Financiero\Lp;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LpProducto extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $table = 'lp_productos';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'status' => 'integer',
    ];

    public function tipoProducto(): BelongsTo
    {
        return $this->belongsTo(LpTipoProducto::class, 'tipo_producto_id');
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo('referencia', 'referencia_tipo', 'referencia_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(LpPrecioProducto::class, 'producto_id');
    }

    public function listasPrecios(): BelongsToMany
    {
        return $this->belongsToMany(LpListaPrecio::class, 'lp_precios_producto', 'producto_id', 'lista_precio_id')
                    ->withPivot(['precio_contado', 'precio_total', 'matricula',
                                'numero_cuotas', 'valor_cuota'])
                    ->withTimestamps();
    }

    /**
     * Verifica si el producto es financiable
     */
    public function esFinanciable(): bool
    {
        return $this->tipoProducto->es_financiable ?? false;
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'codigo',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     */
    protected function getAllowedRelations(): array
    {
        return [
            'tipoProducto',
            'referencia',
            'precios',
            'listasPrecios'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['tipoProducto'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['precios', 'listasPrecios'];
    }
}
```

### 5.3 Modelo: `LpListaPrecio`

```php
<?php

namespace App\Models\Financiero\Lp;

use App\Models\Configuracion\Poblacion;
use App\Traits\Financiero\HasListaPrecioStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LpListaPrecio extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes, HasListaPrecioStatus;

    protected $table = 'lp_listas_precios';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'status' => 'integer',
    ];

    // Constantes de estado
    const STATUS_INACTIVA = 0;
    const STATUS_EN_PROCESO = 1;
    const STATUS_APROBADA = 2;
    const STATUS_ACTIVA = 3;

    public function poblaciones(): BelongsToMany
    {
        return $this->belongsToMany(Poblacion::class, 'lp_lista_precio_poblacion', 'lista_precio_id', 'poblacion_id')
                    ->withTimestamps();
    }

    public function preciosProductos(): HasMany
    {
        return $this->hasMany(LpPrecioProducto::class, 'lista_precio_id');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(LpProducto::class, 'lp_precios_producto', 'lista_precio_id', 'producto_id')
                    ->withPivot(['precio_contado', 'precio_total', 'matricula',
                                'numero_cuotas', 'valor_cuota'])
                    ->withTimestamps();
    }

    /**
     * Verifica si la lista está vigente en una fecha específica
     * Solo las listas activas (status = 3) están vigentes
     */
    public function estaVigente(?Carbon $fecha = null): bool
    {
        $fecha = $fecha ?? Carbon::now();
        return $fecha->between($this->fecha_inicio, $this->fecha_fin)
            && $this->status === self::STATUS_ACTIVA;
    }

    /**
     * Scope para listas vigentes (solo activas)
     */
    public function scopeVigentes($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('fecha_inicio', '<=', $fecha)
                    ->where('fecha_fin', '>=', $fecha)
                    ->where('status', self::STATUS_ACTIVA);
    }

    /**
     * Scope para listas aprobadas que deben activarse automáticamente
     */
    public function scopeAprobadasParaActivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::STATUS_APROBADA)
                    ->where('fecha_inicio', '<=', $fecha);
    }

    /**
     * Scope para listas activas que deben inactivarse por pérdida de vigencia
     */
    public function scopeActivasParaInactivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::STATUS_ACTIVA)
                    ->where('fecha_fin', '<', $fecha);
    }

    /**
     * Activa automáticamente las listas aprobadas cuando inicia su vigencia
     * Este método debe ejecutarse mediante un comando programado (cron)
     */
    public static function activarListasAprobadas(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::aprobadasParaActivar($fecha)->update([
            'status' => self::STATUS_ACTIVA
        ]);
    }

    /**
     * Inactiva automáticamente las listas activas que pierden su vigencia
     * Este método debe ejecutarse mediante un comando programado (cron)
     */
    public static function inactivarListasVencidas(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::activasParaInactivar($fecha)->update([
            'status' => self::STATUS_INACTIVA
        ]);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'codigo',
            'fecha_inicio',
            'fecha_fin',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     */
    protected function getAllowedRelations(): array
    {
        return [
            'poblaciones',
            'preciosProductos',
            'productos',
            'preciosProductos.producto',
            'preciosProductos.producto.tipoProducto'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['poblaciones'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['poblaciones', 'preciosProductos', 'productos'];
    }
}
```

### 5.4 Modelo: `LpPrecioProducto`

```php
<?php

namespace App\Models\Financiero\Lp;

use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LpPrecioProducto extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes;

    protected $table = 'lp_precios_producto';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'precio_contado' => 'decimal:2',
        'precio_total' => 'decimal:2',
        'matricula' => 'decimal:2',
        'numero_cuotas' => 'integer',
        'valor_cuota' => 'decimal:2',
    ];

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(LpListaPrecio::class, 'lista_precio_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(LpProducto::class, 'producto_id');
    }

    /**
     * Calcula el valor de la cuota redondeado al 100 más cercano
     * Este método solo se usa al crear/actualizar, NO al consultar
     */
    public function calcularValorCuota(): float
    {
        if (!$this->producto || !$this->producto->esFinanciable() ||
            !$this->precio_total ||
            $this->matricula === null ||
            !$this->numero_cuotas || $this->numero_cuotas <= 0) {
            return 0;
        }

        $valorRestante = $this->precio_total - $this->matricula;
        $valorCuota = $valorRestante / $this->numero_cuotas;

        // Redondear al 100 más cercano
        return round($valorCuota / 100) * 100;
    }

    /**
     * Boot del modelo - calcula automáticamente el valor de la cuota al guardar
     * Solo se ejecuta al crear o actualizar, no al consultar
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($precioProducto) {
            // Cargar la relación si no está cargada
            if (!$precioProducto->relationLoaded('producto')) {
                $precioProducto->load('producto');
            }

            if ($precioProducto->producto && $precioProducto->producto->esFinanciable()) {
                // Calcular y almacenar el valor de la cuota
                $precioProducto->valor_cuota = $precioProducto->calcularValorCuota();
            } else {
                // Para productos no financiables, limpiar valores de financiación
                $precioProducto->valor_cuota = null;
            }
        });
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'precio_contado',
            'precio_total',
            'matricula',
            'numero_cuotas',
            'valor_cuota',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     */
    protected function getAllowedRelations(): array
    {
        return [
            'listaPrecio',
            'producto',
            'producto.tipoProducto'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['producto', 'listaPrecio'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return [];
    }
}
```

## 6. Gestión de Estados y Activación Automática

### 6.1 Flujo de Estados de Lista de Precios

El flujo de estados de una lista de precios es el siguiente:

1. **En Proceso (1)**: La lista se crea en estado "en proceso" y puede ser editada libremente. No se activará hasta que pase a aprobada.

2. **Aprobada (2)**: Una vez aprobada, la lista no puede editarse directamente. Cambiará automáticamente a activa cuando inicie su período de vigencia.

3. **Activa (3)**: Solo las listas activas se utilizan para consultas de precios. Se activan automáticamente cuando:

    - La lista está en estado "aprobada" (2)
    - La fecha actual es mayor o igual a `fecha_inicio`

    Se inactivan automáticamente cuando:

    - La lista está en estado "activa" (3)
    - La fecha actual es mayor que `fecha_fin`

4. **Inactiva (0)**: Lista desactivada manualmente o automáticamente por pérdida de vigencia, ya no se utiliza para consultas.

### 6.2 Comando Programado para Gestión Automática de Estados

Se debe crear un comando de Laravel que se ejecute diariamente (mediante cron) para:

1. Activar automáticamente las listas aprobadas cuando inicia su período de vigencia
2. Inactivar automáticamente las listas activas que pierden su vigencia

```php
<?php

namespace App\Console\Commands\Financiero;

use App\Models\Financiero\Lp\LpListaPrecio;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ActivarListasPreciosAprobadas extends Command
{
    protected $signature = 'financiero:gestionar-listas-precios';
    protected $description = 'Activa listas aprobadas e inactiva listas vencidas automáticamente';

    public function handle()
    {
        $fechaActual = Carbon::now();

        // Activar listas aprobadas que inician su vigencia
        $activadas = LpListaPrecio::aprobadasParaActivar($fechaActual)->get();

        foreach ($activadas as $lista) {
            $lista->update(['status' => LpListaPrecio::STATUS_ACTIVA]);
            $this->info("Lista de precios '{$lista->nombre}' activada automáticamente");
        }

        $this->info("Total de listas activadas: " . $activadas->count());

        // Inactivar listas activas que pierden su vigencia
        $inactivadas = LpListaPrecio::activasParaInactivar($fechaActual)->get();

        foreach ($inactivadas as $lista) {
            $lista->update(['status' => LpListaPrecio::STATUS_INACTIVA]);
            $this->info("Lista de precios '{$lista->nombre}' inactivada automáticamente (vigencia vencida)");
        }

        $this->info("Total de listas inactivadas: " . $inactivadas->count());

        return Command::SUCCESS;
    }
}
```

**Configuración del cron (en `app/Console/Kernel.php`):**

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('financiero:gestionar-listas-precios')
             ->daily();
}
```

**Nota:** El comando ahora gestiona tanto la activación de listas aprobadas como la inactivación de listas vencidas en una sola ejecución diaria.

## 7. Servicios y Lógica de Negocio

### 7.1 Servicio: `LpPrecioProductoService`

```php
<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Configuracion\Poblacion;
use Carbon\Carbon;

class LpPrecioProductoService
{
    /**
     * Redondea un valor al 100 más cercano
     *
     * @param float $valor
     * @return float
     */
    public function redondearACien(float $valor): float
    {
        return round($valor / 100) * 100;
    }

    /**
     * Calcula el valor de la cuota para un producto financiable
     *
     * @param float $precioTotal
     * @param float $matricula
     * @param int $numeroCuotas
     * @return float
     */
    public function calcularCuota(float $precioTotal, float $matricula, int $numeroCuotas): float
    {
        if ($numeroCuotas <= 0) {
            return 0;
        }

        $valorRestante = $precioTotal - $matricula;
        $valorCuota = $valorRestante / $numeroCuotas;

        return $this->redondearACien($valorCuota);
    }

    /**
     * Obtiene el precio de un producto para una población y fecha específica
     *
     * @param int $productoId
     * @param int $poblacionId
     * @param Carbon|null $fecha
     * @return PrecioProducto|null
     */
    public function obtenerPrecio(int $productoId, int $poblacionId, ?Carbon $fecha = null): ?PrecioProducto
    {
        $fecha = $fecha ?? Carbon::now();

        $listaPrecio = LpListaPrecio::whereHas('poblaciones', function ($query) use ($poblacionId) {
            $query->where('poblacions.id', $poblacionId);
        })
        ->vigentes($fecha) // Solo retorna listas activas (status = 3)
        ->first();

        if (!$listaPrecio) {
            return null;
        }

        return LpPrecioProducto::where('lista_precio_id', $listaPrecio->id)
            ->where('producto_id', $productoId)
            ->first();
    }

    /**
     * Valida que no existan solapamientos de vigencia para una población
     *
     * @param int $poblacionId
     * @param Carbon $fechaInicio
     * @param Carbon $fechaFin
     * @param int|null $excluirListaId
     * @return bool
     */
    public function validarSolapamientoVigencia(
        int $poblacionId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?int $excluirListaId = null
    ): bool {
        $query = LpListaPrecio::whereHas('poblaciones', function ($q) use ($poblacionId) {
            $q->where('poblacions.id', $poblacionId);
        })
        ->where('status', LpListaPrecio::STATUS_ACTIVA) // Solo validar solapamientos con listas activas
        ->where(function ($q) use ($fechaInicio, $fechaFin) {
            $q->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
              ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
              ->orWhere(function ($q2) use ($fechaInicio, $fechaFin) {
                  $q2->where('fecha_inicio', '<=', $fechaInicio)
                     ->where('fecha_fin', '>=', $fechaFin);
              });
        });

        if ($excluirListaId) {
            $query->where('id', '!=', $excluirListaId);
        }

        return $query->count() === 0;
    }
}
```

## 8. Ejemplos de Uso

### 8.1 Crear una Lista de Precios

```php
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use Carbon\Carbon;

// Crear lista de precios en estado "en proceso"
$listaPrecio = LpListaPrecio::create([
    'nombre' => 'Lista de Precios 2024 - Bogotá',
    'codigo' => 'LP-BOG-2024',
    'fecha_inicio' => Carbon::parse('2024-01-01'),
    'fecha_fin' => Carbon::parse('2024-12-31'),
    'status' => LpListaPrecio::STATUS_EN_PROCESO, // Estado inicial: en proceso
]);

// Asociar a poblaciones
$listaPrecio->poblaciones()->attach([1, 2, 3]); // IDs de poblaciones

// Agregar precio de un curso
$productoCurso = LpProducto::where('codigo', 'CURSO-001')->first();
$precioCurso = LpPrecioProducto::create([
    'lista_precio_id' => $listaPrecio->id,
    'producto_id' => $productoCurso->id,
    'precio_contado' => 2000000,
    'precio_total' => 2500000,
    'matricula' => 500000, // Obligatorio para cursos y módulos (puede ser 0)
    'numero_cuotas' => 10,
    // valor_cuota se calcula y almacena automáticamente al guardar: (2500000 - 500000) / 10 = 200000
]);

// Agregar precio de un producto complementario
$productoComplementario = LpProducto::where('codigo', 'CERT-001')->first();
$precioComplementario = LpPrecioProducto::create([
    'lista_precio_id' => $listaPrecio->id,
    'producto_id' => $productoComplementario->id,
    'precio_contado' => 50000,
    // No requiere campos de financiación
]);
```

### 8.2 Aprobar una Lista de Precios

```php
// Cambiar estado de "en proceso" a "aprobada"
$listaPrecio->update([
    'status' => LpListaPrecio::STATUS_APROBADA
]);

// La lista se activará automáticamente cuando la fecha actual >= fecha_inicio
// mediante el comando programado diario
```

### 8.3 Consultar Precios

```php
use App\Services\Financiero\LpPrecioProductoService;

$service = new LpPrecioProductoService();

// Obtener precio de un producto para una población
$precio = $service->obtenerPrecio(
    productoId: 1,
    poblacionId: 5,
    fecha: Carbon::now()
);

if ($precio) {
    echo "Precio de contado: $" . number_format($precio->precio_contado, 2);

    if ($precio->producto->esFinanciable()) {
        echo "Precio total: $" . number_format($precio->precio_total, 2);
        echo "Matrícula: $" . number_format($precio->matricula, 2);
        echo "Cuotas: {$precio->numero_cuotas} x $" . number_format($precio->valor_cuota, 2);
    }
}
```

### 8.4 Ejemplo de Cálculo de Cuotas

```php
$service = new LpPrecioProductoService();

// Ejemplo 1: $5,530 se redondea a $5,500
$cuota1 = $service->calcularCuota(
    precioTotal: 100000,
    matricula: 44500,
    numeroCuotas: 10
);
// Resultado: (100000 - 44500) / 10 = 5550 → redondeado a 5500

// Ejemplo 2: $6,580 se redondea a $6,600
$cuota2 = $service->calcularCuota(
    precioTotal: 100000,
    matricula: 34200,
    numeroCuotas: 10
);
// Resultado: (100000 - 34200) / 10 = 6580 → redondeado a 6600
```

## 9. Validaciones y Reglas de Negocio

### 9.1 Validaciones de Lista de Precios

-   La fecha de fin debe ser mayor o igual a la fecha de inicio
-   No pueden existir dos listas de precios activas (status = 3) con vigencia solapada para la misma población
-   El código de la lista debe ser único
-   Las listas en proceso (status = 1) no pueden activarse hasta que pasen a aprobadas (status = 2)
-   Las listas aprobadas (status = 2) se activan automáticamente cuando inicia su período de vigencia

### 9.2 Validaciones de Precio de Producto

-   Para productos financiables (cursos y módulos):
    -   `precio_total` > 0
    -   `matricula` >= 0 y < `precio_total` (obligatorio, puede ser 0)
    -   `numero_cuotas` > 0
    -   `valor_cuota` se calcula y almacena automáticamente al crear/actualizar y debe ser >= 0
-   Para productos no financiables:
    -   Solo `precio_contado` es obligatorio
    -   `precio_contado` >= 0

**Importante:** Los valores de financiación (`precio_total`, `matricula`, `numero_cuotas`, `valor_cuota`) se calculan y almacenan únicamente al crear o actualizar la lista de precios. No se recalculan al consultar.

### 9.3 Validaciones de Producto

-   El código del producto debe ser único (si se proporciona)
-   `referencia_id` y `referencia_tipo` deben ser consistentes:
    -   Si `referencia_tipo` es 'curso', `referencia_id` debe existir en la tabla `cursos`
    -   Si `referencia_tipo` es 'modulo', `referencia_id` debe existir en la tabla `modulos`

## 10. Consideraciones de Implementación

### 10.1 Migración de Datos Existentes

Si ya existen cursos y módulos en el sistema, se debe crear un proceso de migración para:

1. Crear tipos de producto iniciales
2. Crear productos basados en cursos existentes
3. Crear productos basados en módulos existentes
4. Crear productos complementarios según necesidad

### 10.2 Integración con Módulo de Facturación

Este diseño prepara la base para el módulo de facturación, donde se utilizarán estos precios para:

-   Generar cotizaciones
-   Crear facturas
-   Registrar pagos de matrícula y cuotas
-   Gestionar planes de pago

### 10.3 Historial de Precios

Para mantener un historial de cambios de precios, se recomienda:

-   Usar soft deletes en `precios_producto` para mantener historial
-   Considerar una tabla de auditoría para cambios importantes
-   Mantener las listas de precios antiguas para consulta histórica

### 10.4 Performance

-   Indexar adecuadamente las tablas para consultas frecuentes:

    -   Búsqueda por población y fecha
    -   Búsqueda por producto
    -   Validación de solapamientos

-   Considerar caché para listas de precios vigentes frecuentemente consultadas

## 11. Lista de Verificación de Implementación

Esta sección contiene la lista completa de pasos y archivos necesarios para implementar el submódulo de Listas de Precios.

### 11.1 Archivos a Crear

#### 11.1.1 Trait Personalizado

**Archivo:** `app/Traits/Financiero/HasListaPrecioStatus.php`

-   Trait para manejar estados de listas de precios (inactiva, en proceso, aprobada, activa)
-   Incluir métodos: `getStatusOptions()`, `getStatusText()`, `getStatusTextAttribute()`, `getStatusValidationRule()`, `getStatusValidationMessages()`
-   Incluir scopes: `scopeInactiva()`, `scopeEnProceso()`, `scopeAprobada()`, `scopeActiva()`

#### 11.1.2 Modelos Eloquent

1. **`app/Models/Financiero/Lp/LpTipoProducto.php`**

    - Tabla: `lp_tipos_producto`
    - Traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasActiveStatus`
    - Relaciones: `productos()` HasMany
    - Métodos: `getAllowedSortFields()`, `getAllowedRelations()`, `getDefaultRelations()`, `getCountableRelations()`

2. **`app/Models/Financiero/Lp/LpProducto.php`**

    - Tabla: `lp_productos`
    - Traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasActiveStatus`
    - Relaciones: `tipoProducto()` BelongsTo, `referencia()` MorphTo, `precios()` HasMany, `listasPrecios()` BelongsToMany
    - Métodos: `esFinanciable()`, `getAllowedSortFields()`, `getAllowedRelations()`, `getDefaultRelations()`, `getCountableRelations()`

3. **`app/Models/Financiero/Lp/LpListaPrecio.php`**

    - Tabla: `lp_listas_precios`
    - Traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`, `HasListaPrecioStatus`
    - Relaciones: `poblaciones()` BelongsToMany, `preciosProductos()` HasMany, `productos()` BelongsToMany
    - Métodos: `estaVigente()`, `scopeVigentes()`, `scopeAprobadasParaActivar()`, `activarListasAprobadas()`, `getAllowedSortFields()`, `getAllowedRelations()`, `getDefaultRelations()`, `getCountableRelations()`
    - Constantes: `STATUS_INACTIVA`, `STATUS_EN_PROCESO`, `STATUS_APROBADA`, `STATUS_ACTIVA`

4. **`app/Models/Financiero/Lp/LpPrecioProducto.php`**
    - Tabla: `lp_precios_producto`
    - Traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`
    - Relaciones: `listaPrecio()` BelongsTo, `producto()` BelongsTo
    - Métodos: `calcularValorCuota()`, `boot()` (para cálculo automático), `getAllowedSortFields()`, `getAllowedRelations()`, `getDefaultRelations()`, `getCountableRelations()`

#### 11.1.3 Migraciones de Base de Datos

1. **`database/migrations/YYYY_MM_DD_HHMMSS_create_lp_tipos_producto_table.php`**

    - Crear tabla `lp_tipos_producto`
    - Campos: id, nombre, codigo (unique), es_financiable, descripcion, status, timestamps, deleted_at
    - Índices: codigo, status

2. **`database/migrations/YYYY_MM_DD_HHMMSS_create_lp_productos_table.php`**

    - Crear tabla `lp_productos`
    - Campos: id, tipo_producto_id (FK), nombre, codigo (unique nullable), descripcion, referencia_id (nullable), referencia_tipo (enum nullable), status, timestamps, deleted_at
    - Foreign keys: tipo_producto_id -> lp_tipos_producto
    - Índices: tipo_producto_id, referencia (composite), status, codigo

3. **`database/migrations/YYYY_MM_DD_HHMMSS_create_lp_listas_precios_table.php`**

    - Crear tabla `lp_listas_precios`
    - Campos: id, nombre, codigo (unique nullable), fecha_inicio, fecha_fin, descripcion, status, timestamps, deleted_at
    - Índices: fechas (composite), status, codigo
    - Check constraint: fecha_fin >= fecha_inicio

4. **`database/migrations/YYYY_MM_DD_HHMMSS_create_lp_lista_precio_poblacion_table.php`**

    - Crear tabla `lp_lista_precio_poblacion`
    - Campos: id, lista_precio_id (FK), poblacion_id (FK), timestamps
    - Foreign keys: lista_precio_id -> lp_listas_precios (CASCADE), poblacion_id -> poblacions (CASCADE)
    - Unique constraint: (lista_precio_id, poblacion_id)
    - Índices: lista_precio_id, poblacion_id

5. **`database/migrations/YYYY_MM_DD_HHMMSS_create_lp_precios_producto_table.php`**
    - Crear tabla `lp_precios_producto`
    - Campos: id, lista_precio_id (FK), producto_id (FK), precio_contado, precio_total (nullable), matricula (NOT NULL DEFAULT 0), numero_cuotas (nullable), valor_cuota (nullable), observaciones, timestamps, deleted_at
    - Foreign keys: lista_precio_id -> lp_listas_precios (CASCADE), producto_id -> lp_productos (CASCADE)
    - Unique constraint: (lista_precio_id, producto_id)
    - Índices: lista_precio_id, producto_id
    - Check constraints: precio_contado >= 0, precio_total >= 0 (si no null), matricula >= 0, numero_cuotas > 0 (si no null), valor_cuota >= 0 (si no null)

#### 11.1.4 Seeders

1. **`database/seeders/LpTipoProductoSeeder.php`**

    - Crear tipos de producto iniciales: curso (financiable), modulo (financiable), complementario (no financiable)

2. **`database/seeders/LpProductoSeeder.php`** (Opcional)
    - Crear productos de ejemplo basados en cursos y módulos existentes

#### 11.1.5 Servicios

1. **`app/Services/Financiero/LpPrecioProductoService.php`**
    - Métodos: `redondearACien()`, `calcularCuota()`, `obtenerPrecio()`, `validarSolapamientoVigencia()`

#### 11.1.6 Comandos de Consola

1. **`app/Console/Commands/Financiero/ActivarListasPreciosAprobadas.php`**
    - Comando para gestionar automáticamente el estado de las listas de precios:
        - Activar listas aprobadas cuando inicia su vigencia
        - Inactivar listas activas que pierden su vigencia
    - Signature: `financiero:gestionar-listas-precios`
    - Programar en `app/Console/Kernel.php` para ejecución diaria

#### 11.1.7 Requests (Validación)

1. **`app/Http/Requests/Api/Financiero/Lp/StoreLpTipoProductoRequest.php`**

    - Validaciones: nombre (required, string, max:255), codigo (required, string, max:50, unique:lp_tipos_producto), es_financiable (boolean), descripcion (nullable, string), status (usar HasActiveStatusValidation)

2. **`app/Http/Requests/Api/Financiero/Lp/UpdateLpTipoProductoRequest.php`**

    - Similar a Store pero con reglas de actualización

3. **`app/Http/Requests/Api/Financiero/Lp/StoreLpProductoRequest.php`**

    - Validaciones: tipo_producto_id (required, exists:lp_tipos_producto), nombre (required, string, max:255), codigo (nullable, string, max:100, unique:lp_productos), descripcion (nullable, string), referencia_id (nullable, required_with:referencia_tipo), referencia_tipo (nullable, in:curso,modulo), status (usar HasActiveStatusValidation)

4. **`app/Http/Requests/Api/Financiero/Lp/UpdateLpProductoRequest.php`**

    - Similar a Store pero con reglas de actualización

5. **`app/Http/Requests/Api/Financiero/Lp/StoreLpListaPrecioRequest.php`**

    - Validaciones: nombre (required, string, max:255), codigo (nullable, string, max:100, unique:lp_listas_precios), fecha_inicio (required, date), fecha_fin (required, date, after_or_equal:fecha_inicio), descripcion (nullable, string), status (usar HasListaPrecioStatus validation), poblaciones (required, array, exists:poblacions,id)

6. **`app/Http/Requests/Api/Financiero/Lp/UpdateLpListaPrecioRequest.php`**

    - Similar a Store pero con reglas de actualización

7. **`app/Http/Requests/Api/Financiero/Lp/StoreLpPrecioProductoRequest.php`**

    - Validaciones: lista_precio_id (required, exists:lp_listas_precios), producto_id (required, exists:lp_productos), precio_contado (required, numeric, min:0), precio_total (nullable, required_if:producto.es_financiable, numeric, min:0), matricula (required, numeric, min:0), numero_cuotas (nullable, required_if:producto.es_financiable, integer, min:1), observaciones (nullable, string)

8. **`app/Http/Requests/Api/Financiero/Lp/UpdateLpPrecioProductoRequest.php`**
    - Similar a Store pero con reglas de actualización

#### 11.1.8 Controladores

1. **`app/Http/Controllers/Api/Financiero/Lp/LpTipoProductoController.php`**

    - Métodos: `index()`, `store()`, `show()`, `update()`, `destroy()`
    - Usar Resource para respuestas
    - Implementar filtros, ordenamiento y relaciones según estructura existente

2. **`app/Http/Controllers/Api/Financiero/Lp/LpProductoController.php`**

    - Métodos: `index()`, `store()`, `show()`, `update()`, `destroy()`
    - Incluir filtro por tipo_producto_id, referencia_tipo, es_financiable

3. **`app/Http/Controllers/Api/Financiero/Lp/LpListaPrecioController.php`**

    - Métodos: `index()`, `store()`, `show()`, `update()`, `destroy()`, `aprobar()`, `activar()`, `inactivar()`
    - Incluir filtros por status, fecha_inicio, fecha_fin, poblacion_id
    - Validar solapamientos al crear/actualizar

4. **`app/Http/Controllers/Api/Financiero/Lp/LpPrecioProductoController.php`**
    - Métodos: `index()`, `store()`, `show()`, `update()`, `destroy()`
    - Incluir filtros por lista_precio_id, producto_id
    - Endpoint especial: `obtenerPrecio()` para consultar precio por producto y población

#### 11.1.9 Resources (Transformadores)

1. **`app/Http/Resources/Api/Financiero/Lp/LpTipoProductoResource.php`**

    - Incluir: id, nombre, codigo, es_financiable, descripcion, status, status_text (usar trait), timestamps

2. **`app/Http/Resources/Api/Financiero/Lp/LpProductoResource.php`**

    - Incluir: id, tipo_producto_id, tipo_producto (relación), nombre, codigo, descripcion, referencia_id, referencia_tipo, referencia (relación), status, status_text (usar trait), timestamps

3. **`app/Http/Resources/Api/Financiero/Lp/LpListaPrecioResource.php`**

    - Incluir: id, nombre, codigo, fecha_inicio, fecha_fin, descripcion, status, status_text (usar trait), poblaciones (relación), precios_productos (relación), timestamps

4. **`app/Http/Resources/Api/Financiero/Lp/LpPrecioProductoResource.php`**
    - Incluir: id, lista_precio_id, lista_precio (relación), producto_id, producto (relación), precio_contado, precio_total, matricula, numero_cuotas, valor_cuota, observaciones, timestamps

#### 11.1.10 Rutas

**Archivo:** `routes/financiero.php` (crear si no existe) o agregar a `routes/api.php`

```php
Route::prefix('financiero/lp')->middleware(['auth:sanctum'])->group(function () {
    // Tipos de Producto
    Route::apiResource('tipos-producto', LpTipoProductoController::class);

    // Productos
    Route::apiResource('productos', LpProductoController::class);

    // Listas de Precios
    Route::apiResource('listas-precios', LpListaPrecioController::class);
    Route::post('listas-precios/{id}/aprobar', [LpListaPrecioController::class, 'aprobar']);
    Route::post('listas-precios/{id}/activar', [LpListaPrecioController::class, 'activar']);
    Route::post('listas-precios/{id}/inactivar', [LpListaPrecioController::class, 'inactivar']);

    // Precios de Productos
    Route::apiResource('precios-producto', LpPrecioProductoController::class);
    Route::get('precios-producto/obtener-precio', [LpPrecioProductoController::class, 'obtenerPrecio']);
});
```

#### 11.1.11 Modificaciones a Archivos Existentes

1. **`app/Console/Kernel.php`**

    - Agregar comando programado: `$schedule->command('financiero:activar-listas-precios')->daily();`

2. **`database/seeders/RolesAndPermissionsSeeder.php`**
    - Agregar permisos para el módulo financiero de listas de precios:
        - `fin_lp_tipos_producto` (ver tipos de producto)
        - `fin_lp_tipoProductoCrear`, `fin_lp_tipoProductoEditar`, `fin_lp_tipoProductoInactivar`
        - `fin_lp_productos` (ver productos)
        - `fin_lp_productoCrear`, `fin_lp_productoEditar`, `fin_lp_productoInactivar`
        - `fin_lp_listas_precios` (ver listas de precios)
        - `fin_lp_listaPrecioCrear`, `fin_lp_listaPrecioEditar`, `fin_lp_listaPrecioInactivar`, `fin_lp_listaPrecioAprobar`
        - `fin_lp_precios_producto` (ver precios)
        - `fin_lp_precioProductoCrear`, `fin_lp_precioProductoEditar`, `fin_lp_precioProductoInactivar`
    - Asignar permisos a roles: superusuario, financiero, coordinador

### 11.2 Pasos de Implementación

1. **Crear el trait HasListaPrecioStatus**

    - Ubicación: `app/Traits/Financiero/HasListaPrecioStatus.php`
    - Implementar todos los métodos y scopes necesarios

2. **Crear las migraciones**

    - Ejecutar en orden: tipos_producto → productos → listas_precios → lista_precio_poblacion → precios_producto
    - Verificar foreign keys y constraints

3. **Crear los modelos**

    - Seguir la estructura de `app/Models/Academico/Asistencia.php`
    - Incluir todos los traits necesarios
    - Implementar relaciones y métodos requeridos

4. **Crear los seeders**

    - Ejecutar `LpTipoProductoSeeder` para crear tipos iniciales
    - Opcional: ejecutar `LpProductoSeeder` para productos de ejemplo

5. **Crear el servicio**

    - Implementar lógica de negocio para cálculos y validaciones

6. **Crear los Requests**

    - Implementar validaciones usando traits de status
    - Incluir mensajes de error personalizados

7. **Crear los Controladores**

    - Implementar CRUD completo
    - Incluir filtros, ordenamiento y relaciones
    - Manejar errores apropiadamente

8. **Crear los Resources**

    - Transformar modelos a formato JSON
    - Incluir relaciones y campos calculados

9. **Configurar rutas**

    - Agregar rutas al archivo de rutas correspondiente
    - Aplicar middleware de autenticación y permisos

10. **Actualizar RolesAndPermissionsSeeder**

    - Agregar todos los permisos del módulo
    - Asignar permisos a roles apropiados

11. **Crear comando programado**

    - Implementar comando para gestionar estado de listas (activar aprobadas e inactivar vencidas)
    - Configurar en Kernel.php para ejecución diaria

12. **Pruebas**
    - Probar creación de listas de precios
    - Probar cálculo de cuotas
    - Probar activación automática de listas aprobadas
    - Probar inactivación automática de listas vencidas
    - Probar validaciones de solapamiento
    - Probar permisos y seguridad

## 12. Próximos Pasos

1. Crear las migraciones de base de datos
2. Implementar los modelos Eloquent
3. Crear los servicios de negocio
4. Desarrollar los controladores y recursos API
5. Implementar las validaciones y reglas de negocio
6. Crear seeders para datos iniciales
7. Desarrollar tests unitarios y de integración
8. Documentar los endpoints de la API

## 13. Preguntas y Consideraciones Adicionales

### 13.1 Preguntas Pendientes

1. ¿Los productos complementarios pueden tener múltiples variantes (ej: certificado físico vs digital)?
2. ¿Las listas de precios pueden tener un alcance más específico (por sede en lugar de por población)?
3. ¿Se requiere soporte para múltiples monedas?
4. ¿Los precios pueden variar según otros factores (ej: modalidad, horario)?

**Nota:** Los descuentos se manejarán en un submódulo separado de descuentos.

### 13.2 Mejoras Futuras

-   Integración con submódulo de descuentos
-   Precios por volumen o grupos
-   Integración con sistemas de pago
-   Dashboard de análisis de precios
-   Exportación de listas de precios a diferentes formatos
-   Comando programado para activar automáticamente listas aprobadas cuando inicia su vigencia
