# Casos de Uso - Módulo de Listas de Precios

Este documento describe los casos de uso principales del módulo de Listas de Precios.

## 1. Gestión de Precios por Ciudad

### Descripción
El sistema permite definir diferentes precios para el mismo producto según la ciudad (población) donde se ofrezca.

### Flujo
1. Crear lista de precios para una ciudad específica
2. Asociar la lista a la población correspondiente
3. Agregar precios de productos a la lista
4. Aprobar y activar la lista

### Ejemplo
```bash
# Crear lista para Bogotá
POST /api/financiero/lp/listas-precios
{
  "nombre": "Lista Bogotá 2025",
  "codigo": "LP-BOG-2025",
  "fecha_inicio": "2025-01-01",
  "fecha_fin": "2025-12-31",
  "poblaciones": [1],  // Bogotá
  "status": 1
}

# Agregar precio de curso
POST /api/financiero/lp/precios-producto
{
  "lista_precio_id": 1,
  "producto_id": 1,
  "precio_contado": 1000000,
  "precio_total": 1200000,
  "matricula": 200000,
  "numero_cuotas": 10
}
```

## 2. Financiación de Cursos y Módulos

### Descripción
Los cursos y módulos pueden ser financiados con matrícula inicial y cuotas mensuales. El sistema calcula automáticamente el valor de cada cuota redondeando al 100 más cercano.

### Flujo
1. Crear producto de tipo "curso" o "modulo" (financiable)
2. Agregar precio con matrícula y número de cuotas
3. El sistema calcula automáticamente el valor de la cuota

### Ejemplo
```bash
# Crear precio con financiación
POST /api/financiero/lp/precios-producto
{
  "lista_precio_id": 1,
  "producto_id": 1,
  "precio_contado": 1000000,
  "precio_total": 1200000,
  "matricula": 200000,
  "numero_cuotas": 10
}

# El sistema calcula: (1200000 - 200000) / 10 = 100000
# Si fuera 100530, se redondearía a 100500
# Si fuera 100580, se redondearía a 100600
```

## 3. Productos Complementarios

### Descripción
Los productos complementarios (como certificados) no se financian y solo tienen precio de contado.

### Flujo
1. Crear producto de tipo "complementario" (no financiable)
2. Agregar solo precio de contado

### Ejemplo
```bash
# Crear producto complementario
POST /api/financiero/lp/productos
{
  "tipo_producto_id": 3,  // Complementario
  "nombre": "Certificado de Estudios",
  "codigo": "CERT-001",
  "status": 1
}

# Agregar precio (solo contado)
POST /api/financiero/lp/precios-producto
{
  "lista_precio_id": 1,
  "producto_id": 5,
  "precio_contado": 50000
}
```

## 4. Consulta de Precios Vigentes

### Descripción
El sistema permite consultar el precio vigente de un producto para una población y fecha específicas.

### Flujo
1. Cliente solicita precio de un producto
2. Sistema busca lista de precios activa para la población
3. Sistema verifica que la fecha esté dentro del rango de vigencia
4. Retorna el precio del producto

### Ejemplo
```bash
GET /api/financiero/lp/precios-producto/obtener-precio?producto_id=1&poblacion_id=1&fecha=2025-06-01
```

## 5. Gestión de Estados de Listas

### Descripción
Las listas de precios tienen un flujo de estados: En Proceso → Aprobada → Activa → Inactiva.

### Flujo
1. Crear lista en estado "En Proceso"
2. Editar y configurar precios
3. Aprobar lista (cambia a "Aprobada")
4. La lista se activa automáticamente cuando llega su fecha de inicio
5. La lista se inactiva automáticamente cuando pasa su fecha de fin

### Ejemplo
```bash
# Crear en proceso
POST /api/financiero/lp/listas-precios
{
  "status": 1  // En Proceso
}

# Aprobar
POST /api/financiero/lp/listas-precios/1/aprobar

# Activar manualmente (opcional)
POST /api/financiero/lp/listas-precios/1/activar

# Inactivar manualmente
POST /api/financiero/lp/listas-precios/1/inactivar
```

## 6. Validación de Solapamiento

### Descripción
El sistema previene que existan múltiples listas de precios activas con fechas solapadas para la misma población.

### Flujo
1. Usuario intenta crear/actualizar lista de precios
2. Sistema valida que no haya solapamiento con listas existentes
3. Si hay solapamiento, rechaza la operación

### Ejemplo
```bash
# Intentar crear lista con fechas solapadas
POST /api/financiero/lp/listas-precios
{
  "nombre": "Lista Duplicada",
  "fecha_inicio": "2025-06-01",
  "fecha_fin": "2025-12-31",
  "poblaciones": [1]  // Misma población que lista existente
}

# Respuesta: Error de validación
{
  "message": "Ya existe una lista de precios activa con fechas solapadas para esta población."
}
```

## 7. Actualización de Precios

### Descripción
Los precios pueden actualizarse mientras la lista esté en estado "En Proceso" o "Aprobada".

### Flujo
1. Verificar estado de la lista
2. Si está en proceso o aprobada, actualizar precios
3. El valor de cuota se recalcula automáticamente

### Ejemplo
```bash
# Actualizar precio
PUT /api/financiero/lp/precios-producto/1
{
  "precio_contado": 1100000,
  "precio_total": 1300000,
  "matricula": 250000,
  "numero_cuotas": 10
}

# El sistema recalcula valor_cuota automáticamente
```

## 8. Historial de Precios

### Descripción
El sistema mantiene historial de precios mediante soft deletes, permitiendo consultar precios anteriores.

### Flujo
1. Al actualizar o eliminar un precio, se marca como eliminado (soft delete)
2. Los precios eliminados pueden consultarse con `include_trashed=true`
3. Se puede restaurar un precio eliminado

### Ejemplo
```bash
# Consultar precios incluyendo eliminados
GET /api/financiero/lp/precios-producto?include_trashed=true

# Consultar solo eliminados
GET /api/financiero/lp/precios-producto?only_trashed=true
```

## 9. Integración con Facturación

### Descripción
El módulo de facturación puede consultar precios vigentes para generar cotizaciones y facturas.

### Flujo
1. Módulo de facturación solicita precio de producto
2. Sistema retorna precio vigente con opciones de pago
3. Módulo de facturación usa el precio para generar documento

### Ejemplo de Integración
```php
use App\Services\Financiero\LpPrecioProductoService;

$service = app(LpPrecioProductoService::class);
$precio = $service->obtenerPrecio(1, 1, Carbon::now());

if ($precio) {
    // Generar factura con precio de contado
    $factura->total = $precio->precio_contado;
    
    // O generar plan de pago con financiación
    $planPago->matricula = $precio->matricula;
    $planPago->valor_cuota = $precio->valor_cuota;
    $planPago->numero_cuotas = $precio->numero_cuotas;
}
```

## 10. Reportes y Análisis

### Descripción
El sistema permite consultar listas de precios con filtros para análisis y reportes.

### Flujo
1. Filtrar listas por estado, fecha, población
2. Consultar precios de productos en diferentes listas
3. Comparar precios entre ciudades o períodos

### Ejemplo
```bash
# Listar listas activas
GET /api/financiero/lp/listas-precios?status=3

# Listar precios de un producto en todas las listas
GET /api/financiero/lp/precios-producto?producto_id=1

# Filtrar por población
GET /api/financiero/lp/listas-precios?poblacion_id=1
```

