# Casos de Uso - Módulo de Descuentos

Este documento describe los casos de uso principales del módulo de Descuentos y cómo interactúa con el módulo de Listas de Precios.

## 1. Descuento por Pago Anticipado

### Descripción

Un descuento que se aplica cuando el estudiante realiza el pago antes de la fecha programada, incentivando pagos puntuales o anticipados.

### Escenario

Un curso tiene un precio de $2,000,000 con matrícula de $500,000 y 10 cuotas de $150,000. Se ofrece un 5% de descuento si se paga al menos 15 días antes de la fecha programada.

### Flujo

1. Se crea un descuento de tipo "pago anticipado"
2. Se configura con 5% de descuento porcentual
3. Se establece que aplica al valor total
4. Se requiere mínimo 15 días de anticipación
5. Se asocia a las listas de precios correspondientes

### Ejemplo de Código

```php
use App\Models\Financiero\Descuento\Descuento;
use Carbon\Carbon;

$descuento = Descuento::create([
    'nombre' => 'Descuento 5% Pago Anticipado',
    'codigo' => 'DESC-PAGO-ANT-5',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 5.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
    'dias_anticipacion' => 15,
    'fecha_inicio' => Carbon::now(),
    'fecha_fin' => Carbon::now()->addYear(),
    'status' => Descuento::STATUS_EN_PROCESO,
]);

$descuento->listasPrecios()->attach([1]); // Lista de precios 2025
```

### Cálculo

-   Precio original: $2,000,000
-   Descuento 5%: $100,000
-   Precio con descuento: $1,900,000
-   Nueva matrícula: $500,000 (sin cambios)
-   Nuevo valor restante: $1,400,000
-   Nueva cuota (10 cuotas): $140,000

## 2. Promoción de Matrícula con Fecha Límite

### Descripción

Un descuento que se aplica a las matrículas realizadas antes de una fecha límite específica, comúnmente usado para promociones de inicio de ciclo.

### Escenario

Se ofrece un 10% de descuento en matrículas realizadas antes del 15 de enero de 2025 para el ciclo de primer semestre.

### Flujo

1. Se crea un descuento de tipo "promoción matrícula"
2. Se configura con 10% de descuento porcentual
3. Se establece fecha límite de registro: 15 de enero de 2025
4. Se asocia a las listas de precios del primer semestre
5. Se puede limitar a ciudades o sedes específicas

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Promoción Matrícula Enero 2025',
    'codigo' => 'PROM-MAT-ENE-2025',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 10.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-01-31'),
    'status' => Descuento::STATUS_EN_PROCESO, // Estado inicial: en proceso
]);

$descuento->listasPrecios()->attach([1, 2]); // Listas del primer semestre
$descuento->poblaciones()->attach([1]); // Solo Bogotá
```

### Cálculo

-   Precio original: $2,000,000
-   Descuento 10%: $200,000
-   Precio con descuento: $1,800,000
-   Nueva matrícula: $500,000 (sin cambios)
-   Nuevo valor restante: $1,300,000
-   Nueva cuota (10 cuotas): $130,000

## 3. Descuento a la Matrícula

### Descripción

Un descuento que se aplica solo sobre el valor de la matrícula, sin afectar el precio total ni las cuotas.

### Escenario

Se ofrece un 10% de descuento en la matrícula para estudiantes que se matriculen durante el período de vigencia del descuento (enero 2025).

### Flujo

1. Se crea un descuento de tipo "porcentual"
2. Se configura con 10% de descuento
3. Se establece que aplica solo a la matrícula
4. Se define el período de vigencia (fecha_inicio y fecha_fin)
5. El descuento aplica si la matrícula se realiza dentro del período de vigencia

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Descuento 10% Matrícula Enero',
    'codigo' => 'DESC-MAT-10',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 10.00,
    'aplicacion' => Descuento::APLICACION_MATRICULA,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-01-31'),
    'status' => Descuento::STATUS_EN_PROCESO,
    'permite_acumulacion' => false,
]);
```

### Cálculo

-   Matrícula original: $500,000
-   Descuento 10%: $50,000
-   Matrícula con descuento: $450,000
-   Precio total y cuotas: Sin cambios

## 4. Descuento de Valor Fijo en Cuotas

### Descripción

Un descuento de monto fijo que se aplica solo al valor de cada cuota, sin afectar el precio total ni la matrícula. El descuento se aplica por cada cuota u obligación individualmente.

### Escenario

Se ofrece un descuento de $20,000 en cada cuota para estudiantes que paguen anticipadamente.

### Flujo

1. Se crea un descuento de tipo "valor fijo"
2. Se configura con $20,000 de descuento
3. Se establece que aplica solo a cuotas
4. Se requiere pago anticipado de 10 días
5. El descuento se aplica por cada cuota individualmente mientras esté vigente
6. Cada cuota puede tener el descuento aplicado una sola vez, independientemente de si se pagan una o más cuotas por anticipado

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Descuento $20K en Cuotas',
    'codigo' => 'DESC-CUOTA-20K',
    'tipo_descuento' => Descuento::TIPO_VALOR_FIJO,
    'valor_descuento' => 20000.00,
    'aplicacion' => Descuento::APLICACION_CUOTA,
    'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
    'dias_anticipacion' => 10,
    'fecha_inicio' => Carbon::now(),
    'fecha_fin' => Carbon::now()->addYear(),
    'status' => Descuento::STATUS_EN_PROCESO,
    'permite_acumulacion' => true,
]);
```

### Cálculo

-   Cuota original: $150,000
-   Descuento: $20,000
-   Cuota con descuento: $130,000
-   Precio total y matrícula: Sin cambios
-   **Nota importante:**
    -   El descuento se aplica por cada cuota u obligación individualmente
    -   Cada cuota puede tener el descuento aplicado una sola vez
    -   Si una persona paga una o más cuotas por anticipado, cada cuota individual tendrá su descuento aplicado una vez
    -   El descuento aplica a todas las cuotas siempre que se cumplan las condiciones de vigencia del descuento
    -   Ejemplo: Si hay 10 cuotas y el descuento está vigente, cada una de las 10 cuotas tendrá el descuento aplicado cuando se paguen

## 5. Descuento con Código Promocional

### Descripción

Un descuento que se activa mediante un código alfanumérico ingresado por el usuario, útil para promociones en redes sociales o publicidad impresa.

### Escenario

Se publica en redes sociales el código "PROMO2025" que otorga un 15% de descuento en el valor total para matrículas en línea.

### Flujo

1. Se crea un descuento de tipo "código promocional"
2. Se configura el código alfanumérico único
3. Se establece el porcentaje o valor fijo del descuento
4. Se define la aplicación (valor total, matrícula o cuota)
5. El usuario ingresa el código al momento de la matrícula/pago

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Promoción Redes Sociales 2025',
    'codigo' => 'PROMO-RS-2025',
    'codigo_descuento' => 'PROMO2025',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 15.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_CODIGO_PROMOCIONAL,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-03-31'),
    'status' => 1,
    'permite_acumulacion' => false,
]);

$descuento->listasPrecios()->attach([1]);
```

### Uso

El usuario ingresa el código "PROMO2025" en el formulario de matrícula en línea, y el sistema valida y aplica el descuento automáticamente.

## 6. Descuento Específico por Producto

### Descripción

Un descuento que solo aplica a productos específicos dentro de una lista de precios.

### Escenario

Se ofrece un 15% de descuento solo en cursos de programación, no en otros productos.

### Flujo

1. Se crea el descuento con las condiciones generales
2. Se asocia a productos específicos (cursos de programación)
3. El descuento no aplica a otros productos de la lista

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Descuento Cursos Programación',
    'codigo' => 'DESC-PROG-15',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 15.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-02-28'),
    'status' => Descuento::STATUS_EN_PROCESO,
]);

$descuento->listasPrecios()->attach([1]);
// Solo productos de programación (IDs: 5, 6, 7)
$descuento->productos()->attach([5, 6, 7]);
```

## 7. Descuento por Ciudad

### Descripción

Un descuento que solo aplica en ciudades específicas, útil para promociones regionales.

### Escenario

Se ofrece un 8% de descuento solo en Medellín y Cali para incentivar matrículas en esas ciudades.

### Flujo

1. Se crea el descuento
2. Se asocia a las poblaciones (ciudades) correspondientes
3. El descuento aplica a todas las sedes de esas ciudades

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Promoción Regional Medellín-Cali',
    'codigo' => 'PROM-REG-MED-CAL',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 8.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-02-01'),
    'fecha_fin' => Carbon::parse('2025-03-31'),
    'status' => Descuento::STATUS_EN_PROCESO,
]);

$descuento->listasPrecios()->attach([1]);
// Medellín (ID: 2) y Cali (ID: 3)
$descuento->poblaciones()->attach([2, 3]);
```

## 8. Descuento por Sede Específica

### Descripción

Un descuento que solo aplica en sedes específicas, útil para promociones de apertura o sedes nuevas.

### Escenario

Se ofrece un 12% de descuento solo en la nueva sede del norte de Bogotá.

### Flujo

1. Se crea el descuento
2. Se asocia a la sede específica
3. El descuento solo aplica en esa sede

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Apertura Sede Norte Bogotá',
    'codigo' => 'APERT-SEDE-NORTE',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 12.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-03-01'),
    'fecha_fin' => Carbon::parse('2025-04-30'),
    'status' => Descuento::STATUS_EN_PROCESO,
]);

$descuento->listasPrecios()->attach([1]);
// Solo sede norte (ID: 5)
$descuento->sedes()->attach([5]);
```

## 9. Consulta de Descuentos Aplicables

### Descripción

Obtener todos los descuentos que aplican a un producto específico en una sede, considerando las condiciones de activación.

### Flujo

1. Se consulta el producto y la lista de precios
2. Se identifica la sede del estudiante
3. Se verifican las condiciones de activación (fecha de pago, fecha de matrícula)
4. Se retornan los descuentos aplicables

### Ejemplo de Código

```php
use App\Services\Financiero\DescuentoService;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Carbon\Carbon;

$service = new DescuentoService();
$precioProducto = LpPrecioProducto::find(1);

// Obtener descuentos aplicables
$descuentos = $service->obtenerDescuentosAplicables(
    productoId: $precioProducto->producto_id,
    listaPrecioId: $precioProducto->lista_precio_id,
    sedeId: 1,
    fechaPago: Carbon::now(),
    fechaProgramada: Carbon::now()->addDays(20),
    fechaMatricula: Carbon::parse('2025-01-10')
);

foreach ($descuentos as $descuento) {
    echo "Descuento: {$descuento->nombre} - {$descuento->valor_descuento}%\n";
}
```

## 10. Cálculo de Precio Final con Descuentos

### Descripción

Calcular el precio final de un producto aplicando todos los descuentos aplicables.

### Flujo

1. Se obtiene el precio base del producto
2. Se identifican los descuentos aplicables
3. Se aplican los descuentos según su tipo y aplicación
4. Se recalcula matrícula y cuotas si aplica
5. Se retorna el precio final con el detalle de descuentos

### Ejemplo de Código

```php
$precioConDescuentos = $service->calcularPrecioConDescuentos(
    precioProducto: $precioProducto,
    sedeId: 1,
    tipoAplicacion: 'valor_total',
    fechaPago: Carbon::now(),
    fechaProgramada: Carbon::now()->addDays(20),
    fechaMatricula: Carbon::parse('2025-01-10')
);

echo "Precio original: $" . number_format($precioProducto->precio_total, 2) . "\n";
echo "Total descuentos: $" . number_format($precioConDescuentos['total_descuentos'], 2) . "\n";
echo "Precio final: $" . number_format($precioConDescuentos['precio_total'], 2) . "\n";
echo "Nueva cuota: $" . number_format($precioConDescuentos['valor_cuota'], 2) . "\n";

// Detalle de descuentos aplicados
foreach ($precioConDescuentos['descuentos_aplicados'] as $desc) {
    echo "- {$desc['nombre']}: {$desc['tipo']} {$desc['valor']} = $" .
         number_format($desc['descuento_aplicado'], 2) . "\n";
}
```

## 11. Validación de Solapamiento de Vigencia

### Descripción

Validar que no existan descuentos con vigencia solapada para las mismas condiciones (listas de precios, productos, etc.).

### Flujo

1. Al crear o actualizar un descuento
2. Se verifica si hay otros descuentos activos con vigencia solapada
3. Se valida que no haya conflictos para las mismas listas de precios y productos
4. Se permite o rechaza la operación según el resultado

### Ejemplo de Código

```php
$valido = $service->validarSolapamientoVigencia(
    fechaInicio: Carbon::parse('2025-01-01'),
    fechaFin: Carbon::parse('2025-12-31'),
    listaPrecioIds: [1, 2],
    productoIds: [5, 6],
    excluirDescuentoId: null // Para crear nuevo, null. Para actualizar, ID del descuento
);

if (!$valido) {
    throw new \Exception('Existe un descuento con vigencia solapada para estas condiciones');
}
```

## 12. Descuento Global (Sin Restricciones)

### Descripción

Un descuento que aplica a todos los productos de todas las listas de precios, en todas las sedes y ciudades.

### Escenario

Descuento general del 3% para todos los estudiantes que paguen anticipadamente.

### Flujo

1. Se crea el descuento sin asociar productos, sedes ni ciudades
2. El descuento aplica globalmente
3. Solo se valida la condición de activación

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Descuento General 3%',
    'codigo' => 'DESC-GEN-3',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 3.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
    'dias_anticipacion' => 5,
    'fecha_inicio' => Carbon::now(),
    'fecha_fin' => Carbon::now()->addYear(),
    'status' => Descuento::STATUS_EN_PROCESO,
]);

// Asociar a todas las listas de precios activas
$listasActivas = \App\Models\Financiero\Lp\LpListaPrecio::where('status', 3)->pluck('id');
$descuento->listasPrecios()->attach($listasActivas);

// No se asocian productos, sedes ni ciudades = aplica globalmente
```

## 13. Combinación de Descuentos con Acumulación

### Descripción

Cuando múltiples descuentos aplican al mismo producto y permiten acumulación, se aplican secuencialmente. Si no permiten acumulación, solo se aplica el de mayor valor.

### Escenario

Un estudiante tiene:

-   Descuento por pago anticipado: 5% (permite acumulación)
-   Promoción de matrícula: 10% (permite acumulación)
-   Descuento por ciudad: 8% (no permite acumulación)

### Flujo

1. Se identifican todos los descuentos aplicables
2. Se separan los que permiten acumulación de los que no
3. Si hay descuentos no acumulables, solo se aplica el de mayor valor
4. Los descuentos acumulables se aplican secuencialmente sobre el precio resultante
5. Se recalcula el precio final asegurando que nunca sea negativo

### Ejemplo de Cálculo (con acumulación)

-   Precio original: $2,000,000
-   Descuento 5% (acumulable): $2,000,000 - $100,000 = $1,900,000
-   Descuento 10% sobre $1,900,000 (acumulable): $1,900,000 - $190,000 = $1,710,000
-   Descuento 8% sobre $1,710,000 (no acumulable, pero ya hay acumulables aplicados): Se compara con los acumulables y se toma el mejor
-   Precio final: $1,710,000

### Ejemplo de Cálculo (sin acumulación)

Si todos los descuentos no permiten acumulación:

-   Precio original: $2,000,000
-   Descuento 5%: $100,000
-   Descuento 10%: $200,000
-   Descuento 8%: $160,000
-   Se aplica solo el mayor (10%): $2,000,000 - $200,000 = $1,800,000
-   Precio final: $1,800,000

**Regla importante:** El valor final nunca puede ser inferior a cero (0), independientemente de la cantidad de descuentos aplicados.

## 14. Descuentos con Restricciones Múltiples

### Descripción

Un descuento que combina múltiples restricciones: productos específicos, sedes específicas y condición de activación.

### Escenario

Descuento del 15% solo en cursos de programación, solo en la sede norte de Bogotá, para matrículas antes del 15 de enero.

### Flujo

1. Se crea el descuento con todas las condiciones
2. Se asocian los productos específicos
3. Se asocian las sedes específicas
4. Se configura la condición de activación

### Ejemplo de Código

```php
$descuento = Descuento::create([
    'nombre' => 'Promoción Programación Sede Norte',
    'codigo' => 'PROM-PROG-NORTE',
    'tipo_descuento' => Descuento::TIPO_PORCENTUAL,
    'valor_descuento' => 15.00,
    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
    'fecha_inicio' => Carbon::parse('2025-01-01'),
    'fecha_fin' => Carbon::parse('2025-01-31'),
    'status' => Descuento::STATUS_EN_PROCESO,
]);

$descuento->listasPrecios()->attach([1]);
$descuento->productos()->attach([5, 6, 7]); // Solo cursos de programación
$descuento->sedes()->attach([5]); // Solo sede norte
```

## Consideraciones Importantes

1. **Prioridad de Restricciones:**

    - Sedes específicas tienen prioridad sobre ciudades
    - Si hay sedes asociadas, se ignoran las ciudades

2. **Aplicación de Descuentos:**

    - Los descuentos al valor total afectan el cálculo de cuotas
    - Los descuentos a matrícula solo afectan el valor de la matrícula
    - Los descuentos a cuotas no afectan el precio total ni la matrícula
    - **Importante:** Los descuentos a cuotas se aplican por cada cuota u obligación individualmente
    - Cada cuota puede tener el descuento aplicado una sola vez, independientemente de si se pagan una o más cuotas por anticipado
    - El descuento aplica a todas las cuotas siempre que se cumplan las condiciones de vigencia del descuento

3. **Vigencia y Estados:**

    - Los descuentos deben estar vigentes (fecha actual entre inicio y fin)
    - Los descuentos deben estar activos (status = 3) para aplicarse
    - Estados del descuento: 0 (inactivo), 1 (en proceso), 2 (aprobado), 3 (activo)
    - Los estados Activo (3) e Inactivo (0) se gestionan automáticamente mediante jobs según las fechas de vigencia

4. **Condiciones de Activación:**

    - Pago anticipado: requiere fecha de pago y fecha programada
    - Promoción matrícula: requiere fecha de matrícula dentro del período de vigencia del descuento (fecha_inicio y fecha_fin)
    - Código promocional: requiere código alfanumérico válido y vigente

5. **Acumulación de Descuentos:**

    - Si un descuento permite acumulación, puede aplicarse junto con otros descuentos acumulables
    - Si un descuento no permite acumulación, solo se aplicará si no hay otros descuentos aplicables, o se tomará el de mayor valor
    - El valor final nunca puede ser inferior a cero (0)

6. **Prevención de Doble Aplicación:**
    - Un descuento no se puede aplicar dos veces al mismo concepto (misma cuota u obligación)
    - Si una cuota tiene descuento por pronto pago y la persona hace dos pagos a la misma cuota antes de su vencimiento, solo aplicará el descuento una vez
    - Si una persona paga múltiples cuotas por anticipado, cada cuota individual tendrá su descuento aplicado una vez (una vez por cuota)
    - Los descuentos se calculan sobre el valor a pagar, no sobre el valor pagado
    - Se registra en la tabla `descuento_aplicado` para auditoría y prevención de duplicados
