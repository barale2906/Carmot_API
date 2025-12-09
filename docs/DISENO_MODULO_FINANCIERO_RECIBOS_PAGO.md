# Diseño del Submódulo Recibos de Pago

## Descripción General

El submódulo **Recibos de Pago** forma parte del módulo financiero y permite registrar todos los pagos que ingresan al instituto por los diferentes conceptos que se muestran en las listas de precios. Este módulo gestiona la generación, numeración consecutiva por sede, aplicación de descuentos, y generación de reportes e informes de ingresos.

## Estructura de Carpetas

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── Financiero/
│   │           └── ReciboPago/
│   │               └── ReciboPagoController.php
│   ├── Requests/
│   │   └── Api/
│   │       └── Financiero/
│   │           └── ReciboPago/
│   │               ├── StoreReciboPagoRequest.php
│   │               └── UpdateReciboPagoRequest.php
│   └── Resources/
│       └── Api/
│           └── Financiero/
│               └── ReciboPago/
│                   └── ReciboPagoResource.php
├── Models/
│   └── Financiero/
│       └── ReciboPago/
│           └── ReciboPago.php
└── Traits/
    └── Financiero/
        └── HasReciboPagoStatus.php

database/
├── migrations/
│   ├── YYYY_MM_DD_HHMMSS_add_codigo_to_sedes_table.php
│   ├── YYYY_MM_DD_HHMMSS_create_recibos_pago_table.php
│   ├── YYYY_MM_DD_HHMMSS_create_recibo_pago_concepto_pago_table.php
│   ├── YYYY_MM_DD_HHMMSS_create_recibo_pago_lista_precio_table.php
│   ├── YYYY_MM_DD_HHMMSS_create_recibo_pago_producto_table.php
│   ├── YYYY_MM_DD_HHMMSS_create_recibo_pago_descuento_table.php
│   └── YYYY_MM_DD_HHMMSS_create_recibo_pago_medio_pago_table.php
├── factories/
│   └── Financiero/
│       └── ReciboPago/
│           └── ReciboPagoFactory.php
└── seeders/
    ├── RolesAndPermissionsSeeder.php (actualizar)
    └── ReciboPagoSeeder.php (opcional)
```

## Estructura de Base de Datos

### 1. Tabla Principal: `recibos_pago`

**Campos:**

| Campo               | Tipo          | Descripción                                                      | Restricciones       |
| ------------------- | ------------- | ---------------------------------------------------------------- | ------------------- |
| `id`                | bigint        | Identificador único                                              | PK, Auto-increment  |
| `numero_recibo`     | string(50)    | Número completo del recibo (prefijo + consecutivo)               | Unique, Index       |
| `consecutivo`       | integer       | Consecutivo por sede y origen                                    | Index               |
| `prefijo`           | string(10)    | Prefijo de la sede según origen                                  | Index               |
| `origen`            | integer       | Tipo de origen (0=Inventarios, 1=Académico)                      | Default: 1, Index   |
| `fecha_recibo`      | date          | Fecha del recibo                                                 | Not null, Index     |
| `fecha_transaccion` | datetime      | Momento en que ingresó el dinero                                 | Not null, Index     |
| `valor_total`       | decimal(15,2) | Valor total del recibo                                           | Not null, >= 0      |
| `descuento_total`   | decimal(15,2) | Descuento total aplicado                                         | Default: 0, >= 0    |
| `banco`             | string(100)   | Banco donde ingresó el dinero                                    | Nullable            |
| `status`            | integer       | Estado del recibo (0=En proceso, 1=Creado, 2=Cerrado, 3=Anulado) | Default: 0, Index   |
| `cierre`            | integer       | Número de cierre de caja                                         | Nullable, Index     |
| `sede_id`           | bigint        | ID de la sede que genera el recibo                               | FK, Not null, Index |
| `estudiante_id`     | bigint        | ID del estudiante (User)                                         | FK, Nullable, Index |
| `cajero_id`         | bigint        | ID del cajero (User) que genera el recibo                        | FK, Not null, Index |
| `matricula_id`      | bigint        | ID de la matrícula asociada                                      | FK, Nullable, Index |
| `created_at`        | timestamp     | Fecha de creación                                                |                     |
| `updated_at`        | timestamp     | Fecha de actualización                                           |                     |
| `deleted_at`        | timestamp     | Fecha de eliminación (soft delete)                               | Nullable            |

**Índices:**

-   `idx_numero_recibo` - Búsqueda rápida por número de recibo
-   `idx_sede_origen_consecutivo` - Búsqueda por sede, origen y consecutivo
-   `idx_fecha_recibo` - Filtros por fecha
-   `idx_status` - Filtros por estado
-   `idx_cierre` - Filtros por cierre de caja
-   `idx_estudiante` - Filtros por estudiante
-   `idx_cajero` - Filtros por cajero

**Constraints:**

-   `valor_total >= 0`
-   `descuento_total >= 0`
-   `valor_total >= descuento_total`

### 2. Tabla Pivot: `recibo_pago_concepto_pago`

**Campos:**

| Campo              | Tipo          | Descripción                                  | Restricciones       |
| ------------------ | ------------- | -------------------------------------------- | ------------------- |
| `id`               | bigint        | Identificador único                          | PK, Auto-increment  |
| `recibo_pago_id`   | bigint        | ID del recibo de pago                        | FK, Not null, Index |
| `concepto_pago_id` | bigint        | ID del concepto de pago                      | FK, Not null, Index |
| `valor`            | decimal(15,2) | Valor pagado (fuera descuento)               | Not null, >= 0      |
| `tipo`             | integer       | Tipo según concepto de pago                  | Not null            |
| `producto`         | string(255)   | Nombre del producto                          | Nullable            |
| `cantidad`         | integer       | Cantidad del producto                        | Default: 1, >= 1    |
| `unitario`         | decimal(15,2) | Precio unitario                              | Not null, >= 0      |
| `subtotal`         | decimal(15,2) | Subtotal (cantidad \* unitario)              | Not null, >= 0      |
| `id_relacional`    | bigint        | ID de relación (cartera pagada o inventario) | Nullable, Index     |
| `observaciones`    | text          | Observaciones adicionales                    | Nullable            |
| `created_at`       | timestamp     | Fecha de creación                            |                     |
| `updated_at`       | timestamp     | Fecha de actualización                       |                     |

**Índices:**

-   `idx_recibo_pago` - Búsqueda por recibo
-   `idx_concepto_pago` - Búsqueda por concepto
-   `idx_id_relacional` - Búsqueda por relación

**Constraints:**

-   `subtotal = cantidad * unitario`
-   `valor >= 0`
-   `unitario >= 0`
-   `cantidad >= 1`

### 3. Tabla Pivot: `recibo_pago_lista_precio`

**Campos:**

| Campo             | Tipo      | Descripción               | Restricciones       |
| ----------------- | --------- | ------------------------- | ------------------- |
| `id`              | bigint    | Identificador único       | PK, Auto-increment  |
| `recibo_pago_id`  | bigint    | ID del recibo de pago     | FK, Not null, Index |
| `lista_precio_id` | bigint    | ID de la lista de precios | FK, Not null, Index |
| `created_at`      | timestamp | Fecha de creación         |                     |
| `updated_at`      | timestamp | Fecha de actualización    |                     |

**Índices:**

-   `idx_recibo_pago` - Búsqueda por recibo
-   `idx_lista_precio` - Búsqueda por lista de precios

### 4. Tabla Pivot: `recibo_pago_producto`

**Campos:**

| Campo             | Tipo          | Descripción                            | Restricciones       |
| ----------------- | ------------- | -------------------------------------- | ------------------- |
| `id`              | bigint        | Identificador único                    | PK, Auto-increment  |
| `recibo_pago_id`  | bigint        | ID del recibo de pago                  | FK, Not null, Index |
| `producto_id`     | bigint        | ID del producto (LpProducto)           | FK, Not null, Index |
| `cantidad`        | integer       | Cantidad del producto                  | Default: 1, >= 1    |
| `precio_unitario` | decimal(15,2) | Precio unitario aplicado               | Not null, >= 0      |
| `subtotal`        | decimal(15,2) | Subtotal (cantidad \* precio_unitario) | Not null, >= 0      |
| `created_at`      | timestamp     | Fecha de creación                      |                     |
| `updated_at`      | timestamp     | Fecha de actualización                 |                     |

**Índices:**

-   `idx_recibo_pago` - Búsqueda por recibo
-   `idx_producto` - Búsqueda por producto

**Constraints:**

-   `subtotal = cantidad * precio_unitario`
-   `cantidad >= 1`
-   `precio_unitario >= 0`

### 5. Tabla Pivot: `recibo_pago_descuento`

**Campos:**

| Campo             | Tipo          | Descripción                        | Restricciones       |
| ----------------- | ------------- | ---------------------------------- | ------------------- |
| `id`              | bigint        | Identificador único                | PK, Auto-increment  |
| `recibo_pago_id`  | bigint        | ID del recibo de pago              | FK, Not null, Index |
| `descuento_id`    | bigint        | ID del descuento aplicado          | FK, Not null, Index |
| `valor_descuento` | decimal(15,2) | Valor del descuento aplicado       | Not null, >= 0      |
| `valor_original`  | decimal(15,2) | Valor original antes del descuento | Not null, >= 0      |
| `valor_final`     | decimal(15,2) | Valor final después del descuento  | Not null, >= 0      |
| `created_at`      | timestamp     | Fecha de creación                  |                     |
| `updated_at`      | timestamp     | Fecha de actualización             |                     |

**Índices:**

-   `idx_recibo_pago` - Búsqueda por recibo
-   `idx_descuento` - Búsqueda por descuento

**Constraints:**

-   `valor_final = valor_original - valor_descuento`
-   `valor_descuento >= 0`
-   `valor_original >= 0`
-   `valor_final >= 0`

### 6. Tabla Pivot: `recibo_pago_medio_pago`

**Campos:**

| Campo            | Tipo          | Descripción                                                    | Restricciones       |
| ---------------- | ------------- | -------------------------------------------------------------- | ------------------- |
| `id`             | bigint        | Identificador único                                            | PK, Auto-increment  |
| `recibo_pago_id` | bigint        | ID del recibo de pago                                          | FK, Not null, Index |
| `medio_pago`     | string(50)    | Medio de pago (efectivo, tarjeta, transferencia, cheque, etc.) | Not null, Index     |
| `valor`          | decimal(15,2) | Valor pagado con este medio                                    | Not null, >= 0      |
| `referencia`     | string(100)   | Referencia del pago (número de cheque, transferencia, etc.)    | Nullable            |
| `banco`          | string(100)   | Banco relacionado (si aplica)                                  | Nullable            |
| `created_at`     | timestamp     | Fecha de creación                                              |                     |
| `updated_at`     | timestamp     | Fecha de actualización                                         |                     |

**Índices:**

-   `idx_recibo_pago` - Búsqueda por recibo
-   `idx_medio_pago` - Búsqueda por medio de pago

**Constraints:**

-   `valor >= 0`
-   La suma de valores de medios de pago debe ser igual al valor_total del recibo

### 7. Actualización de Tabla: `sedes`

**Nuevos Campos:**

| Campo               | Tipo       | Descripción                                         | Restricciones           |
| ------------------- | ---------- | --------------------------------------------------- | ----------------------- |
| `codigo_academico`  | string(10) | Código de identificación para recibos académicos    | Nullable, Unique, Index |
| `codigo_inventario` | string(10) | Código de identificación para recibos de inventario | Nullable, Unique, Index |

**Índices:**

-   `idx_codigo_academico` - Búsqueda por código académico
-   `idx_codigo_inventario` - Búsqueda por código inventario

## Modelo Eloquent: ReciboPago

### Ubicación

`app/Models/Financiero/ReciboPago/ReciboPago.php`

### Traits a Utilizar

-   `HasFactory` - Para factories
-   `SoftDeletes` - Para eliminación suave
-   `HasFilterScopes` - Para filtros genéricos
-   `HasGenericScopes` - Para scopes genéricos
-   `HasSortingScopes` - Para ordenamiento
-   `HasRelationScopes` - Para relaciones
-   `HasReciboPagoStatus` - Trait personalizado para estados

### Constantes de Estado

```php
const STATUS_EN_PROCESO = 0;
const STATUS_CREADO = 1;
const STATUS_CERRADO = 2;
const STATUS_ANULADO = 3;
```

### Constantes de Origen

```php
const ORIGEN_INVENTARIOS = 0;
const ORIGEN_ACADEMICO = 1;
```

### Relaciones del Modelo ReciboPago

1. **sede()** - `BelongsTo` con `Sede`
2. **estudiante()** - `BelongsTo` con `User` (estudiante_id)
3. **cajero()** - `BelongsTo` con `User` (cajero_id)
4. **matricula()** - `BelongsTo` con `Matricula` (nullable)
5. **conceptosPago()** - `BelongsToMany` con `ConceptoPago` (tabla pivot: `recibo_pago_concepto_pago`)
6. **listasPrecio()** - `BelongsToMany` con `LpListaPrecio` (tabla pivot: `recibo_pago_lista_precio`)
7. **productos()** - `BelongsToMany` con `LpProducto` (tabla pivot: `recibo_pago_producto`)
8. **descuentos()** - `BelongsToMany` con `Descuento` (tabla pivot: `recibo_pago_descuento`)
9. **mediosPago()** - `HasMany` con modelo `ReciboPagoMedioPago` (o tabla pivot directa)

### Relaciones Inversas en Modelos Relacionados

Las siguientes relaciones inversas deben agregarse en los modelos correspondientes:

1. **En modelo `Sede`:**

    - `recibosPago()` - `HasMany` con `ReciboPago`

2. **En modelo `User`:**

    - `recibosPagoComoEstudiante()` - `HasMany` con `ReciboPago` (foreign key: `estudiante_id`)
    - `recibosPagoComoCajero()` - `HasMany` con `ReciboPago` (foreign key: `cajero_id`)

3. **En modelo `Matricula`:**

    - `recibosPago()` - `HasMany` con `ReciboPago`

4. **En modelo `ConceptoPago`:**

    - `recibosPago()` - `BelongsToMany` con `ReciboPago` (tabla pivot: `recibo_pago_concepto_pago`)

5. **En modelo `LpListaPrecio`:**

    - `recibosPago()` - `BelongsToMany` con `ReciboPago` (tabla pivot: `recibo_pago_lista_precio`)

6. **En modelo `LpProducto`:**

    - `recibosPago()` - `BelongsToMany` con `ReciboPago` (tabla pivot: `recibo_pago_producto`)

7. **En modelo `Descuento`:**
    - `recibosPago()` - `BelongsToMany` con `ReciboPago` (tabla pivot: `recibo_pago_descuento`)

### Scopes Personalizados

1. **scopeBySede($query, $sedeId)** - Filtrar por sede
2. **scopeByEstudiante($query, $estudianteId)** - Filtrar por estudiante
3. **scopeByCajero($query, $cajeroId)** - Filtrar por cajero
4. **scopeByOrigen($query, $origen)** - Filtrar por origen (inventario/académico)
5. **scopeByStatus($query, $status)** - Filtrar por estado
6. **scopeByFechaRange($query, $fechaInicio, $fechaFin)** - Filtrar por rango de fechas
7. **scopeByCierre($query, $cierre)** - Filtrar por número de cierre
8. **scopeByMatricula($query, $matriculaId)** - Filtrar por matrícula
9. **scopeEnProceso($query)** - Solo recibos en proceso
10. **scopeCreados($query)** - Solo recibos creados
11. **scopeCerrados($query)** - Solo recibos cerrados
12. **scopeAnulados($query)** - Solo recibos anulados
13. **scopeVigentes($query)** - Recibos no anulados (status != 3)
14. **scopeByProducto($query, $productoId)** - Filtrar por producto vendido
15. **scopeByPoblacion($query, $poblacionId)** - Filtrar por población (a través de sede)

### Métodos Personalizados

1. **generarNumeroRecibo()** - Genera el número de recibo (prefijo + consecutivo)
2. **obtenerConsecutivo()** - Obtiene el siguiente consecutivo para la sede y origen
3. **calcularTotales()** - Calcula valor_total y descuento_total
4. **aplicarDescuentos()** - Aplica descuentos a los productos
5. **validarMediosPago()** - Valida que la suma de medios de pago sea igual al total
6. **anular()** - Anula el recibo (cambia status a ANULADO)
7. **cerrar()** - Cierra el recibo (cambia status a CERRADO)
8. **estaAnulado()** - Verifica si está anulado
9. **estaCerrado()** - Verifica si está cerrado
10. **estaEnProceso()** - Verifica si está en proceso

## Trait: HasReciboPagoStatus

### Ubicación

`app/Traits/Financiero/HasReciboPagoStatus.php`

### Métodos

1. **getStatusOptions()** - Retorna array de estados disponibles
2. **getStatusText($status)** - Retorna texto del estado
3. **getStatusTextAttribute()** - Accessor para status_text
4. **getStatusValidationRule()** - Regla de validación para status
5. **getStatusValidationMessages()** - Mensajes de validación
6. **getOrigenOptions()** - Retorna array de orígenes disponibles
7. **getOrigenText($origen)** - Retorna texto del origen
8. **getOrigenTextAttribute()** - Accessor para origen_text

## Permisos a Crear

En `database/seeders/RolesAndPermissionsSeeder.php`:

```php
// Ver recibos de pago
Permission::create([
    'name' => 'fin_recibos_pago',
    'descripcion' => 'ver recibos de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador, $auxiliar]);

// Crear recibo de pago
Permission::create([
    'name' => 'fin_reciboPagoCrear',
    'descripcion' => 'crear recibo de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador, $auxiliar]);

// Editar recibo de pago
Permission::create([
    'name' => 'fin_reciboPagoEditar',
    'descripcion' => 'editar recibo de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador]);

// Anular recibo de pago
Permission::create([
    'name' => 'fin_reciboPagoAnular',
    'descripcion' => 'anular recibo de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador]);

// Cerrar recibo de pago
Permission::create([
    'name' => 'fin_reciboPagoCerrar',
    'descripcion' => 'cerrar recibo de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador]);

// Ver reportes de recibos de pago
Permission::create([
    'name' => 'fin_reciboPagoReportes',
    'descripcion' => 'ver reportes de recibos de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador]);

// Generar PDF de recibo de pago
Permission::create([
    'name' => 'fin_reciboPagoPDF',
    'descripcion' => 'generar PDF de recibo de pago',
])->syncRoles([$Superusuario, $financiero, $coordinador, $auxiliar, $alumno]);
```

## Funcionalidades Adicionales

### 1. Generación de Número de Recibo

El número de recibo se genera automáticamente con el formato:
`{PREFIJO_SEDE}-{CONSECUTIVO}`

Ejemplo: `ACAD-0001`, `INV-0001`

El consecutivo se incrementa automáticamente por sede y origen.

### 2. Generación de PDF

-   Se genera un PDF del recibo al momento de crearlo
-   El PDF incluye:
    -   Información del instituto
    -   Número de recibo
    -   Fecha del recibo
    -   Datos del estudiante
    -   Detalle de productos/conceptos
    -   Descuentos aplicados
    -   Medios de pago
    -   Totales

### 3. Envío por Correo

-   Al generar el recibo, se envía automáticamente por correo al estudiante
-   El PDF se adjunta al correo
-   Se puede reenviar el recibo manualmente

### 4. Reportes e Informes

Los reportes permiten filtrar por:

-   Período (fecha inicio - fecha fin)
-   Sede
-   Producto vendido
-   Ciudad (a través de población)
-   Cajero
-   Descuentos aplicados
-   Estado del recibo
-   Origen (académico/inventario)

## Reglas de Negocio

1. **Numeración Consecutiva:**

    - Cada sede tiene su propio consecutivo por origen
    - El consecutivo se incrementa automáticamente
    - No se pueden reutilizar números de recibo anulados

2. **Estados del Recibo:**

    - **En Proceso (0):** Recibo en creación, puede editarse
    - **Creado (1):** Recibo finalizado, no puede editarse
    - **Cerrado (2):** Recibo cerrado en cierre de caja
    - **Anulado (3):** Recibo anulado, no puede modificarse

3. **Validaciones:**

    - El valor_total debe ser mayor o igual a descuento_total
    - La suma de medios de pago debe ser igual al valor_total
    - Un recibo en proceso puede editarse
    - Un recibo creado solo puede anularse o cerrarse
    - Un recibo cerrado no puede modificarse
    - Un recibo anulado no puede modificarse

4. **Descuentos:**

    - Los descuentos se aplican a productos/conceptos
    - El descuento_total es la suma de todos los descuentos aplicados
    - Los descuentos deben estar activos y vigentes

5. **Medios de Pago:**
    - Un recibo puede tener múltiples medios de pago
    - La suma de todos los medios debe ser igual al valor_total
    - Medios disponibles: efectivo, tarjeta, transferencia, cheque, otros

## Lista de Verificación - Paso a Paso

### Fase 1: Preparación de Base de Datos

-   [ ] **1.1** Crear migración para agregar campos `codigo_academico` y `codigo_inventario` a la tabla `sedes`
-   [ ] **1.2** Crear migración para la tabla principal `recibos_pago`
-   [ ] **1.3** Crear migración para la tabla pivot `recibo_pago_concepto_pago`
-   [ ] **1.4** Crear migración para la tabla pivot `recibo_pago_lista_precio`
-   [ ] **1.5** Crear migración para la tabla pivot `recibo_pago_producto`
-   [ ] **1.6** Crear migración para la tabla pivot `recibo_pago_descuento`
-   [ ] **1.7** Crear migración para la tabla pivot `recibo_pago_medio_pago`
-   [ ] **1.8** Ejecutar migraciones y verificar estructura

### Fase 2: Modelos y Traits

-   [ ] **2.1** Crear trait `HasReciboPagoStatus` en `app/Traits/Financiero/HasReciboPagoStatus.php`
-   [ ] **2.2** Crear modelo `ReciboPago` en `app/Models/Financiero/ReciboPago/ReciboPago.php`
-   [ ] **2.3** Implementar todas las relaciones del modelo ReciboPago
-   [ ] **2.4** Implementar todos los scopes personalizados
-   [ ] **2.5** Implementar todos los métodos personalizados
-   [ ] **2.6** Actualizar modelo `Sede` - Agregar relación `recibosPago()` (HasMany)
-   [ ] **2.7** Actualizar modelo `Matricula` - Agregar relación `recibosPago()` (HasMany)
-   [ ] **2.8** Actualizar modelo `User` - Agregar relación `recibosPagoComoEstudiante()` (HasMany con foreign key estudiante_id)
-   [ ] **2.9** Actualizar modelo `User` - Agregar relación `recibosPagoComoCajero()` (HasMany con foreign key cajero_id)
-   [ ] **2.10** Actualizar modelo `ConceptoPago` - Agregar relación `recibosPago()` (BelongsToMany)
-   [ ] **2.11** Actualizar modelo `LpListaPrecio` - Agregar relación `recibosPago()` (BelongsToMany)
-   [ ] **2.12** Actualizar modelo `LpProducto` - Agregar relación `recibosPago()` (BelongsToMany)
-   [ ] **2.13** Actualizar modelo `Descuento` - Agregar relación `recibosPago()` (BelongsToMany)

### Fase 3: Permisos

-   [ ] **3.1** Agregar permisos en `RolesAndPermissionsSeeder.php`
-   [ ] **3.2** Ejecutar seeder para crear permisos

### Fase 4: Factory y Seeder

-   [ ] **4.1** Crear factory `ReciboPagoFactory` en `database/factories/Financiero/ReciboPago/ReciboPagoFactory.php`
-   [ ] **4.2** Implementar estados del factory (enProceso, creado, cerrado, anulado)
-   [ ] **4.3** Crear seeder `ReciboPagoSeeder` en `database/seeders/ReciboPagoSeeder.php` (opcional)
-   [ ] **4.4** Implementar generación de datos de prueba con relaciones en el seeder

### Fase 5: Requests (Validación)

-   [ ] **5.1** Crear `StoreReciboPagoRequest` en `app/Http/Requests/Api/Financiero/ReciboPago/StoreReciboPagoRequest.php`
-   [ ] **5.2** Crear `UpdateReciboPagoRequest` en `app/Http/Requests/Api/Financiero/ReciboPago/UpdateReciboPagoRequest.php`
-   [ ] **5.3** Implementar reglas de validación completas
-   [ ] **5.4** Implementar mensajes de validación en español

### Fase 6: Resources (Transformación)

-   [ ] **6.1** Crear `ReciboPagoResource` en `app/Http/Resources/Api/Financiero/ReciboPago/ReciboPagoResource.php`
-   [ ] **6.2** Implementar transformación de datos
-   [ ] **6.3** Incluir relaciones opcionales

### Fase 7: Controller

-   [ ] **7.1** Crear `ReciboPagoController` en `app/Http/Controllers/Api/Financiero/ReciboPago/ReciboPagoController.php`
-   [ ] **7.2** Implementar método `index()` con filtros y paginación
-   [ ] **7.3** Implementar método `store()` con generación de número y cálculo de totales
-   [ ] **7.4** Implementar método `show()` con relaciones
-   [ ] **7.5** Implementar método `update()` con validaciones de estado
-   [ ] **7.6** Implementar método `destroy()` (soft delete)
-   [ ] **7.7** Implementar método `anular()` para anular recibos
-   [ ] **7.8** Implementar método `cerrar()` para cerrar recibos
-   [ ] **7.9** Implementar método `generarPDF()` para generar PDF
-   [ ] **7.10** Implementar método `enviarEmail()` para enviar recibo por correo
-   [ ] **7.11** Implementar método `reportes()` para generar reportes

### Fase 8: Rutas

-   [ ] **8.1** Agregar rutas en `routes/api.php`
-   [ ] **8.2** Proteger rutas con middleware de autenticación
-   [ ] **8.3** Aplicar permisos a las rutas

### Fase 9: Funcionalidades Adicionales

-   [ ] **9.1** Implementar generación de PDF (usar librería como DomPDF o Snappy)
-   [ ] **9.2** Implementar envío de correo con PDF adjunto
-   [ ] **9.3** Crear servicio para generación de números consecutivos
-   [ ] **9.4** Implementar lógica de cálculo de totales y descuentos
-   [ ] **9.5** Crear reportes de ingresos con filtros múltiples

### Fase 10: Testing y Documentación

-   [ ] **10.1** Probar creación de recibos
-   [ ] **10.2** Probar edición de recibos (solo en proceso)
-   [ ] **10.3** Probar anulación de recibos
-   [ ] **10.4** Probar cierre de recibos
-   [ ] **10.5** Probar generación de PDF
-   [ ] **10.6** Probar envío de correo
-   [ ] **10.7** Probar reportes con diferentes filtros
-   [ ] **10.8** Verificar numeración consecutiva por sede y origen
-   [ ] **10.9** Documentar API endpoints
-   [ ] **10.10** Crear documentación de uso

## Actualización de Modelos Relacionados

### Modelo Sede

**Archivo:** `app/Models/Configuracion/Sede.php`

**Agregar relación:**

```php
/**
 * Relación con RecibosPago (uno a muchos).
 * Una sede puede tener múltiples recibos de pago.
 *
 * @return HasMany
 */
public function recibosPago(): HasMany
{
    return $this->hasMany(\App\Models\Financiero\ReciboPago\ReciboPago::class);
}
```

**Actualizar `getAllowedRelations()`:**

```php
protected function getAllowedRelations(): array
{
    return [
        'poblacion',
        'areas',
        'horarios',
        'grupos',
        'ciclos',
        'programaciones',
        'descuentos',
        'recibosPago' // Agregar esta línea
    ];
}
```

### Modelo User

**Archivo:** `app/Models/User.php`

**Agregar relaciones:**

```php
/**
 * Relación con RecibosPago como Estudiante (uno a muchos).
 * Un estudiante puede tener múltiples recibos de pago.
 *
 * @return HasMany
 */
public function recibosPagoComoEstudiante(): HasMany
{
    return $this->hasMany(\App\Models\Financiero\ReciboPago\ReciboPago::class, 'estudiante_id');
}

/**
 * Relación con RecibosPago como Cajero (uno a muchos).
 * Un cajero puede generar múltiples recibos de pago.
 *
 * @return HasMany
 */
public function recibosPagoComoCajero(): HasMany
{
    return $this->hasMany(\App\Models\Financiero\ReciboPago\ReciboPago::class, 'cajero_id');
}
```

**Actualizar `getAllowedRelations()` si existe:**

```php
protected function getAllowedRelations(): array
{
    return [
        // ... relaciones existentes
        'recibosPagoComoEstudiante',
        'recibosPagoComoCajero'
    ];
}
```

### Modelo Matricula

**Archivo:** `app/Models/Academico/Matricula.php`

**Agregar relación:**

```php
/**
 * Relación con RecibosPago (uno a muchos).
 * Una matrícula puede tener múltiples recibos de pago asociados.
 *
 * @return HasMany
 */
public function recibosPago(): HasMany
{
    return $this->hasMany(\App\Models\Financiero\ReciboPago\ReciboPago::class);
}
```

**Actualizar `getAllowedRelations()`:**

```php
protected function getAllowedRelations(): array
{
    return [
        'curso',
        'ciclo',
        'estudiante',
        'matriculadoPor',
        'comercial',
        'recibosPago' // Agregar esta línea
    ];
}
```

### Modelo ConceptoPago

**Archivo:** `app/Models/Financiero/ConceptoPago/ConceptoPago.php`

**Agregar relación:**

```php
/**
 * Relación con RecibosPago (muchos a muchos).
 * Un concepto de pago puede estar en múltiples recibos de pago.
 * La relación se establece a través de la tabla pivot recibo_pago_concepto_pago.
 *
 * @return BelongsToMany
 */
public function recibosPago(): BelongsToMany
{
    return $this->belongsToMany(
        \App\Models\Financiero\ReciboPago\ReciboPago::class,
        'recibo_pago_concepto_pago',
        'concepto_pago_id',
        'recibo_pago_id'
    )->withPivot(['valor', 'tipo', 'producto', 'cantidad', 'unitario', 'subtotal', 'id_relacional', 'observaciones'])
     ->withTimestamps();
}
```

**Actualizar `getAllowedRelations()`:**

```php
protected function getAllowedRelations(): array
{
    return [
        'recibosPago' // Agregar esta línea
    ];
}
```

### Modelo LpListaPrecio

**Archivo:** `app/Models/Financiero/Lp/LpListaPrecio.php`

**Agregar relación:**

```php
/**
 * Relación con RecibosPago (muchos a muchos).
 * Una lista de precios puede estar en múltiples recibos de pago.
 * La relación se establece a través de la tabla pivot recibo_pago_lista_precio.
 *
 * @return BelongsToMany
 */
public function recibosPago(): BelongsToMany
{
    return $this->belongsToMany(
        \App\Models\Financiero\ReciboPago\ReciboPago::class,
        'recibo_pago_lista_precio',
        'lista_precio_id',
        'recibo_pago_id'
    )->withTimestamps();
}
```

**Actualizar `getAllowedRelations()`:**

```php
protected function getAllowedRelations(): array
{
    return [
        'poblaciones',
        'preciosProductos',
        'productos',
        'descuentos',
        'preciosProductos.producto',
        'preciosProductos.producto.tipoProducto',
        'recibosPago' // Agregar esta línea
    ];
}
```

### Modelo LpProducto

**Archivo:** `app/Models/Financiero/Lp/LpProducto.php`

**Agregar relación:**

```php
/**
 * Relación con RecibosPago (muchos a muchos).
 * Un producto puede estar en múltiples recibos de pago.
 * La relación se establece a través de la tabla pivot recibo_pago_producto.
 *
 * @return BelongsToMany
 */
public function recibosPago(): BelongsToMany
{
    return $this->belongsToMany(
        \App\Models\Financiero\ReciboPago\ReciboPago::class,
        'recibo_pago_producto',
        'producto_id',
        'recibo_pago_id'
    )->withPivot(['cantidad', 'precio_unitario', 'subtotal'])
     ->withTimestamps();
}
```

**Actualizar `getAllowedRelations()`:**

```php
protected function getAllowedRelations(): array
{
    return [
        'tipoProducto',
        'listasPrecios',
        'descuentos',
        'recibosPago' // Agregar esta línea
    ];
}
```

### Modelo Descuento

**Archivo:** `app/Models/Financiero/Descuento/Descuento.php`

**Agregar relación:**

```php
/**
 * Relación con RecibosPago (muchos a muchos).
 * Un descuento puede estar aplicado en múltiples recibos de pago.
 * La relación se establece a través de la tabla pivot recibo_pago_descuento.
 *
 * @return BelongsToMany
 */
public function recibosPago(): BelongsToMany
{
    return $this->belongsToMany(
        \App\Models\Financiero\ReciboPago\ReciboPago::class,
        'recibo_pago_descuento',
        'descuento_id',
        'recibo_pago_id'
    )->withPivot(['valor_descuento', 'valor_original', 'valor_final'])
     ->withTimestamps();
}
```

**Actualizar `getAllowedRelations()`:**

```php
protected function getAllowedRelations(): array
{
    return [
        'listasPrecio',
        'productos',
        'sedes',
        'poblaciones',
        'recibosPago' // Agregar esta línea
    ];
}
```

## Notas de Implementación

1. **Numeración Consecutiva:**

    - Se debe crear un servicio o método estático que maneje la generación de consecutivos
    - Considerar usar transacciones de base de datos para evitar duplicados
    - El consecutivo debe ser único por combinación de sede + origen

2. **Cálculo de Totales:**

    - Los totales deben calcularse automáticamente al guardar
    - Considerar usar eventos del modelo (creating, updating) para cálculos automáticos

3. **Validación de Medios de Pago:**

    - La validación debe asegurar que la suma de medios de pago = valor_total
    - Esto debe validarse tanto en creación como en actualización

4. **PDF y Correo:**

    - Considerar usar jobs para envío asíncrono de correos
    - El PDF puede generarse bajo demanda o almacenarse en storage

5. **Reportes:**

    - Los reportes pueden generarse como PDF o Excel
    - Considerar usar queries optimizadas para grandes volúmenes de datos

6. **Relaciones Inversas:**
    - Todas las relaciones inversas deben agregarse con comentarios PHPDoc en español
    - Las relaciones deben incluirse en `getAllowedRelations()` de cada modelo
    - Verificar que las relaciones funcionen correctamente con eager loading

## Consideraciones Técnicas

1. **Performance:**

    - Usar índices en campos de búsqueda frecuente
    - Considerar eager loading para relaciones
    - Optimizar queries de reportes

2. **Seguridad:**

    - Validar permisos en cada operación
    - No permitir modificación de recibos cerrados o anulados
    - Validar que el cajero tenga permisos para la sede

3. **Auditoría:**

    - Considerar crear tabla de auditoría para cambios importantes
    - Registrar quién y cuándo se anuló o cerró un recibo

4. **Integración:**
    - Considerar integración con sistema de caja
    - Considerar integración con sistema contable
