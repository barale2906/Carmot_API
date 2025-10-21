# Sistema de KPIs y Dashboards

## Descripción General

Este sistema permite crear, configurar y visualizar indicadores clave de rendimiento (KPIs) en dashboards personalizables. El sistema está diseñado para ser flexible y permitir la configuración de KPIs basados en cualquier modelo del sistema, incluyendo **relaciones entre campos** y **rangos de tiempo personalizados**.

## Arquitectura del Sistema

### Modelos Principales

1. **Kpi**: Define un indicador de rendimiento con su configuración y rango de tiempo
2. **KpiField**: Campos de configuración para el cálculo del KPI
3. **KpiFieldRelation**: Relaciones matemáticas entre dos campos de un KPI
4. **Dashboard**: Contenedor de tarjetas de visualización
5. **DashboardCard**: Tarjeta individual que muestra un KPI específico

### Servicios

1. **KpiService**: Calcula valores de KPIs basados en configuraciones (tradicional y con relaciones)
2. **KpiMetadataService**: Proporciona metadatos de modelos para configuración

### Controladores

1. **KpiController**: CRUD para KPIs
2. **KpiFieldController**: CRUD para campos de KPIs
3. **KpiFieldRelationController**: CRUD para relaciones entre campos
4. **DashboardController**: CRUD para dashboards
5. **DashboardCardController**: CRUD para tarjetas de dashboard
6. **KpiMetadataController**: Metadatos de modelos

## Flujo de Trabajo

### 1. Configuración de Modelos Permitidos

Primero, configura los modelos que pueden ser usados para KPIs en `config/kpis.php` usando **IDs numéricos**:

```php
'available_kpi_models' => [
    1 => [
        'class' => \App\Models\Academico\Grupo::class,
        'display_name' => 'Grupos por sede',
        'fields' => ['id', 'sede_id', 'inscritos', 'modulo_id', 'profesor_id', 'status', 'created_at', 'updated_at']
    ],
    2 => [
        'class' => \App\Models\Academico\Modulo::class,
        'display_name' => 'Modulos por sede',
        'fields' => ['id', 'sede_id', 'nombre', 'status', 'created_at', 'updated_at']
    ],
    // Agregar más modelos según necesidad
],
```

**Estructura de configuración:**
- **ID numérico**: Clave única para referenciar el modelo
- **class**: Clase completa del modelo Eloquent
- **display_name**: Nombre legible para mostrar en la interfaz
- **fields**: Array de campos permitidos para usar en KPIs

### 2. Crear un KPI

```php
// Crear KPI básico con rango de tiempo personalizado
$kpi = Kpi::create([
    'name' => 'Total de Grupos Activos',
    'code' => 'total_grupos_activos',
    'description' => 'Número total de grupos activos en el sistema',
    'unit' => 'grupos',
    'is_active' => true,
    'calculation_type' => 'custom_fields',
    'base_model' => 1, // ID del modelo en config/kpis.php (Grupo)
    'default_period_type' => 'monthly',
    'use_custom_time_range' => false,
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

### 2.1. Crear Relaciones entre Campos (Nuevo)

```php
// Crear relación entre dos campos para calcular porcentajes
$kpi->fieldRelations()->create([
    'field_a_id' => 1, // Campo A: Ventas
    'field_b_id' => 2, // Campo B: Visitas
    'operation' => 'percentage', // (A / B) * 100
    'multiplier' => 1.0,
    'is_active' => true,
    'order' => 1,
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

// Calcular usando rango personalizado
$value = $kpiService->getKpiValue(
    $kpi->id,
    $tenantId,
    $startDate,
    $endDate
);

// Calcular usando rango por defecto del KPI
$value = $kpiService->getKpiValueWithDefaultRange($kpi->id, $tenantId);
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

### Relaciones entre Campos (Nuevo)
- `GET /api/dashboards/kpis/{kpi}/field-relations` - Lista relaciones
- `POST /api/dashboards/kpis/{kpi}/field-relations` - Crear relación
- `GET /api/dashboards/kpis/{kpi}/field-relations/{relation}` - Obtener relación
- `PUT /api/dashboards/kpis/{kpi}/field-relations/{relation}` - Actualizar relación
- `DELETE /api/dashboards/kpis/{kpi}/field-relations/{relation}` - Eliminar relación
- `GET /api/dashboards/field-relations/operations` - Operaciones disponibles

### Metadatos
- `GET /api/dashboards/kpi-metadata/models` - Modelos disponibles
- `GET /api/dashboards/kpi-metadata/models/{modelId}/fields` - Campos del modelo

## Tipos de Operaciones Soportadas

### Operaciones de Cálculo
- `sum`: Suma de valores
- `count`: Conteo de registros
- `avg`: Promedio de valores
- `min`: Valor mínimo
- `max`: Valor máximo

### Operaciones de Filtrado
- `where`: Condición WHERE con operadores (=, >, <, LIKE, IN)

### Operaciones entre Campos (Nuevo)
- `divide`: División (Campo A ÷ Campo B)
- `multiply`: Multiplicación (Campo A × Campo B)
- `add`: Suma (Campo A + Campo B)
- `subtract`: Resta (Campo A - Campo B)
- `percentage`: Porcentaje ((Campo A ÷ Campo B) × 100)

### Tipos de Campos
- `numeric`: Campos numéricos
- `string`: Campos de texto
- `date`: Campos de fecha
- `boolean`: Campos booleanos

### Tipos de Periodo (Nuevo)
- `daily`: Diario
- `weekly`: Semanal
- `monthly`: Mensual
- `yearly`: Anual
- `custom`: Personalizado con fechas específicas

## Nuevas Funcionalidades

### 1. Rangos de Tiempo Personalizados
Los KPIs ahora pueden tener su propio rango de tiempo por defecto:
- **Tipos predefinidos**: daily, weekly, monthly, yearly
- **Rangos personalizados**: Fechas específicas de inicio y fin
- **Cálculo automático**: Si no se especifican fechas, usa el rango por defecto

### 2. Relaciones entre Campos
Permite crear operaciones matemáticas entre dos campos:
- **Máximo 1 relación por KPI**
- **Soporte para diferentes modelos**: Los campos pueden ser de modelos diferentes
- **Operaciones matemáticas**: divide, multiply, add, subtract, percentage
- **Condiciones adicionales**: Filtros específicos por campo

### 3. Configuración Centralizada
- **IDs numéricos**: Los modelos se referencian por ID en lugar de clase completa
- **Validación automática**: El middleware `ValidateKpiSecurity` valida que el ID existe en la configuración
- **Información completa**: La API devuelve configuración completa del modelo incluyendo campos permitidos
- **Seguridad**: Solo se pueden usar modelos y campos predefinidos en la configuración

## Consideraciones de Seguridad

1. **Autenticación**: Todas las rutas requieren autenticación
2. **Middleware de Validación**: `ValidateKpiSecurity` valida que:
   - Los modelos base estén en la lista permitida (por ID)
   - Los campos usados estén en la lista de campos permitidos del modelo
3. **Permisos**: Los dashboards están asociados a usuarios específicos
4. **Soft Deletes**: Todos los modelos usan eliminación suave
5. **Límites de relaciones**: Máximo 1 relación por KPI
6. **Validación de campos**: Solo campos permitidos en la configuración
7. **Configuración centralizada**: Solo modelos y campos predefinidos pueden ser usados

## Extensibilidad

El sistema está diseñado para ser extensible:

1. **Nuevos Modelos**: Agregar modelos a `config/kpis.php` con IDs únicos y sus campos permitidos
2. **Nuevas Operaciones**: Extender `KpiService` con nuevas operaciones
3. **Nuevos Tipos de Campo**: Agregar validaciones en los controladores
4. **Nuevas Operaciones entre Campos**: Agregar en `KpiFieldRelation::getAvailableOperations()`
5. **Exportación**: Implementar `exportPdf` en `DashboardController`
6. **Middleware de Seguridad**: El middleware se actualiza automáticamente con nuevos modelos de la configuración

## Ejemplos de Uso

### KPI de Grupos Totales (Tradicional)
```php
$kpi = Kpi::create([
    'name' => 'Total de Grupos',
    'code' => 'total_grupos',
    'base_model' => 1, // ID del modelo Grupo en config/kpis.php
    'default_period_type' => 'monthly',
]);

$kpi->kpiFields()->create([
    'field_name' => 'id',
    'operation' => 'count',
    'field_type' => 'numeric',
]);
```

### KPI de Promedio de Inscritos por Grupo (Con Relación)
```php
$kpi = Kpi::create([
    'name' => 'Promedio de Inscritos por Grupo',
    'code' => 'promedio_inscritos_grupo',
    'base_model' => 1, // ID del modelo Grupo
    'unit' => 'inscritos',
]);

// Campo A: Total de Inscritos
$kpi->kpiFields()->create([
    'field_name' => 'inscritos',
    'display_name' => 'Total Inscritos',
    'field_type' => 'numeric',
    'operation' => 'sum',
]);

// Campo B: Conteo de Grupos
$kpi->kpiFields()->create([
    'field_name' => 'id',
    'display_name' => 'Total Grupos',
    'field_type' => 'numeric',
    'operation' => 'count',
]);

// Relación: (Total Inscritos / Total Grupos)
$kpi->fieldRelations()->create([
    'field_a_id' => 1, // Total Inscritos
    'field_b_id' => 2, // Total Grupos
    'operation' => 'divide',
    'multiplier' => 1.0,
]);
```

### KPI con Rango de Tiempo Personalizado
```php
$kpi = Kpi::create([
    'name' => 'Grupos del Año 2024',
    'code' => 'grupos_2024',
    'base_model' => 1, // ID del modelo Grupo
    'use_custom_time_range' => true,
    'default_period_start_date' => '2024-01-01',
    'default_period_end_date' => '2024-12-31',
]);
```

### KPI de Diferencia de Inscritos (Con Resta)
```php
$kpi = Kpi::create([
    'name' => 'Diferencia de Inscritos',
    'code' => 'diferencia_inscritos',
    'base_model' => 1, // ID del modelo Grupo
]);

// Campo A: Inscritos Actuales
$kpi->kpiFields()->create([
    'field_name' => 'inscritos',
    'display_name' => 'Inscritos Actuales',
    'field_type' => 'numeric',
    'operation' => 'sum',
]);

// Campo B: Capacidad Máxima (ejemplo: 30 por grupo)
$kpi->kpiFields()->create([
    'field_name' => 'id',
    'display_name' => 'Capacidad Total',
    'field_type' => 'numeric',
    'operation' => 'count',
    'value' => '30', // 30 estudiantes por grupo
]);

// Relación: Inscritos - Capacidad
$kpi->fieldRelations()->create([
    'field_a_id' => 1, // Inscritos
    'field_b_id' => 2, // Capacidad
    'operation' => 'subtract',
    'multiplier' => 1.0,
]);
```

## Respuesta de la API

### KPI con Información Completa
```json
{
    "id": 1,
    "name": "Promedio de Inscritos por Grupo",
    "base_model": 1,
    "base_model_config": {
        "class": "App\\Models\\Academico\\Grupo",
        "display_name": "Grupos por sede",
        "fields": ["id", "sede_id", "inscritos", "modulo_id", "profesor_id", "status", "created_at", "updated_at"]
    },
    "base_model_display_name": "Grupos por sede",
    "base_model_fields": ["id", "sede_id", "inscritos", "modulo_id", "profesor_id", "status", "created_at", "updated_at"],
    "default_period_type": "monthly",
    "has_time_range": true,
    "has_field_relations": true,
    "field_relations": [
        {
            "id": 1,
            "operation": "divide",
            "operation_display": "División (A ÷ B)",
            "field_a": {
                "field_name": "inscritos",
                "display_name": "Total Inscritos"
            },
            "field_b": {
                "field_name": "id", 
                "display_name": "Total Grupos"
            }
        }
    ]
}
```

Este sistema proporciona una base sólida y flexible para la creación de dashboards con KPIs personalizables, incluyendo **relaciones matemáticas entre campos** y **rangos de tiempo personalizados**.
