# Plan de Implementación - Módulo Conceptos de Pago

## Descripción General

Este documento describe la implementación del modelo **Conceptos de Pago** dentro del módulo Financiero. Este modelo tiene la función de organizar los diferentes conceptos por los cuales se van a recibir pagos al momento de matricular a los estudiantes, recibir sus pagos por cobros adicionales, recargos por pago con tarjeta, pagos por acuerdo de pago, etc.

## Estructura de Carpetas

El módulo ConceptoPago sigue la misma estructura organizacional que el módulo Lp:

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── Financiero/
│   │           └── ConceptoPago/
│   │               └── ConceptoPagoController.php
│   ├── Requests/
│   │   └── Api/
│   │       └── Financiero/
│   │           └── ConceptoPago/
│   │               ├── StoreConceptoPagoRequest.php
│   │               └── UpdateConceptoPagoRequest.php
│   └── Resources/
│       └── Api/
│           └── Financiero/
│               └── ConceptoPago/
│                   └── ConceptoPagoResource.php
└── Models/
    └── Financiero/
        └── ConceptoPago/
            └── ConceptoPago.php

database/
├── factories/
│   └── Financiero/
│       └── ConceptoPago/
│           └── ConceptoPagoFactory.php
└── seeders/
    └── ConceptoPagoSeeder.php
```

## Estructura del Modelo

### Campos del Modelo

1. **nombre** (string, obligatorio)

    - Nombre descriptivo del concepto de pago
    - Máximo 255 caracteres
    - Ejemplos: "Matrícula", "Pago de mensualidad", "Recargo por pago con tarjeta"

2. **tipo** (array/json, obligatorio)

    - Array de tipos disponibles para el concepto de pago
    - Tipos iniciales:
        - `0 => 'Cartera'`
        - `1 => 'Financiero'`
        - `2 => 'Inventario'`
        - `3 => 'Otro'`
    - El array es editable y permite agregar nuevos tipos mediante el método `agregarTipo()`

3. **valor** (decimal, obligatorio)
    - Valor del concepto de pago
    - Precisión: hasta 2 decimales
    - Formato: `decimal(10, 2)` en la base de datos
    - Debe ser mayor o igual a 0

## Archivos Creados

### 1. Migración de Base de Datos

**Archivo:** `database/migrations/2025_12_01_100000_create_conceptos_pago_table.php`

-   Crea la tabla `conceptos_pago` con los campos:
    -   `id` (bigint, primary key)
    -   `nombre` (string, 255 caracteres)
    -   `tipo` (json) - Almacena el array de tipos
    -   `valor` (decimal 10,2)
    -   `created_at`, `updated_at` (timestamps)
    -   `deleted_at` (soft deletes)
-   Índices creados:
    -   `idx_nombre` - Para búsquedas rápidas por nombre
    -   `idx_valor` - Para filtros por valor

### 2. Modelo Eloquent

**Archivo:** `app/Models/Financiero/ConceptoPago/ConceptoPago.php`

**Características:**

-   Usa los traits: `HasFactory`, `SoftDeletes`, `HasFilterScopes`, `HasGenericScopes`, `HasSortingScopes`, `HasRelationScopes`
-   Casts:
    -   `tipo` => `array` (conversión automática JSON ↔ Array)
    -   `valor` => `decimal:2` (formato con 2 decimales)
-   Constante `TIPOS_DEFAULT` con los tipos iniciales
-   Métodos personalizados:
    -   `getTiposDisponibles()`: Retorna los tipos disponibles del concepto
    -   `agregarTipo(string $nuevoTipo)`: Agrega un nuevo tipo al array (solo si no existe)

### 3. Factory

**Archivo:** `database/factories/Financiero/ConceptoPago/ConceptoPagoFactory.php`

**Estados disponibles:**

-   `tipoCartera()`: Crea concepto con tipo "Cartera"
-   `tipoFinanciero()`: Crea concepto con tipo "Financiero"
-   `tipoInventario()`: Crea concepto con tipo "Inventario"
-   `tipoOtro()`: Crea concepto con tipo "Otro"
-   `tipoMultiple()`: Crea concepto con múltiples tipos
-   `conValor(float $valor)`: Establece un valor específico

### 4. Seeder

**Archivo:** `database/seeders/ConceptoPagoSeeder.php`

**Conceptos de pago iniciales creados:**

1. Matrícula (Financiero)
2. Pago de mensualidad (Financiero)
3. Recargo por pago con tarjeta (Financiero) - Valor: 5,000
4. Cobro adicional por material (Inventario)
5. Pago por acuerdo de pago (Cartera)
6. Recargo por mora (Financiero) - Valor: 10,000
7. Pago de certificado (Otro) - Valor: 25,000
8. Cobro por reposición de clase (Financiero) - Valor: 50,000
9. Pago de uniforme (Inventario)
10. Cobro por material didáctico (Inventario)

### 5. Request Classes

#### StoreConceptoPagoRequest

**Archivo:** `app/Http/Requests/Api/Financiero/ConceptoPago/StoreConceptoPagoRequest.php`

**Validaciones:**

-   `nombre`: required|string|max:255
-   `tipo`: required|array|min:1
-   `tipo.*`: required|string|max:255
-   `valor`: required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/

#### UpdateConceptoPagoRequest

**Archivo:** `app/Http/Requests/Api/Financiero/ConceptoPago/UpdateConceptoPagoRequest.php`

**Validaciones:**

-   Todos los campos usan `sometimes` para permitir actualizaciones parciales
-   Mismas reglas de validación que StoreConceptoPagoRequest

### 6. Resource

**Archivo:** `app/Http/Resources/Api/Financiero/ConceptoPago/ConceptoPagoResource.php`

**Campos incluidos en la respuesta JSON:**

-   `id`: ID del concepto
-   `nombre`: Nombre del concepto
-   `tipo`: Array de tipos
-   `valor`: Valor numérico
-   `valor_formatted`: Valor formateado con separadores de miles
-   `created_at`, `updated_at`, `deleted_at`: Fechas formateadas

### 7. Controller

**Archivo:** `app/Http/Controllers/Api/Financiero/ConceptoPago/ConceptoPagoController.php`

**Endpoints implementados:**

1. **GET** `/api/conceptos-pago` - Listar conceptos de pago

    - Filtros disponibles:
        - `search`: Búsqueda por nombre
        - `tipo`: Filtrar por tipo específico
        - `valor_min`: Valor mínimo
        - `valor_max`: Valor máximo
        - `include_trashed`: Incluir eliminados
        - `only_trashed`: Solo eliminados
    - Ordenamiento: `sort_by`, `sort_direction`
    - Paginación: `per_page`

2. **POST** `/api/conceptos-pago` - Crear concepto de pago

    - Requiere permisos: `fin_conceptoPagoCrear`

3. **GET** `/api/conceptos-pago/{id}` - Mostrar concepto de pago

    - Requiere permisos: `fin_conceptos_pago`

4. **PUT/PATCH** `/api/conceptos-pago/{id}` - Actualizar concepto de pago

    - Requiere permisos: `fin_conceptoPagoEditar`

5. **DELETE** `/api/conceptos-pago/{id}` - Eliminar concepto de pago (soft delete)

    - Requiere permisos: `fin_conceptoPagoInactivar`

6. **POST** `/api/conceptos-pago/{id}/agregar-tipo` - Agregar nuevo tipo al concepto
    - Body: `{ "tipo": "Nuevo Tipo" }`
    - Agrega un nuevo tipo al array si no existe

### 8. Rutas

**Archivo:** `routes/financiero.php`

**Rutas agregadas:**

```php
Route::apiResource('conceptos-pago', ConceptoPagoController::class);
Route::post('conceptos-pago/{conceptoPago}/agregar-tipo', [ConceptoPagoController::class, 'agregarTipo']);
```

## Permisos Requeridos

Los siguientes permisos deben ser creados en el sistema de permisos:

1. `fin_conceptos_pago` - Ver listado y detalles
2. `fin_conceptoPagoCrear` - Crear nuevos conceptos
3. `fin_conceptoPagoEditar` - Actualizar conceptos existentes
4. `fin_conceptoPagoInactivar` - Eliminar conceptos (soft delete)

## Ejecución de Migraciones y Seeders

### Ejecutar migración:

```bash
php artisan migrate
```

### Ejecutar seeder:

```bash
php artisan db:seed --class=ConceptoPagoSeeder
```

## Ejemplos de Uso

### Crear un concepto de pago:

```json
POST /api/conceptos-pago
{
    "nombre": "Matrícula",
    "tipo": ["Financiero"],
    "valor": 150000.00
}
```

### Actualizar un concepto de pago:

```json
PUT /api/conceptos-pago/1
{
    "valor": 200000.00
}
```

### Agregar un nuevo tipo a un concepto:

```json
POST /api/conceptos-pago/1/agregar-tipo
{
    "tipo": "Contabilidad"
}
```

### Filtrar conceptos por tipo:

```
GET /api/conceptos-pago?tipo=Financiero
```

### Buscar conceptos por nombre:

```
GET /api/conceptos-pago?search=matrícula
```

## Notas de Implementación

1. **Campo tipo como array**: El campo `tipo` se almacena como JSON en la base de datos y se convierte automáticamente a array en PHP mediante el cast del modelo.

2. **Agregar nuevos tipos**: Los nuevos tipos se pueden agregar mediante el endpoint `agregar-tipo` o directamente actualizando el campo `tipo` con un array completo.

3. **Validación de decimales**: La validación del campo `valor` usa una expresión regular para asegurar máximo 2 decimales: `/^\d+(\.\d{1,2})?$/`

4. **Soft Deletes**: Todos los conceptos de pago usan soft deletes, por lo que no se eliminan permanentemente de la base de datos.

5. **Estructura consistente**: La implementación sigue la misma estructura y patrones utilizados en los modelos del módulo Lp (Listas de Precios).

## Próximos Pasos (Opcional)

1. Crear tests unitarios y de integración
2. Agregar documentación API con Scramble/Swagger
3. Implementar validaciones adicionales según reglas de negocio
4. Crear relaciones con otros modelos financieros (pagos, facturas, etc.)
