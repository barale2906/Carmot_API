# Guía de Pruebas - Módulo de Listas de Precios

Este documento contiene las instrucciones para ejecutar las pruebas del módulo de Listas de Precios.

## Pre-requisitos

1. Ejecutar migraciones: `php artisan migrate:fresh`
2. Ejecutar seeders: `php artisan db:seed --class=LpTipoProductoSeeder`
3. Ejecutar seeders de permisos: `php artisan db:seed --class=RolesAndPermissionsSeeder`
4. Tener un usuario autenticado con permisos apropiados

## 1. Pruebas de Migraciones

```bash
# Ejecutar migraciones desde cero
php artisan migrate:fresh

# Verificar que las tablas se crearon
php artisan db:show

# O verificar directamente en la base de datos:
# - lp_tipos_producto
# - lp_productos
# - lp_listas_precios
# - lp_lista_precio_poblacion
# - lp_precios_producto
```

## 2. Pruebas de Modelos (Tinker)

```bash
php artisan tinker
```

### Crear Tipo de Producto
```php
$tipo = App\Models\Financiero\Lp\LpTipoProducto::create([
    'nombre' => 'Curso de Prueba',
    'codigo' => 'CURSO-TEST',
    'es_financiable' => true,
    'descripcion' => 'Tipo de producto de prueba',
    'status' => 1
]);
```

### Crear Producto con Referencia a Curso
```php
// Primero necesitas un curso existente
$curso = App\Models\Academico\Curso::first();

$producto = App\Models\Financiero\Lp\LpProducto::create([
    'tipo_producto_id' => 1,
    'nombre' => 'Producto Curso Test',
    'codigo' => 'PROD-CURSO-001',
    'referencia_id' => $curso->id,
    'referencia_tipo' => 'curso',
    'status' => 1
]);
```

### Crear Producto Complementario
```php
$tipoComplementario = App\Models\Financiero\Lp\LpTipoProducto::where('codigo', 'complementario')->first();

$productoComp = App\Models\Financiero\Lp\LpProducto::create([
    'tipo_producto_id' => $tipoComplementario->id,
    'nombre' => 'Certificado de Estudios',
    'codigo' => 'CERT-001',
    'status' => 1
]);
```

### Crear Lista de Precios
```php
// Necesitas una población existente
$poblacion = App\Models\Configuracion\Poblacion::first();

$lista = App\Models\Financiero\Lp\LpListaPrecio::create([
    'nombre' => 'Lista de Precios 2025',
    'codigo' => 'LP-2025-001',
    'fecha_inicio' => '2025-01-01',
    'fecha_fin' => '2025-12-31',
    'status' => 1, // En proceso
    'descripcion' => 'Lista de precios de prueba'
]);

// Asociar población
$lista->poblaciones()->attach($poblacion->id);
```

### Probar Cálculo de Cuotas
```php
$precio = App\Models\Financiero\Lp\LpPrecioProducto::create([
    'lista_precio_id' => 1,
    'producto_id' => 1,
    'precio_contado' => 1000000,
    'precio_total' => 1200000,
    'matricula' => 200000,
    'numero_cuotas' => 10
]);

// El valor_cuota debería calcularse automáticamente
// (1200000 - 200000) / 10 = 100000, redondeado a 100000
echo $precio->valor_cuota; // Debería ser 100000
```

## 3. Pruebas de Servicio

```bash
php artisan tinker
```

```php
$service = app(App\Services\Financiero\LpPrecioProductoService::class);

// Probar redondeo
$service->redondearACien(5530); // Debería retornar 5500
$service->redondearACien(6580); // Debería retornar 6600

// Probar cálculo de cuota
$service->calcularCuota(1200000, 200000, 10); // Debería retornar 100000
```

## 4. Pruebas de Comando Programado

```bash
# Ejecutar comando manualmente
php artisan financiero:gestionar-listas-precios

# Verificar que active listas aprobadas y inactive listas vencidas
```

## 5. Pruebas de API (usando Postman, Insomnia o curl)

### Autenticación
Primero necesitas obtener un token de autenticación:

```bash
curl -X POST http://tu-dominio/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@ejemplo.com",
    "password": "password"
  }'
```

### Tipos de Producto

```bash
# Listar tipos de producto
curl -X GET http://tu-dominio/api/financiero/lp/tipos-producto \
  -H "Authorization: Bearer TU_TOKEN"

# Crear tipo de producto
curl -X POST http://tu-dominio/api/financiero/lp/tipos-producto \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Curso",
    "codigo": "CURSO",
    "es_financiable": true,
    "status": 1
  }'

# Mostrar tipo de producto
curl -X GET http://tu-dominio/api/financiero/lp/tipos-producto/1 \
  -H "Authorization: Bearer TU_TOKEN"

# Actualizar tipo de producto
curl -X PUT http://tu-dominio/api/financiero/lp/tipos-producto/1 \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Curso Actualizado",
    "codigo": "CURSO-UPD",
    "es_financiable": true,
    "status": 1
  }'

# Eliminar tipo de producto
curl -X DELETE http://tu-dominio/api/financiero/lp/tipos-producto/1 \
  -H "Authorization: Bearer TU_TOKEN"
```

### Productos

```bash
# Listar productos
curl -X GET http://tu-dominio/api/financiero/lp/productos \
  -H "Authorization: Bearer TU_TOKEN"

# Crear producto
curl -X POST http://tu-dominio/api/financiero/lp/productos \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_producto_id": 1,
    "nombre": "Producto Test",
    "codigo": "PROD-001",
    "referencia_id": 1,
    "referencia_tipo": "curso",
    "status": 1
  }'
```

### Listas de Precios

```bash
# Listar listas de precios
curl -X GET http://tu-dominio/api/financiero/lp/listas-precios \
  -H "Authorization: Bearer TU_TOKEN"

# Crear lista de precios
curl -X POST http://tu-dominio/api/financiero/lp/listas-precios \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Lista 2025",
    "codigo": "LP-2025",
    "fecha_inicio": "2025-01-01",
    "fecha_fin": "2025-12-31",
    "poblaciones": [1],
    "status": 1
  }'

# Aprobar lista de precios
curl -X POST http://tu-dominio/api/financiero/lp/listas-precios/1/aprobar \
  -H "Authorization: Bearer TU_TOKEN"

# Activar lista de precios
curl -X POST http://tu-dominio/api/financiero/lp/listas-precios/1/activar \
  -H "Authorization: Bearer TU_TOKEN"

# Inactivar lista de precios
curl -X POST http://tu-dominio/api/financiero/lp/listas-precios/1/inactivar \
  -H "Authorization: Bearer TU_TOKEN"
```

### Precios de Productos

```bash
# Listar precios de productos
curl -X GET http://tu-dominio/api/financiero/lp/precios-producto \
  -H "Authorization: Bearer TU_TOKEN"

# Crear precio de producto
curl -X POST http://tu-dominio/api/financiero/lp/precios-producto \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lista_precio_id": 1,
    "producto_id": 1,
    "precio_contado": 1000000,
    "precio_total": 1200000,
    "matricula": 200000,
    "numero_cuotas": 10
  }'

# Obtener precio vigente
curl -X GET "http://tu-dominio/api/financiero/lp/precios-producto/obtener-precio?producto_id=1&poblacion_id=1&fecha=2025-06-01" \
  -H "Authorization: Bearer TU_TOKEN"
```

## 6. Pruebas de Validaciones

### Validar Solapamiento de Vigencia
```bash
# Intentar crear dos listas con fechas solapadas para la misma población
# Debería fallar con error de validación
```

### Validar Matrícula Obligatoria
```bash
# Intentar crear precio sin matrícula para producto financiable
# Debería fallar con error de validación
```

### Validar Códigos Únicos
```bash
# Intentar crear dos tipos de producto con el mismo código
# Debería fallar con error de validación
```

## 7. Pruebas de Permisos

```bash
# Intentar acceder sin autenticación
# Debería retornar 401 Unauthorized

# Intentar acceder sin permisos apropiados
# Debería retornar 403 Forbidden
```

## Checklist de Verificación

- [ ] Todas las migraciones se ejecutan sin errores
- [ ] Los modelos se crean correctamente
- [ ] Las relaciones funcionan correctamente
- [ ] El cálculo de cuotas funciona con redondeo
- [ ] El comando programado funciona correctamente
- [ ] Todas las rutas API responden correctamente
- [ ] Las validaciones funcionan correctamente
- [ ] Los permisos están funcionando
- [ ] Los mensajes de error están en español
- [ ] Los recursos API devuelven la estructura correcta

