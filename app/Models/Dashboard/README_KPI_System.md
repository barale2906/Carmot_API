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

El sistema de KPIs permite crear indicadores de rendimiento de dos formas diferentes, cada una diseñada para diferentes niveles de complejidad y casos de uso.

#### 📊 **Tipos de Cálculo Disponibles**

| Tipo | Descripción | ¿Necesita campos? | Cuándo usar |
|------|-------------|------------------|-------------|
| **`predefined`** | Cálculos automáticos predefinidos | ❌ **NO** | Conteos simples, métricas básicas |
| **`custom_fields`** | Cálculos personalizados con campos específicos | ✅ **SÍ** | KPIs con filtros, operaciones específicas |

---

#### 🎯 **Tipo 1: `predefined` - KPIs Automáticos**

**Para qué sirve:** Conteos simples y métricas básicas sin necesidad de configurar campos.

**Ejemplo:** "Total de grupos en el sistema"

```javascript
const kpiData = {
  name: "Total de Grupos",
  code: "total_grupos",
  description: "Número total de grupos registrados en el sistema",
  unit: "grupos",
  is_active: true,
  calculation_type: "predefined",  // ← Tipo automático
  base_model: 1,  // ID del modelo Grupos
  default_period_type: "monthly",
  use_custom_time_range: false
  // ← NO se especifican campos (kpi_fields)
};

const response = await fetch('/api/dashboard/kpis', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify(kpiData)
});
```

**Resultado:** El sistema automáticamente contará todos los grupos del modelo especificado.

---

#### 🔧 **Tipo 2: `custom_fields` - KPIs Personalizados**

**Para qué sirve:** KPIs con filtros específicos, operaciones matemáticas y control total sobre el cálculo.

**Ejemplo A: Conteo con filtro simple**
```javascript
const kpiData = {
  name: "Grupos Activos",
  code: "grupos_activos",
  description: "Número de grupos con estado activo",
  unit: "grupos",
  is_active: true,
  calculation_type: "custom_fields",  // ← Tipo personalizado
  base_model: 1,
  default_period_type: "monthly",
  use_custom_time_range: false,
  kpi_fields: [
    {
      field_name: "status",
      display_name: "Estado",
      field_type: "numeric",
      operation: "where",  // ← Filtrar por condición
      operator: "=",
      value: "1",  // 1 = activo
      is_required: true,
      order: 1
    },
    {
      field_name: "id",
      display_name: "ID",
      field_type: "numeric",
      operation: "count",  // ← Contar registros que cumplan el filtro
      is_required: true,
      order: 2
    }
  ]
};
```

**Ejemplo B: Suma de valores**
```javascript
const kpiData = {
  name: "Total de Inscritos",
  code: "total_inscritos",
  description: "Suma total de inscritos en todos los grupos",
  unit: "estudiantes",
  is_active: true,
  calculation_type: "custom_fields",
  base_model: 1,
  default_period_type: "monthly",
  kpi_fields: [
    {
      field_name: "inscritos",
      display_name: "Inscritos",
      field_type: "numeric",
      operation: "sum",  // ← Sumar valores del campo
      is_required: true,
      order: 1
    }
  ]
};
```

**Ejemplo C: Promedio con filtros múltiples**
```javascript
const kpiData = {
  name: "Promedio de Inscritos por Grupo Activo",
  code: "promedio_inscritos_activos",
  description: "Promedio de inscritos en grupos activos",
  unit: "estudiantes",
  is_active: true,
  calculation_type: "custom_fields",
  base_model: 1,
  default_period_type: "monthly",
  kpi_fields: [
    {
      field_name: "status",
      display_name: "Estado",
      field_type: "numeric",
      operation: "where",
      operator: "=",
      value: "1",  // Solo grupos activos
      is_required: true,
      order: 1
    },
    {
      field_name: "inscritos",
      display_name: "Inscritos",
      field_type: "numeric",
      operation: "avg",  // ← Calcular promedio
      is_required: true,
      order: 2
    }
  ]
};
```

**Ejemplo D: Múltiples filtros**
```javascript
const kpiData = {
  name: "Grupos Activos por Sede Específica",
  code: "grupos_activos_sede_principal",
  description: "Grupos activos en la sede principal",
  unit: "grupos",
  is_active: true,
  calculation_type: "custom_fields",
  base_model: 1,
  default_period_type: "monthly",
  kpi_fields: [
    {
      field_name: "status",
      display_name: "Estado",
      field_type: "numeric",
      operation: "where",
      operator: "=",
      value: "1",  // Estado activo
      is_required: true,
      order: 1
    },
    {
      field_name: "sede_id",
      display_name: "Sede",
      field_type: "numeric",
      operation: "where",
      operator: "=",
      value: "1",  // Sede principal
      is_required: true,
      order: 2
    },
    {
      field_name: "id",
      display_name: "ID",
      field_type: "numeric",
      operation: "count",
      is_required: true,
      order: 3
    }
  ]
};
```

---

#### 🚀 **Flujo Completo: Crear KPI con Campos**

**Paso 1: Crear el KPI básico**
```javascript
const createKpi = async () => {
  const kpiData = {
    name: "Grupos Activos por Módulo",
    code: "grupos_activos_modulo",
    description: "Conteo de grupos activos agrupados por módulo",
    unit: "grupos",
    is_active: true,
    calculation_type: "custom_fields",
    base_model: 1,
    default_period_type: "monthly",
    use_custom_time_range: false
  };

  const response = await fetch('/api/dashboard/kpis', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify(kpiData)
  });

  const kpi = await response.json();
  return kpi.data;
};
```

**Paso 2: Agregar campos de configuración**
```javascript
const addKpiFields = async (kpiId) => {
  const fields = [
    {
      kpi_id: kpiId,
      field_name: "status",
      display_name: "Estado",
      field_type: "numeric",
      operation: "where",
      operator: "=",
      value: "1",
      is_required: true,
      order: 1
    },
    {
      kpi_id: kpiId,
      field_name: "modulo_id",
      display_name: "Módulo",
      field_type: "numeric",
      operation: "group_by",  // ← Agrupar por módulo
      is_required: true,
      order: 2
    },
    {
      kpi_id: kpiId,
      field_name: "id",
      display_name: "ID",
      field_type: "numeric",
      operation: "count",
      is_required: true,
      order: 3
    }
  ];

  // Crear cada campo
  for (const field of fields) {
    await fetch('/api/dashboard/kpi-fields', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
      },
      body: JSON.stringify(field)
    });
  }
};
```

**Paso 3: Usar el KPI completo**
```javascript
const createCompleteKpi = async () => {
  try {
    // Crear KPI
    const kpi = await createKpi();
    
    // Agregar campos
    await addKpiFields(kpi.id);
    
    console.log('KPI creado exitosamente:', kpi);
    return kpi;
  } catch (error) {
    console.error('Error creando KPI:', error);
  }
};
```

---

#### 📋 **Operaciones Disponibles para Campos**

| Operación | Descripción | Ejemplo de uso |
|-----------|-------------|----------------|
| **`count`** | Contar registros | Total de grupos |
| **`sum`** | Sumar valores | Total de inscritos |
| **`avg`** | Promedio | Promedio de inscritos |
| **`min`** | Valor mínimo | Mínimo de inscritos |
| **`max`** | Valor máximo | Máximo de inscritos |
| **`where`** | Filtrar por condición | Solo grupos activos |
| **`group_by`** | Agrupar por campo | Agrupar por sede |

---

#### 🎨 **Interfaz de Usuario Recomendada**

**Para desarrolladores frontend, aquí tienes un ejemplo de cómo estructurar la UI:**

```jsx
// Componente React para crear KPIs
const KpiCreator = () => {
  const [step, setStep] = useState(1);
  const [kpiData, setKpiData] = useState({
    name: '',
    code: '',
    description: '',
    unit: '',
    calculation_type: 'predefined', // ← Valor por defecto
    base_model: null,
    default_period_type: 'monthly'
  });
  const [fields, setFields] = useState([]);

  const calculationTypes = [
    { value: 'predefined', label: 'Automático', description: 'Cálculo simple sin configuración' },
    { value: 'custom_fields', label: 'Personalizado', description: 'Con filtros y operaciones específicas' }
  ];

  return (
    <div className="kpi-creator">
      <div className="step-indicator">
        <span className={step >= 1 ? 'active' : ''}>1. Información Básica</span>
        <span className={step >= 2 ? 'active' : ''}>2. Configuración</span>
        <span className={step >= 3 ? 'active' : ''}>3. Campos</span>
      </div>

      {step === 1 && (
        <BasicInfoStep 
          data={kpiData} 
          onChange={setKpiData}
          calculationTypes={calculationTypes}
        />
      )}

      {step === 2 && kpiData.calculation_type === 'custom_fields' && (
        <FieldsConfigStep 
          fields={fields}
          onChange={setFields}
          baseModel={kpiData.base_model}
        />
      )}

      {step === 3 && (
        <ReviewStep 
          kpiData={kpiData}
          fields={fields}
          onSubmit={createKpi}
        />
      )}
    </div>
  );
};
```

**Ejemplo de componente para selección de tipo de cálculo:**
```jsx
const CalculationTypeSelector = ({ value, onChange }) => {
  const types = [
    {
      value: 'predefined',
      title: 'Automático',
      description: 'Perfecto para conteos simples',
      icon: '⚡',
      example: 'Total de grupos'
    },
    {
      value: 'custom_fields',
      title: 'Personalizado',
      description: 'Con filtros y operaciones específicas',
      icon: '🔧',
      example: 'Grupos activos por sede'
    }
  ];

  return (
    <div className="calculation-types">
      <h3>¿Cómo quieres calcular tu KPI?</h3>
      <div className="type-cards">
        {types.map(type => (
          <div 
            key={type.value}
            className={`type-card ${value === type.value ? 'selected' : ''}`}
            onClick={() => onChange(type.value)}
          >
            <div className="type-icon">{type.icon}</div>
            <h4>{type.title}</h4>
            <p>{type.description}</p>
            <small>Ejemplo: {type.example}</small>
          </div>
        ))}
      </div>
    </div>
  );
};
```

---

#### ✅ **Validaciones Importantes**

**Para el frontend, asegúrate de validar:**

```javascript
const validateKpiForm = (data) => {
  const errors = {};

  // Validaciones básicas
  if (!data.name?.trim()) {
    errors.name = 'El nombre es obligatorio';
  }

  if (!data.code?.trim()) {
    errors.code = 'El código es obligatorio';
  } else if (!/^[a-z0-9_]+$/.test(data.code)) {
    errors.code = 'Solo letras minúsculas, números y guiones bajos';
  }

  if (!data.calculation_type) {
    errors.calculation_type = 'Debe seleccionar un tipo de cálculo';
  }

  if (!data.base_model) {
    errors.base_model = 'Debe seleccionar un modelo base';
  }

  // Validaciones específicas por tipo
  if (data.calculation_type === 'custom_fields') {
    if (!data.kpi_fields || data.kpi_fields.length === 0) {
      errors.fields = 'Debe agregar al menos un campo';
    } else {
      // Verificar que haya al menos una operación principal
      const hasMainOperation = data.kpi_fields.some(field => 
        ['sum', 'count', 'avg', 'min', 'max'].includes(field.operation)
      );
      
      if (!hasMainOperation) {
        errors.fields = 'Debe tener al menos un campo con operación principal (sum, count, avg, min, max)';
      }
    }
  }

  return errors;
};
```

---

#### 🎯 **Resumen para Desarrolladores Frontend**

1. **`predefined`**: Más simple, solo necesita información básica
2. **`custom_fields`**: Más flexible, permite filtros y operaciones específicas

**Recomendación:** Comienza con `predefined` para casos simples, y usa `custom_fields` para casos más complejos con filtros y operaciones específicas.

---

#### 🎯 **Guía Rápida para Usuarios Finales**

**¿Qué tipo de KPI necesitas crear?**

| Tu necesidad | Tipo recomendado | Ejemplo |
|--------------|------------------|---------|
| "Quiero saber cuántos grupos hay" | `predefined` | Total de grupos |
| "Quiero contar solo grupos activos" | `custom_fields` | Grupos con status = 1 |
| "Quiero sumar todos los inscritos" | `custom_fields` | Suma del campo inscritos |
| "Quiero el promedio de inscritos" | `custom_fields` | Promedio del campo inscritos |
| "Quiero grupos activos por sede" | `custom_fields` | Filtro + agrupación |
| "Quiero un cálculo complejo" | `custom_fields` | Ratios y porcentajes con operaciones |

**Pasos simples para crear un KPI:**

1. **Elige el tipo** → `predefined` (fácil) o `custom_fields` (flexible)
2. **Selecciona el modelo** → Grupos, Módulos, Ciclos, etc.
3. **Configura campos** → Solo si elegiste `custom_fields`
4. **¡Listo!** → Tu KPI está creado

---

#### 📝 **Plantillas de KPIs Comunes**

**Para el frontend, puedes ofrecer estas plantillas predefinidas:**

```javascript
const kpiTemplates = {
  // Plantilla 1: Conteo simple
  simple_count: {
    name: "Total de {model_name}",
    code: "total_{model_code}",
    calculation_type: "predefined",
    description: "Conteo total de {model_name} en el sistema"
  },

  // Plantilla 2: Conteo con filtro
  filtered_count: {
    name: "{model_name} Activos",
    code: "{model_code}_activos",
    calculation_type: "custom_fields",
    description: "Conteo de {model_name} con estado activo",
    kpi_fields: [
      {
        field_name: "status",
        operation: "where",
        operator: "=",
        value: "1"
      },
      {
        field_name: "id",
        operation: "count"
      }
    ]
  },

  // Plantilla 3: Suma de valores
  sum_values: {
    name: "Total de {field_display_name}",
    code: "total_{field_name}",
    calculation_type: "custom_fields",
    description: "Suma total de {field_display_name}",
    kpi_fields: [
      {
        field_name: "{field_name}",
        operation: "sum"
      }
    ]
  },

  // Plantilla 4: Promedio con filtro
  average_filtered: {
    name: "Promedio de {field_display_name} (Activos)",
    code: "promedio_{field_name}_activos",
    calculation_type: "custom_fields",
    description: "Promedio de {field_display_name} en registros activos",
    kpi_fields: [
      {
        field_name: "status",
        operation: "where",
        operator: "=",
        value: "1"
      },
      {
        field_name: "{field_name}",
        operation: "avg"
      }
    ]
  }
};

// Función para aplicar plantilla
const applyTemplate = (template, variables) => {
  let result = JSON.parse(JSON.stringify(template));
  
  // Reemplazar variables en strings
  const replaceVariables = (obj) => {
    for (let key in obj) {
      if (typeof obj[key] === 'string') {
        obj[key] = obj[key].replace(/\{(\w+)\}/g, (match, varName) => {
          return variables[varName] || match;
        });
      } else if (typeof obj[key] === 'object' && obj[key] !== null) {
        replaceVariables(obj[key]);
      }
    }
  };
  
  replaceVariables(result);
  return result;
};

// Ejemplo de uso
const grupoTemplate = applyTemplate(kpiTemplates.filtered_count, {
  model_name: "Grupos",
  model_code: "grupos"
});
```

---

#### 🎨 **Componente de Asistente de KPIs**

```jsx
const KpiWizard = () => {
  const [step, setStep] = useState(1);
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [kpiData, setKpiData] = useState({});

  const templates = [
    {
      id: 'simple_count',
      title: 'Conteo Simple',
      description: 'Contar todos los registros',
      icon: '🔢',
      example: 'Total de grupos: 150'
    },
    {
      id: 'filtered_count',
      title: 'Conteo con Filtro',
      description: 'Contar registros que cumplan una condición',
      icon: '🔍',
      example: 'Grupos activos: 120'
    },
    {
      id: 'sum_values',
      title: 'Suma de Valores',
      description: 'Sumar valores de un campo',
      icon: '➕',
      example: 'Total inscritos: 2,500'
    },
    {
      id: 'average_filtered',
      title: 'Promedio con Filtro',
      description: 'Promedio de valores con condición',
      icon: '📊',
      example: 'Promedio inscritos por grupo: 20'
    }
  ];

  const handleTemplateSelect = (templateId) => {
    setSelectedTemplate(templateId);
    setStep(2);
  };

  const handleModelSelect = (modelId) => {
    const template = kpiTemplates[selectedTemplate];
    const modelConfig = getModelConfig(modelId);
    
    const variables = {
      model_name: modelConfig.display_name,
      model_code: modelConfig.display_name.toLowerCase().replace(/\s+/g, '_'),
      field_name: 'inscritos', // Campo por defecto
      field_display_name: 'Inscritos'
    };

    const kpiTemplate = applyTemplate(template, variables);
    setKpiData({
      ...kpiTemplate,
      base_model: modelId,
      default_period_type: 'monthly',
      use_custom_time_range: false
    });
    
    setStep(3);
  };

  return (
    <div className="kpi-wizard">
      <div className="wizard-header">
        <h2>Crear Nuevo KPI</h2>
        <div className="step-indicator">
          <span className={step >= 1 ? 'active' : ''}>1. Plantilla</span>
          <span className={step >= 2 ? 'active' : ''}>2. Modelo</span>
          <span className={step >= 3 ? 'active' : ''}>3. Configurar</span>
        </div>
      </div>

      {step === 1 && (
        <div className="template-selection">
          <h3>¿Qué tipo de KPI quieres crear?</h3>
          <div className="template-grid">
            {templates.map(template => (
              <div 
                key={template.id}
                className="template-card"
                onClick={() => handleTemplateSelect(template.id)}
              >
                <div className="template-icon">{template.icon}</div>
                <h4>{template.title}</h4>
                <p>{template.description}</p>
                <small className="example">{template.example}</small>
              </div>
            ))}
          </div>
        </div>
      )}

      {step === 2 && (
        <div className="model-selection">
          <h3>Selecciona el modelo base</h3>
          <ModelSelector onSelect={handleModelSelect} />
        </div>
      )}

      {step === 3 && (
        <div className="kpi-configuration">
          <h3>Configuración del KPI</h3>
          <KpiConfigForm 
            data={kpiData}
            onChange={setKpiData}
            onSubmit={createKpi}
          />
        </div>
      )}
    </div>
  );
};
```

---

#### 🚀 **Ejemplos de Uso en la Vida Real**

**Caso 1: Director Académico**
```javascript
// "Quiero saber cuántos grupos activos tenemos por sede"
const kpiData = {
  name: "Grupos Activos por Sede",
  code: "grupos_activos_sede",
  calculation_type: "custom_fields",
  base_model: 1, // Grupos
  kpi_fields: [
    { field_name: "status", operation: "where", operator: "=", value: "1" },
    { field_name: "sede_id", operation: "group_by" },
    { field_name: "id", operation: "count" }
  ]
};
```

**Caso 2: Coordinador de Admisiones**
```javascript
// "Quiero el total de estudiantes inscritos"
const kpiData = {
  name: "Total de Estudiantes Inscritos",
  code: "total_estudiantes_inscritos",
  calculation_type: "custom_fields",
  base_model: 1, // Grupos
  kpi_fields: [
    { field_name: "inscritos", operation: "sum" }
  ]
};
```

**Caso 3: Gerente de Operaciones**
```javascript
// "Quiero el promedio de inscritos por grupo activo"
const kpiData = {
  name: "Promedio de Inscritos por Grupo Activo",
  code: "promedio_inscritos_grupo_activo",
  calculation_type: "custom_fields",
  base_model: 1, // Grupos
  kpi_fields: [
    { field_name: "status", operation: "where", operator: "=", value: "1" },
    { field_name: "inscritos", operation: "avg" }
  ]
};
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
