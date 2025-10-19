# Sistema de KPIs y Dashboards

## Descripción General

Este sistema permite crear, configurar y visualizar indicadores clave de rendimiento (KPIs) en dashboards personalizables. El sistema está diseñado para ser flexible y permitir la configuración de KPIs basados en cualquier modelo del sistema.

## Arquitectura del Sistema

### Modelos Principales

1. **Kpi**: Define un indicador de rendimiento con su configuración
2. **KpiField**: Campos de configuración para el cálculo del KPI
3. **Dashboard**: Contenedor de tarjetas de visualización
4. **DashboardCard**: Tarjeta individual que muestra un KPI específico

### Servicios

1. **KpiService**: Calcula valores de KPIs basados en configuraciones
2. **KpiMetadataService**: Proporciona metadatos de modelos para configuración

### Controladores

1. **KpiController**: CRUD para KPIs
2. **KpiFieldController**: CRUD para campos de KPIs
3. **DashboardController**: CRUD para dashboards
4. **DashboardCardController**: CRUD para tarjetas de dashboard
5. **KpiMetadataController**: Metadatos de modelos

## Flujo de Trabajo

### 1. Configuración de Modelos Permitidos

Primero, configura los modelos que pueden ser usados para KPIs en `config/kpis.php`:

```php
'available_kpi_models' => [
    \App\Models\Academico\Grupo::class => [
        'display_name' => 'Grupos por sede',
        'fields' => ['id', 'sede_id', 'inscritos', 'modulo_id', 'profesor_id', 'status', 'created_at', 'updated_at']
    ],
    // Agregar más modelos según necesidad
],
```

### 2. Crear un KPI

```php
// Crear KPI básico
$kpi = Kpi::create([
    'name' => 'Total de Grupos Activos',
    'code' => 'total_grupos_activos',
    'description' => 'Número total de grupos activos en el sistema',
    'unit' => 'grupos',
    'is_active' => true,
    'calculation_type' => 'custom_fields',
    'base_model' => \App\Models\Academico\Grupo::class,
]);

// Agregar campos de configuración
$kpi->kpiFields()->create([
    'field_name' => 'status',
    'display_name' => 'Estado',
    'field_type' => 'numeric',
    'operation' => 'where',
    'operator' => '=',
    'value' => '1', // 1 = activo
    'is_required' => true,
    'order' => 1,
]);

$kpi->kpiFields()->create([
    'field_name' => 'id',
    'display_name' => 'ID',
    'field_type' => 'numeric',
    'operation' => 'count',
    'is_required' => true,
    'order' => 2,
]);
```

### 3. Crear un Dashboard

```php
$dashboard = Dashboard::create([
    'user_id' => auth()->id(),
    'tenant_id' => 1, // opcional
    'name' => 'Dashboard Académico',
    'is_default' => true,
]);
```

### 4. Agregar Tarjetas al Dashboard

```php
$card = DashboardCard::create([
    'dashboard_id' => $dashboard->id,
    'kpi_id' => $kpi->id,
    'title' => 'Grupos Activos',
    'background_color' => '#3498db',
    'text_color' => '#ffffff',
    'width' => 2,
    'height' => 1,
    'x_position' => 0,
    'y_position' => 0,
    'period_type' => 'monthly',
    'period_start_date' => now()->subMonth(),
    'period_end_date' => now(),
    'order' => 1,
]);
```

### 5. Calcular Valores de KPIs

```php
$kpiService = new KpiService();

$value = $kpiService->getKpiValue(
    $kpi->id,
    $tenantId,
    $startDate,
    $endDate
);
```

## API Endpoints

### KPIs
- `GET /api/dashboards/kpis` - Lista KPIs
- `POST /api/dashboards/kpis` - Crear KPI
- `GET /api/dashboards/kpis/{id}` - Obtener KPI
- `PUT /api/dashboards/kpis/{id}` - Actualizar KPI
- `DELETE /api/dashboards/kpis/{id}` - Eliminar KPI

### Campos de KPI
- `GET /api/dashboards/kpi-fields` - Lista campos
- `POST /api/dashboards/kpi-fields` - Crear campo
- `GET /api/dashboards/kpi-fields/{id}` - Obtener campo
- `PUT /api/dashboards/kpi-fields/{id}` - Actualizar campo
- `DELETE /api/dashboards/kpi-fields/{id}` - Eliminar campo

### Dashboards
- `GET /api/dashboards/dashboards` - Lista dashboards
- `POST /api/dashboards/dashboards` - Crear dashboard
- `GET /api/dashboards/dashboards/{id}` - Obtener dashboard
- `PUT /api/dashboards/dashboards/{id}` - Actualizar dashboard
- `DELETE /api/dashboards/dashboards/{id}` - Eliminar dashboard

### Tarjetas de Dashboard
- `GET /api/dashboards/dashboard-cards` - Lista tarjetas
- `POST /api/dashboards/dashboard-cards` - Crear tarjeta
- `GET /api/dashboards/dashboard-cards/{id}` - Obtener tarjeta
- `PUT /api/dashboards/dashboard-cards/{id}` - Actualizar tarjeta
- `DELETE /api/dashboards/dashboard-cards/{id}` - Eliminar tarjeta

### Metadatos
- `GET /api/dashboards/kpi-metadata/models` - Modelos disponibles
- `GET /api/dashboards/kpi-metadata/models/{modelClass}/fields` - Campos del modelo

## Tipos de Operaciones Soportadas

### Operaciones de Cálculo
- `sum`: Suma de valores
- `count`: Conteo de registros
- `avg`: Promedio de valores
- `min`: Valor mínimo
- `max`: Valor máximo

### Operaciones de Filtrado
- `where`: Condición WHERE con operadores (=, >, <, LIKE, IN)

### Tipos de Campos
- `numeric`: Campos numéricos
- `string`: Campos de texto
- `date`: Campos de fecha
- `boolean`: Campos booleanos

## Consideraciones de Seguridad

1. **Autenticación**: Todas las rutas requieren autenticación
2. **Validación**: Se valida que los modelos estén en la lista permitida
3. **Permisos**: Los dashboards están asociados a usuarios específicos
4. **Soft Deletes**: Todos los modelos usan eliminación suave

## Extensibilidad

El sistema está diseñado para ser extensible:

1. **Nuevos Modelos**: Agregar modelos a `config/kpis.php`
2. **Nuevas Operaciones**: Extender `KpiService` con nuevas operaciones
3. **Nuevos Tipos de Campo**: Agregar validaciones en los controladores
4. **Exportación**: Implementar `exportPdf` en `DashboardController`

## Ejemplos de Uso

### KPI de Ventas Totales
```php
$kpi = Kpi::create([
    'name' => 'Ventas Totales',
    'code' => 'total_ventas',
    'base_model' => \App\Models\Venta::class,
]);

$kpi->kpiFields()->create([
    'field_name' => 'monto',
    'operation' => 'sum',
    'field_type' => 'numeric',
]);
```

### KPI de Nuevos Clientes
```php
$kpi = Kpi::create([
    'name' => 'Nuevos Clientes',
    'code' => 'nuevos_clientes',
    'base_model' => \App\Models\Cliente::class,
]);

$kpi->kpiFields()->create([
    'field_name' => 'id',
    'operation' => 'count',
    'field_type' => 'numeric',
]);
```

Este sistema proporciona una base sólida y flexible para la creación de dashboards con KPIs personalizables.
