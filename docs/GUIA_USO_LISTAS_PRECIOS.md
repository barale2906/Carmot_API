# Guía de Uso - Módulo de Listas de Precios

Esta guía proporciona ejemplos prácticos y flujos de trabajo comunes para el uso del módulo de Listas de Precios.

## Tabla de Contenidos

1. [Flujos de Trabajo Comunes](#flujos-de-trabajo-comunes)
2. [Casos de Uso](#casos-de-uso)
3. [Ejemplos de Integración](#ejemplos-de-integración)
4. [Troubleshooting](#troubleshooting)

## Flujos de Trabajo Comunes

### 1. Crear una Nueva Lista de Precios

**Escenario:** Necesitas crear una lista de precios para el año 2025 que aplicará a varias ciudades.

**Pasos:**

1. **Crear o verificar tipos de producto:**
   ```bash
   GET /api/financiero/lp/tipos-producto
   ```
   Si no existen, crear los tipos necesarios (curso, modulo, complementario).

2. **Crear productos si es necesario:**
   ```bash
   POST /api/financiero/lp/productos
   {
     "tipo_producto_id": 1,
     "nombre": "Curso de Programación Web",
     "codigo": "CURSO-PWEB-001",
     "referencia_id": 1,
     "referencia_tipo": "curso",
     "status": 1
   }
   ```

3. **Crear la lista de precios:**
   ```bash
   POST /api/financiero/lp/listas-precios
   {
     "nombre": "Lista de Precios 2025",
     "codigo": "LP-2025-001",
     "fecha_inicio": "2025-01-01",
     "fecha_fin": "2025-12-31",
     "poblaciones": [1, 2, 3],
     "status": 1,
     "descripcion": "Lista de precios vigente para el año 2025"
   }
   ```

4. **Agregar precios de productos:**
   ```bash
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
   El `valor_cuota` se calculará automáticamente: (1200000 - 200000) / 10 = 100000

5. **Aprobar la lista:**
   ```bash
   POST /api/financiero/lp/listas-precios/1/aprobar
   ```

6. **La lista se activará automáticamente** cuando llegue la fecha de inicio (mediante comando programado).

### 2. Consultar Precio de un Producto

**Escenario:** Un cliente quiere saber el precio de un curso en su ciudad.

**Pasos:**

```bash
GET /api/financiero/lp/precios-producto/obtener-precio?producto_id=1&poblacion_id=1&fecha=2025-06-01
```

**Respuesta:**
```json
{
  "message": "Precio obtenido exitosamente.",
  "data": {
    "id": 1,
    "lista_precio_id": 1,
    "producto_id": 1,
    "precio_contado": 1000000,
    "precio_total": 1200000,
    "matricula": 200000,
    "numero_cuotas": 10,
    "valor_cuota": 100000,
    "producto": {
      "id": 1,
      "nombre": "Curso de Programación Web",
      "tipo_producto": {
        "nombre": "Curso",
        "es_financiable": true
      }
    },
    "lista_precio": {
      "nombre": "Lista de Precios 2025",
      "fecha_inicio": "2025-01-01",
      "fecha_fin": "2025-12-31"
    }
  }
}
```

### 3. Actualizar Precios en una Lista Existente

**Escenario:** Necesitas actualizar el precio de un producto en una lista que está "En Proceso".

**Pasos:**

1. **Verificar estado de la lista:**
   ```bash
   GET /api/financiero/lp/listas-precios/1
   ```

2. **Actualizar el precio:**
   ```bash
   PUT /api/financiero/lp/precios-producto/1
   {
     "precio_contado": 1100000,
     "precio_total": 1300000,
     "matricula": 250000,
     "numero_cuotas": 10
   }
   ```
   El `valor_cuota` se recalculará automáticamente: (1300000 - 250000) / 10 = 105000

## Casos de Uso

### Caso 1: Crear Lista de Precios para Múltiples Ciudades

**Problema:** Necesitas crear una lista de precios que aplicará a Bogotá, Medellín y Cali.

**Solución:**

```bash
POST /api/financiero/lp/listas-precios
{
  "nombre": "Lista Nacional 2025",
  "codigo": "LP-NAC-2025",
  "fecha_inicio": "2025-01-01",
  "fecha_fin": "2025-12-31",
  "poblaciones": [1, 2, 3],  // IDs de Bogotá, Medellín, Cali
  "status": 1
}
```

### Caso 2: Producto con Precio Diferente por Ciudad

**Problema:** Un curso tiene precio diferente en Bogotá vs Medellín.

**Solución:**

Crear dos listas de precios diferentes, cada una asociada a su respectiva ciudad:

```bash
# Lista para Bogotá
POST /api/financiero/lp/listas-precios
{
  "nombre": "Lista Bogotá 2025",
  "codigo": "LP-BOG-2025",
  "fecha_inicio": "2025-01-01",
  "fecha_fin": "2025-12-31",
  "poblaciones": [1],  // Solo Bogotá
  "status": 1
}

# Lista para Medellín
POST /api/financiero/lp/listas-precios
{
  "nombre": "Lista Medellín 2025",
  "codigo": "LP-MED-2025",
  "fecha_inicio": "2025-01-01",
  "fecha_fin": "2025-12-31",
  "poblaciones": [2],  // Solo Medellín
  "status": 1
}
```

Luego agregar precios diferentes para el mismo producto en cada lista.

### Caso 3: Producto Complementario (No Financiable)

**Problema:** Necesitas agregar un certificado de estudios que solo tiene precio de contado.

**Solución:**

1. Crear producto complementario:
```bash
POST /api/financiero/lp/productos
{
  "tipo_producto_id": 3,  // ID del tipo "complementario"
  "nombre": "Certificado de Estudios",
  "codigo": "CERT-001",
  "status": 1
}
```

2. Agregar precio (solo precio_contado):
```bash
POST /api/financiero/lp/precios-producto
{
  "lista_precio_id": 1,
  "producto_id": 5,
  "precio_contado": 50000
  // No se incluyen precio_total, matricula, numero_cuotas
}
```

### Caso 4: Cambiar Estado de Lista Manualmente

**Problema:** Necesitas activar una lista aprobada antes de su fecha de inicio.

**Solución:**

```bash
POST /api/financiero/lp/listas-precios/1/activar
```

**Nota:** Solo funciona si la lista está en estado "Aprobada".

## Ejemplos de Integración

### Integración con Módulo de Facturación

Cuando necesites obtener el precio de un producto para generar una factura:

```php
use App\Services\Financiero\LpPrecioProductoService;
use Carbon\Carbon;

$service = app(LpPrecioProductoService::class);

// Obtener precio vigente
$precio = $service->obtenerPrecio(
    productoId: 1,
    poblacionId: 1,
    fecha: Carbon::now()
);

if ($precio) {
    // Usar precio_contado para pago de contado
    $precioContado = $precio->precio_contado;
    
    // O usar precio_total, matricula y valor_cuota para financiación
    $precioTotal = $precio->precio_total;
    $matricula = $precio->matricula;
    $valorCuota = $precio->valor_cuota;
    $numeroCuotas = $precio->numero_cuotas;
}
```

### Integración con Frontend

**Ejemplo en JavaScript (Vue/React):**

```javascript
// Obtener precio de un producto
async function obtenerPrecio(productoId, poblacionId, fecha = null) {
  const params = new URLSearchParams({
    producto_id: productoId,
    poblacion_id: poblacionId
  });
  
  if (fecha) {
    params.append('fecha', fecha);
  }
  
  const response = await fetch(
    `/api/financiero/lp/precios-producto/obtener-precio?${params}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );
  
  const data = await response.json();
  return data.data;
}

// Usar el precio
const precio = await obtenerPrecio(1, 1, '2025-06-01');

if (precio) {
  console.log('Precio de contado:', precio.precio_contado);
  console.log('Precio total:', precio.precio_total);
  console.log('Matrícula:', precio.matricula);
  console.log('Valor cuota:', precio.valor_cuota);
  console.log('Número de cuotas:', precio.numero_cuotas);
}
```

## Troubleshooting

### Problema: No se encuentra precio vigente

**Síntomas:** La API retorna 404 al buscar un precio.

**Causas posibles:**
1. No existe una lista de precios activa para la población y fecha especificadas
2. La lista de precios no está en estado "Activa"
3. La fecha está fuera del rango de vigencia de la lista
4. El producto no tiene precio configurado en la lista

**Solución:**
1. Verificar que exista una lista de precios activa:
   ```bash
   GET /api/financiero/lp/listas-precios?status=3&poblacion_id=1
   ```

2. Verificar que la lista incluya el producto:
   ```bash
   GET /api/financiero/lp/precios-producto?lista_precio_id=1&producto_id=1
   ```

3. Verificar fechas de vigencia:
   ```bash
   GET /api/financiero/lp/listas-precios/1
   ```

### Problema: Error al aprobar lista de precios

**Síntomas:** Error 422 al intentar aprobar una lista.

**Causa:** La lista no está en estado "En Proceso".

**Solución:**
```bash
# Verificar estado actual
GET /api/financiero/lp/listas-precios/1

# Si está en otro estado, cambiar primero a "En Proceso"
PUT /api/financiero/lp/listas-precios/1
{
  "status": 1  // En Proceso
}

# Luego aprobar
POST /api/financiero/lp/listas-precios/1/aprobar
```

### Problema: Error de solapamiento de vigencia

**Síntomas:** Error de validación al crear/actualizar lista de precios.

**Causa:** Ya existe una lista de precios activa con fechas solapadas para la misma población.

**Solución:**
1. Verificar listas existentes:
   ```bash
   GET /api/financiero/lp/listas-precios?poblacion_id=1&status=3
   ```

2. Ajustar fechas para evitar solapamiento o inactivar la lista existente.

### Problema: Valor de cuota no se calcula

**Síntomas:** El campo `valor_cuota` está en null después de crear/actualizar.

**Causas posibles:**
1. El producto no es financiable
2. Faltan campos requeridos (precio_total, matricula, numero_cuotas)
3. El número de cuotas es 0 o negativo

**Solución:**
1. Verificar que el producto sea financiable:
   ```bash
   GET /api/financiero/lp/productos/1?with=tipoProducto
   ```
   Verificar que `tipo_producto.es_financiable` sea `true`.

2. Verificar que todos los campos estén presentes:
   ```bash
   GET /api/financiero/lp/precios-producto/1
   ```

### Problema: Lista no se activa automáticamente

**Síntomas:** La lista está aprobada pero no se activa cuando llega la fecha de inicio.

**Causa:** El comando programado no se está ejecutando.

**Solución:**
1. Ejecutar el comando manualmente para verificar:
   ```bash
   php artisan financiero:gestionar-listas-precios
   ```

2. Verificar que el comando esté programado en `app/Console/Kernel.php`:
   ```php
   $schedule->command('financiero:gestionar-listas-precios')->daily();
   ```

3. Verificar que el cron esté configurado en el servidor:
   ```bash
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```

## Mejores Prácticas

1. **Siempre crear listas en estado "En Proceso"** para poder editarlas antes de aprobarlas.

2. **Validar solapamientos** antes de crear nuevas listas para evitar conflictos.

3. **Usar códigos únicos y descriptivos** para facilitar la identificación de listas.

4. **Revisar precios antes de aprobar** una lista, ya que después será más difícil modificarlos.

5. **Documentar cambios importantes** en el campo `descripcion` de las listas.

6. **Mantener historial** usando soft deletes en lugar de eliminar permanentemente.

7. **Usar el servicio** `LpPrecioProductoService` para obtener precios en lugar de consultas directas.

