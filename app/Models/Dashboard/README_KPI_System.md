# Sistema de KPIs y Dashboards - Guía para Desarrolladores Frontend

## Descripción General

Este sistema permite crear, configurar y visualizar indicadores clave de rendimiento (KPIs) en dashboards personalizables con **gráficos dinámicos** y **filtros avanzados**. El sistema está diseñado para ser flexible y permitir la configuración de KPIs basados en cualquier modelo del sistema, incluyendo **relaciones entre campos**, **rangos de tiempo personalizados**, y **gráficos interactivos**.

## Arquitectura del Sistema

### Modelos Principales

1. **Kpi**: Define un indicador de rendimiento con su configuración y rango de tiempo
2. **KpiField**: Campos de configuración para el cálculo del KPI
3. **KpiFieldRelation**: Relaciones matemáticas entre dos campos de un KPI
4. **Dashboard**: Contenedor de tarjetas de visualización
5. **DashboardCard**: Tarjeta individual que muestra un KPI específico con configuración de gráficos

### Servicios

1. **KpiService**: Calcula valores de KPIs basados en configuraciones
2. **KpiMetadataService**: Proporciona metadatos de modelos para configuración
3. **ChartDataService**: Genera datos para gráficos con filtros dinámicos
4. **DynamicFilterService**: Aplica filtros dinámicos a consultas

### Controladores

1. **KpiController**: CRUD para KPIs
2. **KpiFieldController**: CRUD para campos de KPIs
3. **KpiFieldRelationController**: CRUD para relaciones entre campos
4. **DashboardController**: CRUD para dashboards
5. **DashboardCardController**: CRUD para tarjetas de dashboard
6. **ChartDataController**: Datos y metadatos para gráficos
7. **KpiMetadataController**: Metadatos de modelos

## Flujo de Trabajo para Frontend

### 1. Configuración Inicial

#### Obtener Modelos Disponibles
```javascript
// GET /api/dashboard/kpi-metadata/models
const models = await fetch('/api/dashboard/kpi-metadata/models', {
  headers: { 'Authorization': 'Bearer ' + token }
});
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "display_name": "Grupos por sede",
      "class": "App\\Models\\Academico\\Grupo",
      "fields": ["id", "sede_id", "inscritos", "modulo_id", "profesor_id", "status", "created_at", "updated_at"]
    }
  ]
}
```

#### Obtener Campos de un Modelo
```javascript
// GET /api/dashboard/kpi-metadata/models/{modelId}/fields
const fields = await fetch(`/api/dashboard/kpi-metadata/models/${modelId}/fields`, {
  headers: { 'Authorization': 'Bearer ' + token }
});
```

### 2. Crear un KPI

#### Paso 1: Crear KPI Básico
```javascript
const kpiData = {
  name: "Total de Grupos Activos",
  code: "total_grupos_activos",
  description: "Número total de grupos activos en el sistema",
  unit: "grupos",
  is_active: true,
  calculation_type: "custom_fields",
  base_model: 1, // ID del modelo en config/kpis.php
  default_period_type: "monthly",
  use_custom_time_range: false
};

const kpi = await fetch('/api/dashboard/kpis', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify(kpiData)
});
```

#### Paso 2: Agregar Campos al KPI
```javascript
// Campo de filtro
const filterField = {
  kpi_id: kpi.id,
  field_name: "status",
  display_name: "Estado",
  field_type: "numeric",
  operation: "where",
  operator: "=",
  value: "1", // 1 = activo
  is_required: true,
  order: 1
};

// Campo de cálculo
const calculationField = {
  kpi_id: kpi.id,
  field_name: "id",
  display_name: "ID",
  field_type: "numeric",
  operation: "count",
  is_required: true,
  order: 2
};

await fetch('/api/dashboard/kpi-fields', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify(filterField)
});

await fetch('/api/dashboard/kpi-fields', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify(calculationField)
});
```

### 3. Crear Dashboard con Gráficos

#### Paso 1: Crear Dashboard
```javascript
const dashboardData = {
  user_id: userId,
  tenant_id: 1,
  name: "Dashboard Académico",
  is_default: true
};

const dashboard = await fetch('/api/dashboard/dashboards', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify(dashboardData)
});
```

#### Paso 2: Crear Tarjeta con Gráfico
```javascript
const cardData = {
  dashboard_id: dashboard.id,
  kpi_id: kpi.id,
  title: "Grupos Activos por Sede",
  chart_type: "pie", // Tipos: bar, pie, line, area, scatter
  chart_parameters: {
    show_percentages: true,
    show_legend: true,
    legend_position: "bottom",
    donut: false
  },
  group_by: "sede_id", // Campo por el cual agrupar
  filters: [
    {
      field: "status",
      type: "exact",
      value: "1",
      operator: "="
    }
  ],
  position: {
    x: 0,
    y: 0,
    w: 2,
    h: 1
  }
};

const card = await fetch('/api/dashboard/dashboard-cards', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify(cardData)
});
```

### 4. Obtener Datos para Gráficos

#### Obtener Datos de una Tarjeta
```javascript
// GET /api/dashboard/dashboard-cards/{cardId}/chart-data
const chartData = await fetch(`/api/dashboard/dashboard-cards/${cardId}/chart-data`, {
  headers: { 'Authorization': 'Bearer ' + token }
});
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "kpi_id": 1,
    "name": "Total de Grupos Activos",
    "chart_type": "pie",
    "data": [
      {
        "label": "Sede Principal",
        "value": 15,
        "percentage": 60
      },
      {
        "label": "Sede Norte",
        "value": 10,
        "percentage": 40
      }
    ],
    "total": 25,
    "unit": "grupos",
    "period": "2024-01-20"
  }
}
```

#### Obtener Datos con Filtros Dinámicos
```javascript
// GET /api/dashboard/kpis/{kpiId}/chart-data?group_by=sede_id&chart_type=bar&filters=[{"field":"status","type":"exact","value":"1"}]
const dynamicData = await fetch(`/api/dashboard/kpis/${kpiId}/chart-data?group_by=sede_id&chart_type=bar&filters=${encodeURIComponent(JSON.stringify(filters))}`, {
  headers: { 'Authorization': 'Bearer ' + token }
});
```

## API Endpoints Completos

### KPIs
- `GET /api/dashboard/kpis` - Lista KPIs
- `POST /api/dashboard/kpis` - Crear KPI
- `GET /api/dashboard/kpis/{id}` - Obtener KPI
- `PUT /api/dashboard/kpis/{id}` - Actualizar KPI
- `DELETE /api/dashboard/kpis/{id}` - Eliminar KPI

### Campos de KPI
- `GET /api/dashboard/kpi-fields` - Lista campos
- `POST /api/dashboard/kpi-fields` - Crear campo
- `GET /api/dashboard/kpi-fields/{id}` - Obtener campo
- `PUT /api/dashboard/kpi-fields/{id}` - Actualizar campo
- `DELETE /api/dashboard/kpi-fields/{id}` - Eliminar campo

### Relaciones entre Campos
- `GET /api/dashboard/kpis/{kpi}/field-relations` - Lista relaciones
- `POST /api/dashboard/kpis/{kpi}/field-relations` - Crear relación
- `GET /api/dashboard/kpis/{kpi}/field-relations/{relation}` - Obtener relación
- `PUT /api/dashboard/kpis/{kpi}/field-relations/{relation}` - Actualizar relación
- `DELETE /api/dashboard/kpis/{kpi}/field-relations/{relation}` - Eliminar relación
- `GET /api/dashboard/field-relations/operations` - Operaciones disponibles

### Dashboards
- `GET /api/dashboard/dashboards` - Lista dashboards
- `POST /api/dashboard/dashboards` - Crear dashboard
- `GET /api/dashboard/dashboards/{id}` - Obtener dashboard
- `PUT /api/dashboard/dashboards/{id}` - Actualizar dashboard
- `DELETE /api/dashboard/dashboards/{id}` - Eliminar dashboard

### Tarjetas de Dashboard
- `GET /api/dashboard/dashboard-cards` - Lista tarjetas
- `POST /api/dashboard/dashboard-cards` - Crear tarjeta
- `GET /api/dashboard/dashboard-cards/{id}` - Obtener tarjeta
- `PUT /api/dashboard/dashboard-cards/{id}` - Actualizar tarjeta
- `DELETE /api/dashboard/dashboard-cards/{id}` - Eliminar tarjeta

### Datos de Gráficos
- `GET /api/dashboard/kpis/{kpi}/chart-data` - Datos de gráfico para KPI
- `GET /api/dashboard/kpis/{kpi}/chart-statistics` - Estadísticas del KPI
- `GET /api/dashboard/dashboard-cards/{card}/chart-data` - Datos de gráfico para tarjeta
- `GET /api/dashboard/chart-types/{chartType}/parameters` - Parámetros del tipo de gráfico
- `GET /api/dashboard/models/{modelId}/group-by-fields` - Campos disponibles para agrupar
- `GET /api/dashboard/filter-types` - Tipos de filtro disponibles

### Metadatos
- `GET /api/dashboard/kpi-metadata/models` - Modelos disponibles
- `GET /api/dashboard/kpi-metadata/models/{modelId}/fields` - Campos del modelo

## Valores para Selects y Formularios

### Tipos de Gráfico
```javascript
const chartTypes = [
  { value: 'bar', label: 'Gráfico de Barras' },
  { value: 'pie', label: 'Gráfico de Torta' },
  { value: 'line', label: 'Gráfico de Líneas' },
  { value: 'area', label: 'Gráfico de Área' },
  { value: 'scatter', label: 'Gráfico de Dispersión' }
];
```

### Tipos de Período
```javascript
const periodTypes = [
  { value: 'daily', label: 'Diario' },
  { value: 'weekly', label: 'Semanal' },
  { value: 'monthly', label: 'Mensual' },
  { value: 'yearly', label: 'Anual' },
  { value: 'custom', label: 'Personalizado' }
];
```

### Operaciones de Campo
```javascript
const fieldOperations = [
  { value: 'sum', label: 'Suma' },
  { value: 'count', label: 'Conteo' },
  { value: 'avg', label: 'Promedio' },
  { value: 'min', label: 'Mínimo' },
  { value: 'max', label: 'Máximo' },
  { value: 'where', label: 'Filtro' }
];
```

### Operadores de Filtro
```javascript
const operators = [
  { value: '=', label: 'Igual a' },
  { value: '!=', label: 'Diferente de' },
  { value: '>', label: 'Mayor que' },
  { value: '<', label: 'Menor que' },
  { value: '>=', label: 'Mayor o igual que' },
  { value: '<=', label: 'Menor o igual que' },
  { value: 'LIKE', label: 'Contiene' },
  { value: 'IN', label: 'En la lista' }
];
```

### Tipos de Campo
```javascript
const fieldTypes = [
  { value: 'numeric', label: 'Numérico' },
  { value: 'string', label: 'Texto' },
  { value: 'date', label: 'Fecha' },
  { value: 'boolean', label: 'Booleano' }
];
```

### Tipos de Filtro
```javascript
const filterTypes = [
  { value: 'exact', label: 'Valor Exacto' },
  { value: 'in', label: 'En la Lista' },
  { value: 'date_range', label: 'Rango de Fechas' },
  { value: 'text', label: 'Contiene Texto' },
  { value: 'multiple', label: 'Múltiples Condiciones' }
];
```

### Operaciones entre Campos
```javascript
const fieldRelations = [
  { value: 'divide', label: 'División (A ÷ B)' },
  { value: 'multiply', label: 'Multiplicación (A × B)' },
  { value: 'add', label: 'Suma (A + B)' },
  { value: 'subtract', label: 'Resta (A - B)' },
  { value: 'percentage', label: 'Porcentaje ((A ÷ B) × 100)' }
];
```

## Parámetros de Gráficos por Tipo

### Gráfico de Barras
```javascript
const barParameters = [
  { name: 'orientation', type: 'select', required: true, options: ['vertical', 'horizontal'], default: 'vertical' },
  { name: 'stacked', type: 'boolean', required: false, default: false },
  { name: 'show_values', type: 'boolean', required: false, default: true },
  { name: 'color_scheme', type: 'select', required: false, options: ['default', 'custom', 'gradient'], default: 'default' }
];
```

### Gráfico de Torta
```javascript
const pieParameters = [
  { name: 'show_percentages', type: 'boolean', required: false, default: true },
  { name: 'show_legend', type: 'boolean', required: false, default: true },
  { name: 'legend_position', type: 'select', required: false, options: ['top', 'bottom', 'left', 'right'], default: 'bottom' },
  { name: 'donut', type: 'boolean', required: false, default: false },
  { name: 'donut_size', type: 'numeric', required: false, min: 0, max: 1, default: 0.5 }
];
```

### Gráfico de Líneas
```javascript
const lineParameters = [
  { name: 'smooth', type: 'boolean', required: false, default: false },
  { name: 'show_points', type: 'boolean', required: false, default: true },
  { name: 'fill_area', type: 'boolean', required: false, default: false },
  { name: 'show_grid', type: 'boolean', required: false, default: true },
  { name: 'y_axis_min', type: 'numeric', required: false, default: null },
  { name: 'y_axis_max', type: 'numeric', required: false, default: null }
];
```

## Tips para el Desarrollo Frontend

### 1. Estructura de Componentes Recomendada

```
src/
├── components/
│   ├── KpiBuilder/
│   │   ├── KpiForm.jsx
│   │   ├── FieldConfig.jsx
│   │   └── RelationConfig.jsx
│   ├── Dashboard/
│   │   ├── DashboardGrid.jsx
│   │   ├── DashboardCard.jsx
│   │   └── ChartRenderer.jsx
│   └── Filters/
│       ├── FilterBuilder.jsx
│       └── FilterInput.jsx
├── services/
│   ├── api.js
│   └── chartData.js
└── utils/
    ├── chartConfig.js
    └── validation.js
```

### 2. Manejo de Estado

```javascript
// Usar Context API o Redux para manejar el estado global
const DashboardContext = createContext();

// Estado para KPIs
const [kpis, setKpis] = useState([]);
const [selectedKpi, setSelectedKpi] = useState(null);

// Estado para Dashboards
const [dashboards, setDashboards] = useState([]);
const [currentDashboard, setCurrentDashboard] = useState(null);

// Estado para Gráficos
const [chartData, setChartData] = useState({});
const [chartConfig, setChartConfig] = useState({});
```

### 3. Componente de Constructor de KPIs

#### React
```jsx
const KpiBuilder = () => {
  const [step, setStep] = useState(1);
  const [kpiData, setKpiData] = useState({
    name: '',
    code: '',
    description: '',
    unit: '',
    base_model: null,
    default_period_type: 'monthly'
  });

  const handleNext = () => {
    if (step === 1 && validateBasicInfo()) {
      setStep(2);
    } else if (step === 2 && validateFields()) {
      setStep(3);
    }
  };

  return (
    <div className="kpi-builder">
      {step === 1 && <BasicInfoForm data={kpiData} onChange={setKpiData} />}
      {step === 2 && <FieldConfigForm kpiId={kpiData.id} />}
      {step === 3 && <RelationConfigForm kpiId={kpiData.id} />}
      
      <div className="builder-actions">
        <button onClick={handlePrevious} disabled={step === 1}>
          Anterior
        </button>
        <button onClick={handleNext}>
          {step === 3 ? 'Finalizar' : 'Siguiente'}
        </button>
      </div>
    </div>
  );
};
```

#### Vue.js
```vue
<template>
  <div class="kpi-builder">
    <div v-if="step === 1">
      <BasicInfoForm 
        :data="kpiData" 
        @update:data="updateKpiData" 
      />
    </div>
    <div v-if="step === 2">
      <FieldConfigForm 
        :kpi-id="kpiData.id" 
      />
    </div>
    <div v-if="step === 3">
      <RelationConfigForm 
        :kpi-id="kpiData.id" 
      />
    </div>
    
    <div class="builder-actions">
      <button 
        @click="handlePrevious" 
        :disabled="step === 1"
      >
        Anterior
      </button>
      <button @click="handleNext">
        {{ step === 3 ? 'Finalizar' : 'Siguiente' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import BasicInfoForm from './BasicInfoForm.vue'
import FieldConfigForm from './FieldConfigForm.vue'
import RelationConfigForm from './RelationConfigForm.vue'

const step = ref(1)
const kpiData = reactive({
  name: '',
  code: '',
  description: '',
  unit: '',
  base_model: null,
  default_period_type: 'monthly'
})

const updateKpiData = (newData) => {
  Object.assign(kpiData, newData)
}

const handleNext = () => {
  if (step.value === 1 && validateBasicInfo()) {
    step.value = 2
  } else if (step.value === 2 && validateFields()) {
    step.value = 3
  }
}

const handlePrevious = () => {
  if (step.value > 1) {
    step.value--
  }
}
</script>
```

### 4. Componente de Configuración de Gráficos

#### React
```jsx
const ChartConfigForm = ({ cardData, onChange }) => {
  const [chartType, setChartType] = useState(cardData.chart_type || 'bar');
  const [parameters, setParameters] = useState(cardData.chart_parameters || {});
  const [groupBy, setGroupBy] = useState(cardData.group_by || '');
  const [filters, setFilters] = useState(cardData.filters || []);

  const handleChartTypeChange = (newType) => {
    setChartType(newType);
    // Resetear parámetros al cambiar tipo
    setParameters(getDefaultParameters(newType));
  };

  const addFilter = () => {
    setFilters([...filters, {
      field: '',
      type: 'exact',
      value: '',
      operator: '='
    }]);
  };

  return (
    <div className="chart-config">
      <div className="form-group">
        <label>Tipo de Gráfico</label>
        <select value={chartType} onChange={(e) => handleChartTypeChange(e.target.value)}>
          {chartTypes.map(type => (
            <option key={type.value} value={type.value}>
              {type.label}
            </option>
          ))}
        </select>
      </div>

      <div className="form-group">
        <label>Agrupar por</label>
        <select value={groupBy} onChange={(e) => setGroupBy(e.target.value)}>
          <option value="">Seleccionar campo...</option>
          {availableFields.map(field => (
            <option key={field.name} value={field.name}>
              {field.display_name}
            </option>
          ))}
        </select>
      </div>

      <div className="filters-section">
        <h3>Filtros</h3>
        {filters.map((filter, index) => (
          <FilterInput
            key={index}
            filter={filter}
            onChange={(newFilter) => updateFilter(index, newFilter)}
            onRemove={() => removeFilter(index)}
          />
        ))}
        <button onClick={addFilter}>Agregar Filtro</button>
      </div>

      <ChartParametersForm
        chartType={chartType}
        parameters={parameters}
        onChange={setParameters}
      />
    </div>
  );
};
```

#### Vue.js
```vue
<template>
  <div class="chart-config">
    <div class="form-group">
      <label>Tipo de Gráfico</label>
      <select v-model="chartType" @change="handleChartTypeChange">
        <option 
          v-for="type in chartTypes" 
          :key="type.value" 
          :value="type.value"
        >
          {{ type.label }}
        </option>
      </select>
    </div>

    <div class="form-group">
      <label>Agrupar por</label>
      <select v-model="groupBy">
        <option value="">Seleccionar campo...</option>
        <option 
          v-for="field in availableFields" 
          :key="field.name" 
          :value="field.name"
        >
          {{ field.display_name }}
        </option>
      </select>
    </div>

    <div class="filters-section">
      <h3>Filtros</h3>
      <FilterInput
        v-for="(filter, index) in filters"
        :key="index"
        :filter="filter"
        @update:filter="(newFilter) => updateFilter(index, newFilter)"
        @remove="removeFilter(index)"
      />
      <button @click="addFilter">Agregar Filtro</button>
    </div>

    <ChartParametersForm
      :chart-type="chartType"
      :parameters="parameters"
      @update:parameters="setParameters"
    />
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import FilterInput from './FilterInput.vue'
import ChartParametersForm from './ChartParametersForm.vue'

const props = defineProps({
  cardData: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:cardData'])

const chartType = ref(props.cardData.chart_type || 'bar')
const parameters = reactive(props.cardData.chart_parameters || {})
const groupBy = ref(props.cardData.group_by || '')
const filters = reactive(props.cardData.filters || [])

const handleChartTypeChange = (newType) => {
  chartType.value = newType
  // Resetear parámetros al cambiar tipo
  Object.assign(parameters, getDefaultParameters(newType))
}

const addFilter = () => {
  filters.push({
    field: '',
    type: 'exact',
    value: '',
    operator: '='
  })
}

const updateFilter = (index, newFilter) => {
  filters[index] = newFilter
}

const removeFilter = (index) => {
  filters.splice(index, 1)
}

// Watchers para emitir cambios
watch([chartType, groupBy, filters, parameters], () => {
  emit('update:cardData', {
    chart_type: chartType.value,
    chart_parameters: parameters,
    group_by: groupBy.value,
    filters: filters
  })
}, { deep: true })
</script>
```

### 5. Componente de Renderizado de Gráficos

#### React
```jsx
const ChartRenderer = ({ cardId, chartType, data }) => {
  const chartRef = useRef(null);

  useEffect(() => {
    if (data && chartRef.current) {
      renderChart(chartRef.current, chartType, data);
    }
  }, [data, chartType]);

  return (
    <div className="chart-container">
      <div ref={chartRef} className="chart-canvas"></div>
    </div>
  );
};

const renderChart = (container, type, data) => {
  switch (type) {
    case 'bar':
      renderBarChart(container, data);
      break;
    case 'pie':
      renderPieChart(container, data);
      break;
    case 'line':
      renderLineChart(container, data);
      break;
    case 'area':
      renderAreaChart(container, data);
      break;
    case 'scatter':
      renderScatterChart(container, data);
      break;
  }
};
```

#### Vue.js
```vue
<template>
  <div class="chart-container">
    <div ref="chartRef" class="chart-canvas"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  cardId: {
    type: [String, Number],
    required: true
  },
  chartType: {
    type: String,
    required: true
  },
  data: {
    type: Object,
    default: () => ({})
  }
})

const chartRef = ref(null)

const renderChart = (container, type, data) => {
  switch (type) {
    case 'bar':
      renderBarChart(container, data)
      break
    case 'pie':
      renderPieChart(container, data)
      break
    case 'line':
      renderLineChart(container, data)
      break
    case 'area':
      renderAreaChart(container, data)
      break
    case 'scatter':
      renderScatterChart(container, data)
      break
  }
}

onMounted(() => {
  if (props.data && chartRef.value) {
    renderChart(chartRef.value, props.chartType, props.data)
  }
})

watch([() => props.data, () => props.chartType], () => {
  if (props.data && chartRef.value) {
    renderChart(chartRef.value, props.chartType, props.data)
  }
}, { deep: true })
</script>
```

### 6. Manejo de Errores y Loading

#### React
```jsx
const useApiCall = (url, options = {}) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const execute = async () => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await fetch(url, {
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Content-Type': 'application/json',
          ...options.headers
        },
        ...options
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const result = await response.json();
      setData(result);
      return result;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return { data, loading, error, execute };
};
```

#### Vue.js
```vue
<!-- Composable useApiCall -->
<script setup>
import { ref } from 'vue'

export const useApiCall = (url, options = {}) => {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const execute = async () => {
    loading.value = true
    error.value = null
    
    try {
      const response = await fetch(url, {
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Content-Type': 'application/json',
          ...options.headers
        },
        ...options
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const result = await response.json()
      data.value = result
      return result
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, execute }
}
</script>

<!-- Componente de ejemplo usando el composable -->
<template>
  <div class="api-component">
    <div v-if="loading" class="loading">
      Cargando...
    </div>
    
    <div v-else-if="error" class="error">
      Error: {{ error }}
    </div>
    
    <div v-else-if="data" class="content">
      {{ data }}
    </div>
  </div>
</template>

<script setup>
import { useApiCall } from '@/composables/useApiCall'

const { data, loading, error, execute } = useApiCall('/api/dashboard/kpis')

// Ejecutar al montar el componente
onMounted(() => {
  execute()
})
</script>
```

### 7. Validación de Formularios

#### JavaScript (Común para React y Vue)
```javascript
const validateKpiForm = (data) => {
  const errors = {};

  if (!data.name?.trim()) {
    errors.name = 'El nombre es obligatorio';
  }

  if (!data.code?.trim()) {
    errors.code = 'El código es obligatorio';
  } else if (!/^[a-z0-9_]+$/.test(data.code)) {
    errors.code = 'El código solo puede contener letras minúsculas, números y guiones bajos';
  }

  if (!data.base_model) {
    errors.base_model = 'Debe seleccionar un modelo base';
  }

  if (!data.unit?.trim()) {
    errors.unit = 'La unidad es obligatoria';
  }

  return errors;
};

const validateChartConfig = (data) => {
  const errors = {};

  if (!data.chart_type) {
    errors.chart_type = 'Debe seleccionar un tipo de gráfico';
  }

  if (!data.group_by) {
    errors.group_by = 'Debe seleccionar un campo para agrupar';
  }

  if (data.filters?.some(filter => !filter.field || !filter.value)) {
    errors.filters = 'Todos los filtros deben tener campo y valor';
  }

  return errors;
};
```

#### Vue.js - Composable de Validación
```vue
<!-- composables/useValidation.js -->
<script setup>
import { ref, computed } from 'vue'

export const useValidation = () => {
  const errors = ref({})

  const validateField = (field, value, rules) => {
    const fieldErrors = []
    
    if (rules.required && (!value || value.toString().trim() === '')) {
      fieldErrors.push(`${rules.label || field} es obligatorio`)
    }
    
    if (rules.pattern && value && !rules.pattern.test(value)) {
      fieldErrors.push(rules.message || `${rules.label || field} no tiene el formato correcto`)
    }
    
    if (rules.minLength && value && value.length < rules.minLength) {
      fieldErrors.push(`${rules.label || field} debe tener al menos ${rules.minLength} caracteres`)
    }
    
    if (rules.maxLength && value && value.length > rules.maxLength) {
      fieldErrors.push(`${rules.label || field} no puede tener más de ${rules.maxLength} caracteres`)
    }

    if (fieldErrors.length > 0) {
      errors.value[field] = fieldErrors[0] // Solo el primer error
    } else {
      delete errors.value[field]
    }
  }

  const validateForm = (data, rules) => {
    errors.value = {}
    
    Object.keys(rules).forEach(field => {
      validateField(field, data[field], rules[field])
    })
    
    return Object.keys(errors.value).length === 0
  }

  const hasErrors = computed(() => Object.keys(errors.value).length > 0)
  const getFieldError = (field) => errors.value[field] || null

  return {
    errors,
    validateField,
    validateForm,
    hasErrors,
    getFieldError
  }
}
</script>

<!-- Componente de ejemplo usando validación -->
<template>
  <form @submit.prevent="handleSubmit">
    <div class="form-group">
      <label for="name">Nombre</label>
      <input 
        id="name"
        v-model="formData.name"
        type="text"
        :class="{ 'error': getFieldError('name') }"
        @blur="validateField('name', formData.name, nameRules)"
      />
      <span v-if="getFieldError('name')" class="error-message">
        {{ getFieldError('name') }}
      </span>
    </div>

    <div class="form-group">
      <label for="code">Código</label>
      <input 
        id="code"
        v-model="formData.code"
        type="text"
        :class="{ 'error': getFieldError('code') }"
        @blur="validateField('code', formData.code, codeRules)"
      />
      <span v-if="getFieldError('code')" class="error-message">
        {{ getFieldError('code') }}
      </span>
    </div>

    <button type="submit" :disabled="hasErrors">
      Guardar
    </button>
  </form>
</template>

<script setup>
import { reactive } from 'vue'
import { useValidation } from '@/composables/useValidation'

const { validateField, validateForm, hasErrors, getFieldError } = useValidation()

const formData = reactive({
  name: '',
  code: '',
  base_model: null,
  unit: ''
})

const nameRules = {
  required: true,
  label: 'Nombre',
  minLength: 3
}

const codeRules = {
  required: true,
  label: 'Código',
  pattern: /^[a-z0-9_]+$/,
  message: 'El código solo puede contener letras minúsculas, números y guiones bajos'
}

const handleSubmit = () => {
  const rules = {
    name: nameRules,
    code: codeRules,
    base_model: { required: true, label: 'Modelo base' },
    unit: { required: true, label: 'Unidad' }
  }

  if (validateForm(formData, rules)) {
    // Enviar formulario
    console.log('Formulario válido:', formData)
  }
}
</script>
```

### 8. Optimización de Rendimiento

#### React
```javascript
// Debounce para búsquedas
const useDebounce = (value, delay) => {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => clearTimeout(handler);
  }, [value, delay]);

  return debouncedValue;
};

// Memoización de componentes pesados
const ChartRenderer = memo(({ cardId, chartType, data }) => {
  // ... lógica del componente
});

// Lazy loading de gráficos
const LazyChart = lazy(() => import('./ChartRenderer'));
```

#### Vue.js
```vue
<!-- Composable useDebounce -->
<script setup>
import { ref, watch } from 'vue'

export const useDebounce = (value, delay) => {
  const debouncedValue = ref(value.value || value)

  watch(value, (newValue) => {
    const handler = setTimeout(() => {
      debouncedValue.value = newValue
    }, delay)

    return () => clearTimeout(handler)
  }, { immediate: true })

  return debouncedValue
}
</script>

<!-- Componente optimizado con defineAsyncComponent -->
<template>
  <div class="dashboard-app">
    <DashboardSidebar 
      :dashboards="dashboards"
      :current-dashboard="currentDashboard"
      @select-dashboard="setCurrentDashboard"
    />
    
    <main class="dashboard-main">
      <DashboardHeader
        :dashboard="currentDashboard"
        @add-card="showCardModal = true"
      />
      
      <Suspense>
        <template #default>
          <DashboardGrid
            :cards="cards"
            @card-update="updateCard"
            @card-delete="deleteCard"
          />
        </template>
        <template #fallback>
          <div class="loading">Cargando dashboard...</div>
        </template>
      </Suspense>
    </main>

    <CardModal
      v-if="showCardModal"
      @close="showCardModal = false"
      @save="addCard"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, defineAsyncComponent } from 'vue'
import { useApiCall } from '@/composables/useApiCall'

// Lazy loading de componentes pesados
const DashboardGrid = defineAsyncComponent(() => import('./DashboardGrid.vue'))
const CardModal = defineAsyncComponent(() => import('./CardModal.vue'))

// Estado reactivo
const dashboards = ref([])
const currentDashboard = ref(null)
const cards = ref([])
const showCardModal = ref(false)

// Computed para optimizar re-renders
const dashboardCards = computed(() => 
  cards.value.filter(card => card.dashboard_id === currentDashboard.value?.id)
)

// Debounced search
const searchQuery = ref('')
const debouncedSearch = useDebounce(searchQuery, 300)

// Memoización de funciones pesadas
const loadDashboards = async () => {
  try {
    const { data } = await api.get('/dashboard/dashboards')
    dashboards.value = data
    if (data.length > 0) {
      currentDashboard.value = data[0]
      await loadCards(data[0].id)
    }
  } catch (error) {
    console.error('Error cargando dashboards:', error)
  }
}

const loadCards = async (dashboardId) => {
  try {
    const { data } = await api.get(`/dashboard/dashboard-cards?dashboard_id=${dashboardId}`)
    cards.value = data
  } catch (error) {
    console.error('Error cargando tarjetas:', error)
  }
}

// Watchers optimizados
watch(debouncedSearch, (newQuery) => {
  if (newQuery) {
    // Filtrar cards basado en búsqueda
    filterCards(newQuery)
  }
})

onMounted(() => {
  loadDashboards()
})
</script>

<!-- Componente con v-memo para optimización -->
<template>
  <div class="chart-card" v-memo="[card.id, card.chart_type, cardData]">
    <div class="card-header">
      <h3>{{ card.title }}</h3>
      <div class="card-actions">
        <button @click="editCard">Editar</button>
        <button @click="deleteCard">Eliminar</button>
      </div>
    </div>
    
    <div class="card-content">
      <ChartRenderer
        :card-id="card.id"
        :chart-type="card.chart_type"
        :data="chartData"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  card: {
    type: Object,
    required: true
  }
})

const chartData = ref(null)

// Computed que solo se recalcula cuando cambian las dependencias específicas
const chartConfig = computed(() => ({
  type: props.card.chart_type,
  data: chartData.value,
  options: props.card.chart_parameters
}))

// Watch optimizado con deep: false para evitar re-renders innecesarios
watch(() => props.card.id, async (newId) => {
  if (newId) {
    await loadChartData(newId)
  }
}, { immediate: true })

const loadChartData = async (cardId) => {
  try {
    const { data } = await api.get(`/dashboard/dashboard-cards/${cardId}/chart-data`)
    chartData.value = data
  } catch (error) {
    console.error('Error cargando datos del gráfico:', error)
  }
}
</script>
```

### 9. Tips para UX/UI

#### Navegación Intuitiva
- Usar wizard/stepper para crear KPIs complejos
- Breadcrumbs para navegación en dashboards
- Botones de "Guardar como borrador" para formularios largos

#### Feedback Visual
- Loading states para todas las operaciones async
- Toast notifications para acciones exitosas/fallidas
- Progress bars para operaciones largas
- Skeleton loaders para datos que se están cargando

#### Responsive Design
- Grid system adaptable para diferentes tamaños de pantalla
- Charts responsivos que se ajusten al contenedor
- Sidebar colapsible para configuraciones

#### Accesibilidad
- Labels descriptivos para todos los inputs
- ARIA labels para elementos interactivos
- Navegación por teclado
- Contraste adecuado en colores

### 10. Ejemplo de Implementación Completa

#### React
```jsx
const DashboardApp = () => {
  const [dashboards, setDashboards] = useState([]);
  const [currentDashboard, setCurrentDashboard] = useState(null);
  const [cards, setCards] = useState([]);

  // Cargar dashboards al montar el componente
  useEffect(() => {
    loadDashboards();
  }, []);

  const loadDashboards = async () => {
    try {
      const response = await api.get('/dashboard/dashboards');
      setDashboards(response.data);
      if (response.data.length > 0) {
        setCurrentDashboard(response.data[0]);
        loadCards(response.data[0].id);
      }
    } catch (error) {
      console.error('Error cargando dashboards:', error);
    }
  };

  const loadCards = async (dashboardId) => {
    try {
      const response = await api.get(`/dashboard/dashboard-cards?dashboard_id=${dashboardId}`);
      setCards(response.data);
    } catch (error) {
      console.error('Error cargando tarjetas:', error);
    }
  };

  const addCard = async (cardData) => {
    try {
      const response = await api.post('/dashboard/dashboard-cards', cardData);
      setCards([...cards, response.data]);
    } catch (error) {
      console.error('Error creando tarjeta:', error);
    }
  };

  return (
    <div className="dashboard-app">
      <DashboardSidebar
        dashboards={dashboards}
        currentDashboard={currentDashboard}
        onSelectDashboard={setCurrentDashboard}
      />
      
      <main className="dashboard-main">
        <DashboardHeader
          dashboard={currentDashboard}
          onAddCard={() => setShowCardModal(true)}
        />
        
        <DashboardGrid
          cards={cards}
          onCardUpdate={updateCard}
          onCardDelete={deleteCard}
        />
      </main>

      {showCardModal && (
        <CardModal
          onClose={() => setShowCardModal(false)}
          onSave={addCard}
        />
      )}
    </div>
  );
};
```

#### Vue.js
```vue
<template>
  <div class="dashboard-app">
    <DashboardSidebar
      :dashboards="dashboards"
      :current-dashboard="currentDashboard"
      @select-dashboard="setCurrentDashboard"
    />
    
    <main class="dashboard-main">
      <DashboardHeader
        :dashboard="currentDashboard"
        @add-card="showCardModal = true"
      />
      
      <DashboardGrid
        :cards="filteredCards"
        @card-update="updateCard"
        @card-delete="deleteCard"
      />
    </main>

    <CardModal
      v-if="showCardModal"
      @close="showCardModal = false"
      @save="addCard"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, provide } from 'vue'
import { useApiCall } from '@/composables/useApiCall'
import DashboardSidebar from './components/DashboardSidebar.vue'
import DashboardHeader from './components/DashboardHeader.vue'
import DashboardGrid from './components/DashboardGrid.vue'
import CardModal from './components/CardModal.vue'

// Estado global
const dashboards = ref([])
const currentDashboard = ref(null)
const cards = ref([])
const showCardModal = ref(false)

// Computed para filtrar cards del dashboard actual
const filteredCards = computed(() => 
  cards.value.filter(card => card.dashboard_id === currentDashboard.value?.id)
)

// Composable para llamadas API
const { data: dashboardsData, loading: dashboardsLoading, error: dashboardsError, execute: loadDashboards } = useApiCall('/dashboard/dashboards')
const { data: cardsData, loading: cardsLoading, error: cardsError, execute: loadCards } = useApiCall('/dashboard/dashboard-cards')

// Métodos
const setCurrentDashboard = async (dashboard) => {
  currentDashboard.value = dashboard
  if (dashboard) {
    await loadCards(`?dashboard_id=${dashboard.id}`)
    cards.value = cardsData.value || []
  }
}

const addCard = async (cardData) => {
  try {
    const { data } = await api.post('/dashboard/dashboard-cards', {
      ...cardData,
      dashboard_id: currentDashboard.value.id
    })
    cards.value.push(data)
    showCardModal.value = false
  } catch (error) {
    console.error('Error creando tarjeta:', error)
  }
}

const updateCard = async (cardId, updatedData) => {
  try {
    const { data } = await api.put(`/dashboard/dashboard-cards/${cardId}`, updatedData)
    const index = cards.value.findIndex(card => card.id === cardId)
    if (index !== -1) {
      cards.value[index] = data
    }
  } catch (error) {
    console.error('Error actualizando tarjeta:', error)
  }
}

const deleteCard = async (cardId) => {
  try {
    await api.delete(`/dashboard/dashboard-cards/${cardId}`)
    cards.value = cards.value.filter(card => card.id !== cardId)
  } catch (error) {
    console.error('Error eliminando tarjeta:', error)
  }
}

// Cargar datos iniciales
onMounted(async () => {
  await loadDashboards()
  if (dashboardsData.value && dashboardsData.value.length > 0) {
    dashboards.value = dashboardsData.value
    await setCurrentDashboard(dashboards.value[0])
  }
})

// Provide para componentes hijos
provide('dashboardContext', {
  currentDashboard,
  cards: filteredCards,
  addCard,
  updateCard,
  deleteCard
})
</script>

<!-- Componente DashboardGrid.vue -->
<template>
  <div class="dashboard-grid" :style="gridStyle">
    <DashboardCard
      v-for="card in cards"
      :key="card.id"
      :card="card"
      @update="updateCard"
      @delete="deleteCard"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DashboardCard from './DashboardCard.vue'

const props = defineProps({
  cards: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['card-update', 'card-delete'])

const gridStyle = computed(() => ({
  display: 'grid',
  gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
  gap: '1rem',
  padding: '1rem'
}))

const updateCard = (cardId, data) => {
  emit('card-update', cardId, data)
}

const deleteCard = (cardId) => {
  emit('card-delete', cardId)
}
</script>

<!-- Componente DashboardCard.vue -->
<template>
  <div class="dashboard-card" :style="cardStyle">
    <div class="card-header">
      <h3>{{ card.title }}</h3>
      <div class="card-actions">
        <button @click="editCard">Editar</button>
        <button @click="deleteCard">Eliminar</button>
      </div>
    </div>
    
    <div class="card-content">
      <ChartRenderer
        :card-id="card.id"
        :chart-type="card.chart_type"
        :data="chartData"
        :loading="chartLoading"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import ChartRenderer from './ChartRenderer.vue'
import { useApiCall } from '@/composables/useApiCall'

const props = defineProps({
  card: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update', 'delete'])

const chartData = ref(null)
const { data, loading: chartLoading, execute: loadChartData } = useApiCall(`/dashboard/dashboard-cards/${props.card.id}/chart-data`)

const cardStyle = computed(() => ({
  backgroundColor: props.card.background_color || '#ffffff',
  color: props.card.text_color || '#000000',
  borderRadius: '8px',
  padding: '1rem',
  boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
}))

const editCard = () => {
  // Lógica para editar tarjeta
  emit('update', props.card.id, { /* datos editados */ })
}

const deleteCard = () => {
  if (confirm('¿Estás seguro de que quieres eliminar esta tarjeta?')) {
    emit('delete', props.card.id)
  }
}

// Cargar datos del gráfico
onMounted(async () => {
  await loadChartData()
  chartData.value = data.value
})

watch(() => props.card.id, async () => {
  await loadChartData()
  chartData.value = data.value
})
</script>
```

## Consideraciones de Seguridad

1. **Autenticación**: Todas las rutas requieren Bearer token
2. **Validación**: El middleware `ValidateKpiSecurity` valida modelos y campos
3. **Permisos**: Los dashboards están asociados a usuarios específicos
4. **Sanitización**: Validar y sanitizar todos los inputs del usuario
5. **Rate Limiting**: Implementar límites de velocidad para prevenir abuso

## Extensibilidad

El sistema está diseñado para ser extensible:

1. **Nuevos Modelos**: Agregar a `config/kpis.php`
2. **Nuevos Tipos de Gráfico**: Extender `ChartDataService`
3. **Nuevas Operaciones**: Agregar en `KpiFieldRelation`
4. **Nuevos Filtros**: Extender `DynamicFilterService`
5. **Exportación**: Implementar en `DashboardController`

Este sistema proporciona una base sólida y flexible para la creación de dashboards con KPIs personalizables, incluyendo **gráficos interactivos**, **filtros dinámicos**, y **configuración visual intuitiva**.
