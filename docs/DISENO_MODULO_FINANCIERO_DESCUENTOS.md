# Diseño del Módulo Financiero - Submódulo de Descuentos

## 1. Introducción

Este documento describe el diseño del submódulo de **Descuentos** dentro del módulo financiero del sistema. Este submódulo permite gestionar descuentos que pueden aplicarse a productos dentro de las listas de precios, con capacidad de variación por ubicación (sede o ciudad), tipo de descuento (porcentual o valor fijo), y condiciones de activación (pagos anticipados o promociones de matrícula).

## 2. Requisitos Funcionales

### 2.1 Tipos de Descuento

El sistema debe manejar dos tipos de descuento según su cálculo:

1. **Descuento Porcentual**

    - Se aplica como un porcentaje sobre el valor base
    - Ejemplo: 10% de descuento sobre el precio total
    - Valor almacenado: porcentaje (0-100)

2. **Descuento de Valor Fijo**
    - Se aplica como un monto fijo a descontar
    - Ejemplo: $50,000 de descuento sobre el precio total
    - Valor almacenado: monto en pesos

### 2.2 Aplicación del Descuento

El descuento puede aplicarse sobre:

1. **Valor Total del Producto**

    - Se aplica sobre el precio total del producto (sea financiable o no)
    - Afecta el cálculo de matrícula y cuotas si el producto es financiable

2. **Valor de Matrícula**

    - Se aplica solo sobre el valor de la matrícula
    - Solo aplica para productos financiables que tengan matrícula
    - No afecta el precio total ni las cuotas

3. **Valor de Cuota**
    - Se aplica solo sobre el valor de cada cuota
    - Solo aplica para productos financiables que tengan cuotas
    - No afecta el precio total ni la matrícula
    - **Importante:** El descuento se aplica por cada cuota u obligación individualmente
    - Cada cuota puede tener el descuento aplicado una sola vez, independientemente de si se pagan una o más cuotas por anticipado
    - El descuento aplica a todas las cuotas siempre que se cumplan las condiciones de vigencia del descuento

### 2.3 Condiciones de Activación

Los descuentos pueden activarse bajo tres condiciones:

1. **Pago Anticipado (Cartera)**

    - Se activa cuando el pago se realiza antes de la fecha programada
    - Requiere especificar los días de anticipación mínimos
    - Ejemplo: 5% de descuento si se paga 15 días antes de la fecha programada
    - **Importante:** Un descuento no se puede aplicar dos veces al mismo concepto. Si una cuota tiene descuento por pronto pago y la persona hace dos pagos a la misma cuota antes de su vencimiento, solo aplicará el descuento una vez.

2. **Promoción de Matrícula**

    - Se activa para matrículas realizadas durante el período de vigencia del descuento
    - Solo requiere que la fecha de matrícula esté dentro del rango de vigencia (`fecha_inicio` y `fecha_fin`)
    - Ejemplo: 10% de descuento en matrículas realizadas entre el 1 y el 31 de enero

3. **Código Promocional**
    - Se activa mediante un código alfanumérico ingresado por el usuario
    - Los códigos se pueden publicar en redes sociales o publicidad impresa
    - Útil para matrículas en línea y promociones específicas
    - El código debe ser único y alfanumérico
    - Ejemplo: código "PROMO2025" para obtener 15% de descuento

### 2.4 Alcance Geográfico

Los descuentos pueden aplicarse a:

-   **Todas las sedes** (si no se especifica ninguna)
-   **Sedes específicas** (una o varias)
-   **Ciudades específicas** (una o varias poblaciones)

**Nota:** Si se especifican sedes, el descuento aplica solo a esas sedes. Si se especifican ciudades, aplica a todas las sedes de esas ciudades. Si no se especifica ninguna sede ni ciudad, el descuento aplica globalmente.

### 2.5 Aplicación a Productos

Los descuentos pueden aplicarse a:

-   **Todos los productos** de la lista de precios (si no se especifica ningún producto)
-   **Productos específicos** dentro de la lista de precios (uno o varios)

### 2.6 Relación con Listas de Precios

-   Un descuento puede aplicarse a **múltiples listas de precios** (relación muchos a muchos)
-   Una lista de precios puede tener **múltiples descuentos** aplicables
-   Los descuentos deben estar activos y vigentes para aplicarse

### 2.7 Vigencia y Estados

-   Cada descuento tiene una **fecha de inicio** y **fecha de fin** de vigencia
-   **Estados del descuento:**
    -   **0 - Inactivo**: Descuento desactivado manualmente o automáticamente por pérdida de vigencia
    -   **1 - En Proceso**: Descuento en edición, no se activará hasta que pase a aprobado
    -   **2 - Aprobado**: Descuento aprobado, cambiará automáticamente a activo cuando inicie su período de vigencia
    -   **3 - Activo**: Descuento en uso, solo los descuentos activos se utilizan para aplicar descuentos. Se activa automáticamente cuando la fecha actual >= `fecha_inicio` y se inactiva automáticamente cuando la fecha actual > `fecha_fin`

### 2.8 Acumulación de Descuentos

-   Al crear un descuento, se debe especificar si **permite acumulación** con otros descuentos
-   Si un descuento permite acumulación, puede aplicarse junto con otros descuentos aplicables
-   Si un descuento no permite acumulación, solo se aplicará si no hay otros descuentos aplicables
-   **Regla importante:** El valor final a pagar nunca puede ser inferior a cero (0), independientemente de la cantidad de descuentos aplicados

## 3. Modelo de Datos

### 3.1 Estructura de Tablas

#### 3.1.1 Tabla: `descuentos`

Tabla principal que almacena los descuentos.

```sql
CREATE TABLE descuentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Información básica
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo del descuento',
    codigo VARCHAR(100) UNIQUE NULL COMMENT 'Código único del descuento (interno)',
    codigo_descuento VARCHAR(50) UNIQUE NULL COMMENT 'Código alfanumérico promocional para activar el descuento (publicado en redes/publicidad)',
    descripcion TEXT NULL COMMENT 'Descripción detallada del descuento',

    -- Tipo y valor del descuento
    tipo_descuento ENUM('porcentual', 'valor_fijo') NOT NULL COMMENT 'Tipo de descuento: porcentual o valor fijo',
    valor_descuento DECIMAL(15, 2) NOT NULL COMMENT 'Valor del descuento (porcentaje 0-100 o monto fijo)',

    -- Aplicación del descuento
    aplicacion ENUM('valor_total', 'matricula', 'cuota') NOT NULL COMMENT 'Aplicación: valor total del producto, matrícula o cuota',

    -- Condición de activación
    tipo_activacion ENUM('pago_anticipado', 'promocion_matricula', 'codigo_promocional') NOT NULL COMMENT 'Tipo de activación: pago anticipado, promoción matrícula o código promocional',

    -- Parámetros según tipo de activación
    dias_anticipacion INT NULL COMMENT 'Días mínimos de anticipación para pago anticipado (solo si tipo_activacion = pago_anticipado)',

    -- Acumulación
    permite_acumulacion BOOLEAN DEFAULT FALSE COMMENT 'Indica si el descuento puede acumularse con otros descuentos aplicables',

    -- Vigencia
    fecha_inicio DATE NOT NULL COMMENT 'Fecha de inicio de vigencia del descuento',
    fecha_fin DATE NOT NULL COMMENT 'Fecha de fin de vigencia del descuento',

    -- Estado
    status TINYINT DEFAULT 1 COMMENT '0: inactivo, 1: en proceso, 2: aprobado, 3: activo',

    -- Metadatos
    observaciones TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    INDEX idx_tipo_descuento (tipo_descuento),
    INDEX idx_aplicacion (aplicacion),
    INDEX idx_tipo_activacion (tipo_activacion),
    INDEX idx_fechas (fecha_inicio, fecha_fin),
    INDEX idx_status (status),
    INDEX idx_codigo (codigo),
    INDEX idx_codigo_descuento (codigo_descuento),

    -- Validaciones
    CHECK (fecha_fin >= fecha_inicio),
    CHECK (
        (tipo_descuento = 'porcentual' AND valor_descuento >= 0 AND valor_descuento <= 100) OR
        (tipo_descuento = 'valor_fijo' AND valor_descuento >= 0)
    ),
    CHECK (
        (tipo_activacion = 'pago_anticipado' AND dias_anticipacion IS NOT NULL AND dias_anticipacion > 0) OR
        (tipo_activacion = 'promocion_matricula') OR
        (tipo_activacion = 'codigo_promocional' AND codigo_descuento IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas de negocio:**

-   `valor_descuento`:
    -   Si `tipo_descuento = 'porcentual'`: valor entre 0 y 100
    -   Si `tipo_descuento = 'valor_fijo'`: valor >= 0
-   `dias_anticipacion`: Obligatorio solo si `tipo_activacion = 'pago_anticipado'`
-   `codigo_descuento`: Obligatorio solo si `tipo_activacion = 'codigo_promocional'`, debe ser único y alfanumérico
-   `status`: Estados: 0 (inactivo), 1 (en proceso), 2 (aprobado), 3 (activo). Los estados 3 (activo) y 0 (inactivo) se gestionan automáticamente mediante jobs según las fechas de vigencia
-   `permite_acumulacion`: Indica si el descuento puede acumularse con otros descuentos aplicables
-   `aplicacion = 'matricula'`: Solo aplica para productos financiables que tengan matrícula
-   `aplicacion = 'cuota'`: Solo aplica para productos financiables que tengan cuotas

#### 3.1.2 Tabla: `descuento_lista_precio`

Relación muchos a muchos entre descuentos y listas de precios.

```sql
CREATE TABLE descuento_lista_precio (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descuento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del descuento',
    lista_precio_id BIGINT UNSIGNED NOT NULL COMMENT 'ID de la lista de precios',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (descuento_id) REFERENCES descuentos(id) ON DELETE CASCADE,
    FOREIGN KEY (lista_precio_id) REFERENCES lp_listas_precios(id) ON DELETE CASCADE,

    UNIQUE KEY uk_descuento_lista (descuento_id, lista_precio_id),
    INDEX idx_descuento (descuento_id),
    INDEX idx_lista_precio (lista_precio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.3 Tabla: `descuento_producto`

Relación muchos a muchos entre descuentos y productos específicos dentro de una lista de precios.
Si un descuento no tiene productos asociados, aplica a todos los productos de las listas de precios relacionadas.

```sql
CREATE TABLE descuento_producto (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descuento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del descuento',
    producto_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del producto',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (descuento_id) REFERENCES descuentos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES lp_productos(id) ON DELETE CASCADE,

    UNIQUE KEY uk_descuento_producto (descuento_id, producto_id),
    INDEX idx_descuento (descuento_id),
    INDEX idx_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas de negocio:**

-   Si un descuento no tiene productos asociados, aplica a todos los productos de las listas de precios relacionadas
-   Si un descuento tiene productos asociados, solo aplica a esos productos específicos

#### 3.1.4 Tabla: `descuento_sede`

Relación muchos a muchos entre descuentos y sedes específicas.
Si un descuento no tiene sedes asociadas, aplica a todas las sedes (o según ciudades si están definidas).

```sql
CREATE TABLE descuento_sede (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descuento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del descuento',
    sede_id BIGINT UNSIGNED NOT NULL COMMENT 'ID de la sede',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (descuento_id) REFERENCES descuentos(id) ON DELETE CASCADE,
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE CASCADE,

    UNIQUE KEY uk_descuento_sede (descuento_id, sede_id),
    INDEX idx_descuento (descuento_id),
    INDEX idx_sede (sede_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas de negocio:**

-   Si un descuento no tiene sedes ni ciudades asociadas, aplica globalmente
-   Si un descuento tiene ciudades asociadas, aplica a todas las sedes de esas ciudades
-   Si un descuento tiene sedes específicas asociadas, solo aplica a esas sedes (ignora ciudades si están definidas)

#### 3.1.5 Tabla: `descuento_poblacion`

Relación muchos a muchos entre descuentos y poblaciones (ciudades).
Si un descuento tiene ciudades asociadas, aplica a todas las sedes de esas ciudades.

```sql
CREATE TABLE descuento_poblacion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descuento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del descuento',
    poblacion_id BIGINT UNSIGNED NOT NULL COMMENT 'ID de la población',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (descuento_id) REFERENCES descuentos(id) ON DELETE CASCADE,
    FOREIGN KEY (poblacion_id) REFERENCES poblacions(id) ON DELETE CASCADE,

    UNIQUE KEY uk_descuento_poblacion (descuento_id, poblacion_id),
    INDEX idx_descuento (descuento_id),
    INDEX idx_poblacion (poblacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas de negocio:**

-   Si un descuento tiene ciudades asociadas, aplica a todas las sedes de esas ciudades
-   Si un descuento tiene sedes específicas asociadas, las sedes tienen prioridad sobre las ciudades

#### 3.1.6 Tabla: `descuento_aplicado`

Tabla de historial que registra cada aplicación de un descuento a un concepto de pago específico.
Esta tabla permite auditoría y evita que un descuento se aplique dos veces al mismo concepto.

```sql
CREATE TABLE descuento_aplicado (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descuento_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del descuento aplicado',

    -- Referencia al concepto de pago (puede ser matrícula, cuota, pago de contado, etc.)
    -- Se usa un sistema polimórfico para flexibilidad
    concepto_tipo VARCHAR(255) NOT NULL COMMENT 'Tipo de concepto: matricula, cuota, pago_contado, etc.',
    concepto_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del concepto de pago',

    -- Información del descuento aplicado
    valor_original DECIMAL(15, 2) NOT NULL COMMENT 'Valor original antes del descuento',
    valor_descuento DECIMAL(15, 2) NOT NULL COMMENT 'Valor del descuento aplicado',
    valor_final DECIMAL(15, 2) NOT NULL COMMENT 'Valor final después del descuento',

    -- Información de contexto
    producto_id BIGINT UNSIGNED NULL COMMENT 'ID del producto relacionado',
    lista_precio_id BIGINT UNSIGNED NULL COMMENT 'ID de la lista de precios relacionada',
    sede_id BIGINT UNSIGNED NULL COMMENT 'ID de la sede donde se aplicó',

    -- Metadatos
    observaciones TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (descuento_id) REFERENCES descuentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (producto_id) REFERENCES lp_productos(id) ON DELETE SET NULL,
    FOREIGN KEY (lista_precio_id) REFERENCES lp_listas_precios(id) ON DELETE SET NULL,
    FOREIGN KEY (sede_id) REFERENCES sedes(id) ON DELETE SET NULL,

    INDEX idx_descuento (descuento_id),
    INDEX idx_concepto (concepto_tipo, concepto_id),
    INDEX idx_producto (producto_id),
    INDEX idx_lista_precio (lista_precio_id),
    INDEX idx_sede (sede_id),
    INDEX idx_created_at (created_at),

    -- Validaciones
    CHECK (valor_original >= 0),
    CHECK (valor_descuento >= 0),
    CHECK (valor_final >= 0),
    CHECK (valor_final = valor_original - valor_descuento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Reglas de negocio:**

-   Un descuento no se puede aplicar dos veces al mismo concepto (validado por `concepto_tipo` + `concepto_id`)
-   Los descuentos se calculan sobre el valor a pagar, no sobre el valor pagado
-   Esta tabla permite auditoría completa de todos los descuentos aplicados
-   El `valor_final` nunca puede ser inferior a cero (validado por CHECK constraint)

## 4. Traits Personalizados

### 4.1 Trait: `HasDescuentoStatus`

Trait específico para manejar los estados de los descuentos.

**Archivo:** `app/Traits/Financiero/HasDescuentoStatus.php`

```php
<?php

namespace App\Traits\Financiero;

/**
 * Trait para manejar los estados de los descuentos.
 *
 * Este trait proporciona métodos y scopes para trabajar con los estados
 * de los descuentos: Inactivo, En Proceso, Aprobado y Activo.
 *
 * @package App\Traits\Financiero
 */
trait HasDescuentoStatus
{
    /**
     * Obtiene las opciones de estado para Descuento.
     *
     * Retorna un array asociativo con los estados disponibles:
     * - 0: Inactivo
     * - 1: En Proceso
     * - 2: Aprobado
     * - 3: Activo
     *
     * @return array<int, string> Array con los estados disponibles
     */
    public static function getStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'En Proceso',
            2 => 'Aprobado',
            3 => 'Activo',
        ];
    }

    /**
     * Obtiene el texto del estado basado en el número de estado.
     *
     * @param int|null $status Número del estado
     * @return string Descripción del estado o 'Desconocido' si no existe
     */
    public static function getStatusText(?int $status): string
    {
        $statusOptions = self::getStatusOptions();

        return $statusOptions[$status] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del estado para la instancia actual del modelo.
     *
     * Este método funciona como accessor de Laravel, permitiendo
     * acceder al texto del estado mediante $modelo->status_text.
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
     * Retorna una cadena con las reglas de validación que pueden ser
     * usadas en los FormRequest para validar el campo status.
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
     * Retorna un array con los mensajes de validación en español
     * para el campo status de los descuentos.
     *
     * @return array<string, string> Array con los mensajes de validación
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
     * Scope para filtrar por estado "Inactivo".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactivo($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para filtrar por estado "En Proceso".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnProceso($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para filtrar por estado "Aprobado".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAprobado($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope para filtrar por estado "Activo".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivo($query)
    {
        return $query->where('status', 3);
    }
}
```

## 5. Modelos Eloquent

### 4.1 Modelo: `Descuento`

```php
<?php

namespace App\Models\Financiero\Descuento;

use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpProducto;
use App\Traits\Financiero\HasDescuentoStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Descuento
 *
 * Representa un descuento en el sistema financiero.
 * Los descuentos pueden aplicarse a productos dentro de listas de precios,
 * con capacidad de variación por ubicación y condiciones de activación.
 *
 * @property int $id Identificador único del descuento
 * @property string $nombre Nombre descriptivo del descuento
 * @property string|null $codigo Código único del descuento
 * @property string|null $descripcion Descripción del descuento
 * @property string $tipo_descuento Tipo de descuento: 'porcentual' o 'valor_fijo'
 * @property float $valor_descuento Valor del descuento (porcentaje 0-100 o monto fijo)
 * @property string $aplicacion Aplicación: 'valor_total', 'matricula' o 'cuota'
 * @property string $tipo_activacion Tipo de activación: 'pago_anticipado', 'promocion_matricula' o 'codigo_promocional'
 * @property int|null $dias_anticipacion Días mínimos de anticipación (solo para pago anticipado)
 * @property \Carbon\Carbon $fecha_inicio Fecha de inicio de vigencia
 * @property \Carbon\Carbon $fecha_fin Fecha de fin de vigencia
 * @property int $status Estado del descuento (0: inactivo, 1: en proceso, 2: aprobado, 3: activo)
 * @property string|null $observaciones Observaciones adicionales
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpListaPrecio> $listasPrecios Listas de precios donde aplica
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpProducto> $productos Productos específicos donde aplica (si está vacío, aplica a todos)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Configuracion\Sede> $sedes Sedes específicas donde aplica (si está vacío, aplica según ciudades o globalmente)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Configuracion\Poblacion> $poblaciones Ciudades donde aplica (si está vacío, aplica globalmente)
 */
class Descuento extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes, HasDescuentoStatus;

    /**
     * Constantes para tipo de descuento
     */
    const TIPO_PORCENTUAL = 'porcentual';
    const TIPO_VALOR_FIJO = 'valor_fijo';

    /**
     * Constantes para aplicación del descuento
     */
    const APLICACION_VALOR_TOTAL = 'valor_total';
    const APLICACION_MATRICULA = 'matricula';
    const APLICACION_CUOTA = 'cuota';

    /**
     * Constantes para tipo de activación
     */
    const ACTIVACION_PAGO_ANTICIPADO = 'pago_anticipado';
    const ACTIVACION_PROMOCION_MATRICULA = 'promocion_matricula';
    const ACTIVACION_CODIGO_PROMOCIONAL = 'codigo_promocional';

    /**
     * Constantes para estados del descuento
     */
    const STATUS_INACTIVO = 0;
    const STATUS_EN_PROCESO = 1;
    const STATUS_APROBADO = 2;
    const STATUS_ACTIVO = 3;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'descuentos';

    /**
     * Los atributos que no son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor_descuento' => 'decimal:2',
        'dias_anticipacion' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'status' => 'integer',
        'permite_acumulacion' => 'boolean',
    ];

    /**
     * Relación con LpListaPrecio (muchos a muchos).
     * Un descuento puede aplicarse a múltiples listas de precios.
     *
     * @return BelongsToMany
     */
    public function listasPrecios(): BelongsToMany
    {
        return $this->belongsToMany(
            LpListaPrecio::class,
            'descuento_lista_precio',
            'descuento_id',
            'lista_precio_id'
        )->withTimestamps();
    }

    /**
     * Relación con LpProducto (muchos a muchos).
     * Un descuento puede aplicarse a productos específicos.
     * Si está vacío, aplica a todos los productos de las listas relacionadas.
     *
     * @return BelongsToMany
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            LpProducto::class,
            'descuento_producto',
            'descuento_id',
            'producto_id'
        )->withTimestamps();
    }

    /**
     * Relación con Sede (muchos a muchos).
     * Un descuento puede aplicarse a sedes específicas.
     * Si está vacío, aplica según ciudades o globalmente.
     *
     * @return BelongsToMany
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(
            Sede::class,
            'descuento_sede',
            'descuento_id',
            'sede_id'
        )->withTimestamps();
    }

    /**
     * Relación con Poblacion (muchos a muchos).
     * Un descuento puede aplicarse a ciudades específicas.
     * Si está vacío, aplica globalmente.
     *
     * @return BelongsToMany
     */
    public function poblaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Poblacion::class,
            'descuento_poblacion',
            'descuento_id',
            'poblacion_id'
        )->withTimestamps();
    }

    /**
     * Verifica si el descuento está vigente en una fecha específica.
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return bool True si el descuento está vigente, false en caso contrario
     */
    public function estaVigente(?Carbon $fecha = null): bool
    {
        $fecha = $fecha ?? Carbon::now();
        return $fecha->between($this->fecha_inicio, $this->fecha_fin)
            && $this->status === self::STATUS_ACTIVO; // Solo activos están vigentes
    }

    /**
     * Verifica si el descuento aplica a un producto específico.
     * Si el descuento no tiene productos asociados, aplica a todos.
     *
     * @param int $productoId ID del producto
     * @return bool True si aplica al producto, false en caso contrario
     */
    public function aplicaAProducto(int $productoId): bool
    {
        // Si no tiene productos asociados, aplica a todos
        if ($this->productos()->count() === 0) {
            return true;
        }

        return $this->productos()->where('lp_productos.id', $productoId)->exists();
    }

    /**
     * Verifica si el descuento aplica a una sede específica.
     *
     * @param int $sedeId ID de la sede
     * @return bool True si aplica a la sede, false en caso contrario
     */
    public function aplicaASede(int $sedeId): bool
    {
        // Si tiene sedes específicas, solo aplica a esas sedes
        if ($this->sedes()->count() > 0) {
            return $this->sedes()->where('sedes.id', $sedeId)->exists();
        }

        // Si tiene ciudades asociadas, verificar si la sede pertenece a alguna
        if ($this->poblaciones()->count() > 0) {
            return $this->poblaciones()
                ->whereHas('sedes', function ($query) use ($sedeId) {
                    $query->where('sedes.id', $sedeId);
                })
                ->exists();
        }

        // Si no tiene sedes ni ciudades, aplica globalmente
        return true;
    }

    /**
     * Verifica si el descuento aplica a una población específica.
     *
     * @param int $poblacionId ID de la población
     * @return bool True si aplica a la población, false en caso contrario
     */
    public function aplicaAPoblacion(int $poblacionId): bool
    {
        // Si tiene sedes específicas, verificar si alguna sede pertenece a la población
        if ($this->sedes()->count() > 0) {
            return $this->sedes()
                ->where('poblacion_id', $poblacionId)
                ->exists();
        }

        // Si tiene ciudades asociadas, verificar si la población está incluida
        if ($this->poblaciones()->count() > 0) {
            return $this->poblaciones()->where('poblacions.id', $poblacionId)->exists();
        }

        // Si no tiene sedes ni ciudades, aplica globalmente
        return true;
    }

    /**
     * Calcula el valor del descuento aplicado a un monto.
     * Los descuentos se calculan sobre el valor a pagar, no sobre el valor pagado.
     *
     * @param float $monto Monto base sobre el cual aplicar el descuento (valor a pagar)
     * @return float Valor del descuento calculado
     */
    public function calcularDescuento(float $monto): float
    {
        if ($monto <= 0) {
            return 0;
        }

        if ($this->tipo_descuento === self::TIPO_PORCENTUAL) {
            return ($monto * $this->valor_descuento) / 100;
        }

        // Tipo valor fijo
        return min($this->valor_descuento, $monto); // No puede exceder el monto
    }

    /**
     * Verifica si el descuento puede activarse mediante código promocional.
     *
     * @param string $codigo Código promocional ingresado
     * @return bool True si el código es válido y el descuento puede activarse
     */
    public function puedeActivarPorCodigo(string $codigo): bool
    {
        if ($this->tipo_activacion !== self::ACTIVACION_CODIGO_PROMOCIONAL) {
            return false;
        }

        if (!$this->estaVigente()) {
            return false;
        }

        return strtoupper(trim($codigo)) === strtoupper(trim($this->codigo_descuento ?? ''));
    }

    /**
     * Verifica si el descuento puede activarse según su tipo de activación.
     *
     * @param Carbon|null $fechaPago Fecha de pago (para pago anticipado)
     * @param Carbon|null $fechaProgramada Fecha programada de pago (para pago anticipado)
     * @param Carbon|null $fechaMatricula Fecha de matrícula (para promoción matrícula)
     * @return bool True si el descuento puede activarse, false en caso contrario
     */
    public function puedeActivar(
        ?Carbon $fechaPago = null,
        ?Carbon $fechaProgramada = null,
        ?Carbon $fechaMatricula = null
    ): bool {
        if (!$this->estaVigente()) {
            return false;
        }

        if ($this->tipo_activacion === self::ACTIVACION_PAGO_ANTICIPADO) {
            if (!$fechaPago || !$fechaProgramada) {
                return false;
            }

            $diasAnticipacion = $fechaProgramada->diffInDays($fechaPago);
            return $diasAnticipacion >= $this->dias_anticipacion;
        }

        if ($this->tipo_activacion === self::ACTIVACION_PROMOCION_MATRICULA) {
            if (!$fechaMatricula) {
                return false;
            }

            // La promoción aplica si la fecha de matrícula está dentro del período de vigencia
            return $fechaMatricula->between($this->fecha_inicio, $this->fecha_fin);
        }

        if ($this->tipo_activacion === self::ACTIVACION_CODIGO_PROMOCIONAL) {
            // La validación del código se hace en el método puedeActivarPorCodigo()
            // Este método solo verifica vigencia
            return true;
        }

        return false;
    }

    /**
     * Scope para filtrar descuentos vigentes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVigentes($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('fecha_inicio', '<=', $fecha)
                    ->where('fecha_fin', '>=', $fecha)
                    ->where('status', self::STATUS_ACTIVO); // Solo activos
    }

    /**
     * Scope para filtrar descuentos aprobados que deben activarse automáticamente.
     * Retorna descuentos que están en estado "Aprobado" y cuya fecha de inicio ya llegó o pasó.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAprobadosParaActivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::STATUS_APROBADO)
                    ->where('fecha_inicio', '<=', $fecha);
    }

    /**
     * Scope para filtrar descuentos activos que deben inactivarse por pérdida de vigencia.
     * Retorna descuentos que están activos pero cuya fecha de fin ya pasó.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivosParaInactivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::STATUS_ACTIVO)
                    ->where('fecha_fin', '<', $fecha);
    }

    /**
     * Activa automáticamente los descuentos aprobados cuando inicia su vigencia.
     * Este método debe ejecutarse mediante un comando programado (job/cron).
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return void
     */
    public static function activarDescuentosAprobados(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::aprobadosParaActivar($fecha)->update([
            'status' => self::STATUS_ACTIVO
        ]);
    }

    /**
     * Inactiva automáticamente los descuentos activos que han perdido su vigencia.
     * Este método debe ejecutarse mediante un comando programado (job/cron).
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return void
     */
    public static function inactivarDescuentosVencidos(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::activosParaInactivar($fecha)->update([
            'status' => self::STATUS_INACTIVO
        ]);
    }

    /**
     * Scope para filtrar descuentos por tipo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tipo Tipo de descuento
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_descuento', $tipo);
    }

    /**
     * Scope para filtrar descuentos por tipo de activación.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tipoActivacion Tipo de activación
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePorTipoActivacion($query, string $tipoActivacion)
    {
        return $query->where('tipo_activacion', $tipoActivacion);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'codigo',
            'tipo_descuento',
            'valor_descuento',
            'aplicacion',
            'tipo_activacion',
            'fecha_inicio',
            'fecha_fin',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array<string>
     */
    protected function getAllowedRelations(): array
    {
        return [
            'listasPrecios',
            'productos',
            'sedes',
            'poblaciones',
            'listasPrecios.poblaciones',
            'productos.tipoProducto'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return ['listasPrecios'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [
            'listasPrecios',
            'productos',
            'sedes',
            'poblaciones'
        ];
    }
}
```

## 6. Servicios y Lógica de Negocio

### 6.1 Servicio: `DescuentoService`

```php
<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use Carbon\Carbon;

class DescuentoService
{
    /**
     * Obtiene los descuentos aplicables para un producto en una lista de precios,
     * considerando la sede, las condiciones de activación y códigos promocionales.
     *
     * @param int $productoId ID del producto
     * @param int $listaPrecioId ID de la lista de precios
     * @param int $sedeId ID de la sede
     * @param Carbon|null $fechaPago Fecha de pago (para pago anticipado)
     * @param Carbon|null $fechaProgramada Fecha programada de pago (para pago anticipado)
     * @param Carbon|null $fechaMatricula Fecha de matrícula (para promoción matrícula)
     * @param string|null $codigoPromocional Código promocional ingresado (opcional)
     * @return \Illuminate\Database\Eloquent\Collection Colección de descuentos aplicables
     */
    public function obtenerDescuentosAplicables(
        int $productoId,
        int $listaPrecioId,
        int $sedeId,
        ?Carbon $fechaPago = null,
        ?Carbon $fechaProgramada = null,
        ?Carbon $fechaMatricula = null,
        ?string $codigoPromocional = null
    ) {
        $descuentos = Descuento::whereHas('listasPrecios', function ($query) use ($listaPrecioId) {
            $query->where('lp_listas_precios.id', $listaPrecioId);
        })
        ->vigentes()
        ->get()
        ->filter(function ($descuento) use ($productoId, $sedeId, $fechaPago, $fechaProgramada, $fechaMatricula, $codigoPromocional) {
            // Verificar si aplica al producto
            if (!$descuento->aplicaAProducto($productoId)) {
                return false;
            }

            // Verificar si aplica a la sede
            if (!$descuento->aplicaASede($sedeId)) {
                return false;
            }

            // Verificar condiciones de activación
            if ($descuento->tipo_activacion === Descuento::ACTIVACION_CODIGO_PROMOCIONAL) {
                if (!$codigoPromocional) {
                    return false;
                }
                return $descuento->puedeActivarPorCodigo($codigoPromocional);
            }

            return $descuento->puedeActivar($fechaPago, $fechaProgramada, $fechaMatricula);
        });

        return $descuentos;
    }

    /**
     * Calcula el precio final de un producto aplicando los descuentos correspondientes.
     * Considera la lógica de acumulación y asegura que los valores nunca sean negativos.
     *
     * @param LpPrecioProducto $precioProducto Precio del producto
     * @param int $sedeId ID de la sede
     * @param Carbon|null $fechaPago Fecha de pago (para pago anticipado)
     * @param Carbon|null $fechaProgramada Fecha programada de pago (para pago anticipado)
     * @param Carbon|null $fechaMatricula Fecha de matrícula (para promoción matrícula)
     * @param string|null $codigoPromocional Código promocional ingresado (opcional)
     * @return array Array con los valores calculados después de aplicar descuentos
     */
    public function calcularPrecioConDescuentos(
        LpPrecioProducto $precioProducto,
        int $sedeId,
        ?Carbon $fechaPago = null,
        ?Carbon $fechaProgramada = null,
        ?Carbon $fechaMatricula = null,
        ?string $codigoPromocional = null
    ): array {
        $descuentos = $this->obtenerDescuentosAplicables(
            $precioProducto->producto_id,
            $precioProducto->lista_precio_id,
            $sedeId,
            $fechaPago,
            $fechaProgramada,
            $fechaMatricula,
            $codigoPromocional
        );

        // Separar descuentos por tipo de aplicación y si permiten acumulación
        $descuentosValorTotal = [];
        $descuentosMatricula = [];
        $descuentosCuota = [];
        $descuentosNoAcumulables = [];

        foreach ($descuentos as $descuento) {
            if (!$descuento->permite_acumulacion) {
                $descuentosNoAcumulables[] = $descuento;
            } else {
                switch ($descuento->aplicacion) {
                    case Descuento::APLICACION_VALOR_TOTAL:
                        $descuentosValorTotal[] = $descuento;
                        break;
                    case Descuento::APLICACION_MATRICULA:
                        $descuentosMatricula[] = $descuento;
                        break;
                    case Descuento::APLICACION_CUOTA:
                        $descuentosCuota[] = $descuento;
                        break;
                }
            }
        }

        $resultado = [
            'precio_contado' => $precioProducto->precio_contado ?? 0,
            'precio_total' => $precioProducto->precio_total ?? 0,
            'matricula' => $precioProducto->matricula ?? 0,
            'valor_cuota' => $precioProducto->valor_cuota ?? 0,
            'descuentos_aplicados' => [],
            'total_descuentos' => 0,
        ];

        // Si hay descuentos no acumulables, solo aplicar el de mayor valor
        if (!empty($descuentosNoAcumulables)) {
            $mejorDescuento = collect($descuentosNoAcumulables)->sortByDesc(function ($d) use ($precioProducto) {
                return $d->calcularDescuento($precioProducto->precio_total ?? 0);
            })->first();

            if ($mejorDescuento->aplicacion === Descuento::APLICACION_VALOR_TOTAL) {
                $descuentosValorTotal = [$mejorDescuento];
            } elseif ($mejorDescuento->aplicacion === Descuento::APLICACION_MATRICULA) {
                $descuentosMatricula = [$mejorDescuento];
            } elseif ($mejorDescuento->aplicacion === Descuento::APLICACION_CUOTA) {
                $descuentosCuota = [$mejorDescuento];
            }
        }

        // Aplicar descuentos al valor total (se acumulan si permiten acumulación)
        $precioTotalBase = $resultado['precio_total'];
        foreach ($descuentosValorTotal as $descuento) {
            $descuentoAplicado = $descuento->calcularDescuento($precioTotalBase);
            $resultado['precio_total'] -= $descuentoAplicado;
            $resultado['total_descuentos'] += $descuentoAplicado;

            $resultado['descuentos_aplicados'][] = [
                'descuento_id' => $descuento->id,
                'nombre' => $descuento->nombre,
                'tipo' => $descuento->tipo_descuento,
                'valor' => $descuento->valor_descuento,
                'descuento_aplicado' => $descuentoAplicado,
                'aplicacion' => $descuento->aplicacion,
            ];

            // Actualizar base para siguiente descuento acumulable
            $precioTotalBase = $resultado['precio_total'];
        }

        // Aplicar descuentos a la matrícula (se acumulan si permiten acumulación)
        $matriculaBase = $resultado['matricula'];
        foreach ($descuentosMatricula as $descuento) {
            if ($precioProducto->producto->esFinanciable() && $matriculaBase > 0) {
                $descuentoAplicado = $descuento->calcularDescuento($matriculaBase);
                $resultado['matricula'] -= $descuentoAplicado;
                $resultado['total_descuentos'] += $descuentoAplicado;

                $resultado['descuentos_aplicados'][] = [
                    'descuento_id' => $descuento->id,
                    'nombre' => $descuento->nombre,
                    'tipo' => $descuento->tipo_descuento,
                    'valor' => $descuento->valor_descuento,
                    'descuento_aplicado' => $descuentoAplicado,
                    'aplicacion' => $descuento->aplicacion,
                ];

                // Actualizar base para siguiente descuento acumulable
                $matriculaBase = $resultado['matricula'];
            }
        }

        // Recalcular cuota si se aplicaron descuentos al valor total
        if ($precioProducto->producto->esFinanciable() && $precioProducto->precio_total && !empty($descuentosValorTotal)) {
            $valorRestante = max(0, $resultado['precio_total'] - $resultado['matricula']);
            if ($precioProducto->numero_cuotas > 0) {
                $resultado['valor_cuota'] = round($valorRestante / $precioProducto->numero_cuotas / 100) * 100;
            }
        }

        // Aplicar descuentos a la cuota (se acumulan si permiten acumulación)
        $cuotaBase = $resultado['valor_cuota'];
        foreach ($descuentosCuota as $descuento) {
            if ($precioProducto->producto->esFinanciable() && $cuotaBase > 0) {
                $descuentoAplicado = $descuento->calcularDescuento($cuotaBase);
                $resultado['valor_cuota'] -= $descuentoAplicado;
                $resultado['total_descuentos'] += $descuentoAplicado;

                $resultado['descuentos_aplicados'][] = [
                    'descuento_id' => $descuento->id,
                    'nombre' => $descuento->nombre,
                    'tipo' => $descuento->tipo_descuento,
                    'valor' => $descuento->valor_descuento,
                    'descuento_aplicado' => $descuentoAplicado,
                    'aplicacion' => $descuento->aplicacion,
                ];

                // Actualizar base para siguiente descuento acumulable
                $cuotaBase = $resultado['valor_cuota'];
            }
        }

        // Asegurar que los valores nunca sean negativos (regla fundamental)
        $resultado['precio_total'] = max(0, $resultado['precio_total']);
        $resultado['matricula'] = max(0, $resultado['matricula']);
        $resultado['valor_cuota'] = max(0, $resultado['valor_cuota']);

        return $resultado;
    }

    /**
     * Valida que no existan solapamientos de vigencia para descuentos con las mismas condiciones.
     *
     * @param Carbon $fechaInicio Fecha de inicio
     * @param Carbon $fechaFin Fecha de fin
     * @param array $listaPrecioIds IDs de listas de precios
     * @param array $productoIds IDs de productos (opcional)
     * @param int|null $excluirDescuentoId ID del descuento a excluir (para actualizaciones)
     * @return bool True si no hay solapamientos, false en caso contrario
     */
    public function validarSolapamientoVigencia(
        Carbon $fechaInicio,
        Carbon $fechaFin,
        array $listaPrecioIds,
        array $productoIds = [],
        ?int $excluirDescuentoId = null
    ): bool {
        $query = Descuento::whereHas('listasPrecios', function ($q) use ($listaPrecioIds) {
            $q->whereIn('lp_listas_precios.id', $listaPrecioIds);
        })
        ->vigentes()
        ->where(function ($q) use ($fechaInicio, $fechaFin) {
            $q->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
              ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
              ->orWhere(function ($q2) use ($fechaInicio, $fechaFin) {
                  $q2->where('fecha_inicio', '<=', $fechaInicio)
                     ->where('fecha_fin', '>=', $fechaFin);
              });
        });

        if ($excluirDescuentoId) {
            $query->where('id', '!=', $excluirDescuentoId);
        }

        // Si se especifican productos, verificar que los descuentos también apliquen a esos productos
        if (!empty($productoIds)) {
            $query->where(function ($q) use ($productoIds) {
                // Descuentos que no tienen productos específicos (aplican a todos)
                $q->whereDoesntHave('productos')
                  // O descuentos que incluyen alguno de los productos especificados
                  ->orWhereHas('productos', function ($q2) use ($productoIds) {
                      $q2->whereIn('lp_productos.id', $productoIds);
                  });
            });
        }

        return $query->count() === 0;
    }
}
```

## 7. Ejemplos de Uso

### 7.1 Crear un Descuento de Pago Anticipado

```php
use App\Models\Financiero\Descuento\Descuento;
use Carbon\Carbon;

// Crear descuento por pago anticipado
$descuento = Descuento::create([
    'nombre' => 'Descuento 5% Pago Anticipado',
    'codigo' => 'DESC-PAGO-ANT-5',
    'descripcion' => 'Descuento del 5% por pago 15 días antes de la fecha programada',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 5.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
    'dias_anticipacion' => 15,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-12-31'),
    'status' => Descuento::STATUS_EN_PROCESO, // Estado inicial: en proceso
]);

// Asociar a listas de precios
$descuento->listasPrecios()->attach([1, 2, 3]);

// Asociar a productos específicos (opcional - si está vacío, aplica a todos)
$descuento->productos()->attach([1, 2]);

// Asociar a sedes específicas (opcional)
$descuento->sedes()->attach([1, 2, 3]);
```

### 7.2 Crear un Descuento de Promoción de Matrícula

```php
// Crear descuento de promoción de matrícula
$descuento = Descuento::create([
    'nombre' => 'Promoción Matrícula Enero 2025',
    'codigo' => 'PROM-MAT-ENE-2025',
    'descripcion' => '10% de descuento en matrículas realizadas durante enero 2025',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 10.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-01-31'),
    'status' => Descuento::STATUS_EN_PROCESO, // Estado inicial: en proceso
]);

// Asociar a listas de precios
$descuento->listasPrecios()->attach([1]);

// Asociar a ciudades específicas
$descuento->poblaciones()->attach([1, 2]); // Bogotá y Medellín
```

### 7.3 Aplicar Descuentos a un Precio

```php
use App\Services\Financiero\DescuentoService;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Carbon\Carbon;

$service = new DescuentoService();

// Obtener precio del producto
$precioProducto = LpPrecioProducto::find(1);

// Calcular precio con descuentos aplicables
$precioConDescuentos = $service->calcularPrecioConDescuentos(
    precioProducto: $precioProducto,
    sedeId: 1,
    fechaPago: Carbon::now(),
    fechaProgramada: Carbon::now()->addDays(20), // Pago 20 días antes
    fechaMatricula: Carbon::parse('2025-01-10') // Matrícula dentro del período de vigencia
);

echo "Precio original: $" . number_format($precioProducto->precio_total, 2);
echo "Descuentos aplicados: $" . number_format($precioConDescuentos['total_descuentos'], 2);
echo "Precio final: $" . number_format($precioConDescuentos['precio_total'], 2);
```

## 8. Validaciones y Reglas de Negocio

### 8.1 Validaciones de Descuento

-   La fecha de fin debe ser mayor o igual a la fecha de inicio
-   `valor_descuento`:
    -   Si `tipo_descuento = 'porcentual'`: valor entre 0 y 100
    -   Si `tipo_descuento = 'valor_fijo'`: valor >= 0
-   `dias_anticipacion`: Obligatorio solo si `tipo_activacion = 'pago_anticipado'` y debe ser > 0
-   `codigo_descuento`: Obligatorio solo si `tipo_activacion = 'codigo_promocional'`, debe ser único y alfanumérico
-   `permite_acumulacion`: Indica si el descuento puede acumularse con otros descuentos aplicables
-   `aplicacion = 'matricula'`: Solo aplica para productos financiables que tengan matrícula
-   `aplicacion = 'cuota'`: Solo aplica para productos financiables que tengan cuotas. **Importante:** El descuento se aplica por cada cuota u obligación individualmente. Cada cuota puede tener el descuento aplicado una sola vez, independientemente de si se pagan una o más cuotas por anticipado.
-   `status`: Estados: 0 (inactivo), 1 (en proceso), 2 (aprobado), 3 (activo). Los estados 3 (activo) y 0 (inactivo) se gestionan automáticamente mediante jobs según las fechas de vigencia
-   El código del descuento debe ser único (si se proporciona)

### 8.2 Reglas de Aplicación

-   Si un descuento no tiene productos asociados, aplica a todos los productos de las listas de precios relacionadas
-   Si un descuento tiene productos asociados, solo aplica a esos productos específicos
-   Si un descuento tiene sedes específicas asociadas, solo aplica a esas sedes (ignora ciudades si están definidas)
-   Si un descuento tiene ciudades asociadas pero no sedes, aplica a todas las sedes de esas ciudades
-   Si un descuento no tiene sedes ni ciudades asociadas, aplica globalmente

### 8.3 Cálculo de Descuentos

-   Los descuentos porcentuales se calculan sobre el monto base (valor a pagar)
-   Los descuentos de valor fijo no pueden exceder el monto base
-   **Regla fundamental:** El resultado final nunca puede ser inferior a cero (0)
-   Los descuentos se calculan sobre el valor a pagar, no sobre el valor pagado
-   Si se aplican múltiples descuentos al valor total y permiten acumulación, se aplican secuencialmente
-   Si se aplican descuentos al valor total, se recalcula la cuota automáticamente
-   Los descuentos aplicados a matrícula solo afectan el valor de la matrícula
-   Los descuentos aplicados a cuotas se aplican por cada cuota u obligación individualmente
-   Cada cuota puede tener el descuento aplicado una sola vez, independientemente de si se pagan una o más cuotas por anticipado
-   El descuento aplica a todas las cuotas siempre que se cumplan las condiciones de vigencia del descuento
-   Los descuentos aplicados a cuotas no afectan el precio total ni la matrícula
-   Un descuento no se puede aplicar dos veces al mismo concepto (validado mediante tabla `descuento_aplicado`)

## 9. Consideraciones de Implementación

### 9.1 Estructura de Carpetas

El módulo de Descuentos seguirá la misma estructura organizacional que el módulo Lp:

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── Financiero/
│   │           └── Descuento/
│   │               └── DescuentoController.php
│   ├── Requests/
│   │   └── Api/
│   │       └── Financiero/
│   │           └── Descuento/
│   │               ├── StoreDescuentoRequest.php
│   │               └── UpdateDescuentoRequest.php
│   └── Resources/
│       └── Api/
│           └── Financiero/
│               └── Descuento/
│                   └── DescuentoResource.php
└── Models/
    └── Financiero/
        └── Descuento/
            └── Descuento.php

database/
├── factories/
│   └── Financiero/
│       └── Descuento/
│           └── DescuentoFactory.php
└── seeders/
    └── DescuentoSeeder.php

app/
└── Console/
    └── Commands/
        └── Financiero/
            └── GestionarEstadosDescuentos.php

app/
└── Traits/
    └── Financiero/
        └── HasDescuentoStatus.php
```

### 9.2 Integración con Módulo de Listas de Precios

-   Los descuentos se relacionan con las listas de precios mediante tabla pivot
-   Al consultar precios, se deben considerar los descuentos aplicables
-   Los descuentos deben validarse contra la vigencia de las listas de precios

### 8.3 Integración con Módulo de Facturación/Cartera

-   Al registrar un pago anticipado, se debe verificar si aplican descuentos
-   Al registrar una matrícula, se debe verificar si aplican promociones
-   Los descuentos aplicados deben registrarse en el historial de pagos

## 10. Preguntas y Consideraciones Adicionales

### 10.1 Preguntas Resueltas

1. **¿Los descuentos pueden acumularse o solo se aplica el mayor?**

    - **Respuesta:** Al crear el descuento debe existir una opción (`permite_acumulacion`) que permita decidir si se acumula o no con otros descuentos aplicables.

2. **¿Hay límite máximo de descuentos que se pueden aplicar simultáneamente?**

    - **Respuesta:** No hay límite, lo único es que el valor a pagar nunca puede ser inferior a cero (0).

3. **¿Los descuentos aplicados a cuotas se aplican a todas las cuotas o solo a la primera?**

    - **Respuesta:** Se aplican a todas las cuotas siempre y cuando se cumpla con las condiciones de vigencia del descuento. Vale aclarar que un descuento no se puede aplicar dos veces al mismo concepto. Por ejemplo, si una cuota tiene descuento por pronto pago y la persona hace dos pagos a la misma cuota antes de su vencimiento, solo aplicará el descuento una vez. Los descuentos se calculan sobre el valor a pagar, no sobre el valor pagado.

4. **¿Se requiere historial de descuentos aplicados para auditoría?**

    - **Respuesta:** Sí, es necesaria. Se implementa mediante la tabla `descuento_aplicado` que registra cada aplicación de un descuento a un concepto específico.

5. **¿Los descuentos pueden tener prioridad o orden de aplicación?**
    - **Respuesta:** Por ahora es irrelevante. No se implementa sistema de prioridades en esta versión.

### 10.2 Mejoras Futuras

-   Dashboard de análisis de descuentos aplicados
-   Reportes de efectividad de descuentos
-   Descuentos automáticos basados en reglas de negocio
-   Integración con sistema de lealtad o puntos
-   Descuentos por volumen o grupos de productos
-   Descuentos por referidos

## 11. Resumen de Tablas

| Tabla                    | Propósito                                          | Relaciones                                   |
| ------------------------ | -------------------------------------------------- | -------------------------------------------- |
| `descuentos`             | Tabla principal de descuentos                      | -                                            |
| `descuento_lista_precio` | Relación muchos a muchos con listas de precios     | descuentos ↔ lp_listas_precios               |
| `descuento_producto`     | Relación muchos a muchos con productos específicos | descuentos ↔ lp_productos                    |
| `descuento_sede`         | Relación muchos a muchos con sedes específicas     | descuentos ↔ sedes                           |
| `descuento_poblacion`    | Relación muchos a muchos con ciudades              | descuentos ↔ poblacions                      |
| `descuento_aplicado`     | Historial de descuentos aplicados (auditoría)      | descuentos → (polimórfico) conceptos de pago |

## 12. Diagrama de Relaciones

```
descuentos
    ├── descuento_lista_precio (N:M) → lp_listas_precios
    ├── descuento_producto (N:M) → lp_productos
    ├── descuento_sede (N:M) → sedes
    ├── descuento_poblacion (N:M) → poblacions
    └── descuento_aplicado (1:N) → conceptos de pago (polimórfico)

lp_listas_precios
    └── descuento_lista_precio (N:M) → descuentos

lp_productos
    └── descuento_producto (N:M) → descuentos

sedes
    ├── descuento_sede (N:M) → descuentos
    └── poblacion_id (N:1) → poblacions

poblacions
    ├── descuento_poblacion (N:M) → descuentos
    └── sedes (1:N) → sedes

descuento_aplicado
    ├── descuento_id (N:1) → descuentos
    ├── concepto_tipo + concepto_id (polimórfico) → conceptos de pago
    ├── producto_id (N:1) → lp_productos
    ├── lista_precio_id (N:1) → lp_listas_precios
    └── sede_id (N:1) → sedes
```
