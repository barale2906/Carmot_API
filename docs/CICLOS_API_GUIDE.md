# Guía de API - Gestión de Ciclos Académicos

## Tabla de Contenidos
- [Introducción](#introducción)
- [Endpoints Disponibles](#endpoints-disponibles)
- [Flujo de Trabajo Frontend](#flujo-de-trabajo-frontend)
- [Ejemplos de Uso](#ejemplos-de-uso)
- [Estructura de Datos](#estructura-de-datos)
- [Validaciones](#validaciones)
- [Códigos de Error](#códigos-de-error)

## Introducción

El sistema de Ciclos Académicos permite gestionar períodos de formación con grupos asociados. Cada ciclo puede tener múltiples grupos que se ejecutan de forma secuencial, con fechas de inicio y fin calculadas automáticamente.

### Características Principales
- ✅ **Cálculo automático de fechas** basado en horarios de grupos
- ✅ **Orden secuencial** de grupos dentro del ciclo
- ✅ **Gestión de cronogramas** realistas
- ✅ **Validaciones robustas** de fechas y relaciones
- ✅ **Soft delete** para preservar historial

## Endpoints Disponibles

### Base URL
```
GET/POST/PUT/DELETE /api/ciclos
```

### Lista de Endpoints

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/ciclos` | Listar ciclos con filtros |
| `POST` | `/api/ciclos` | Crear nuevo ciclo |
| `GET` | `/api/ciclos/{id}` | Obtener ciclo específico |
| `PUT` | `/api/ciclos/{id}` | Actualizar ciclo |
| `DELETE` | `/api/ciclos/{id}` | Eliminar ciclo (soft delete) |
| `POST` | `/api/ciclos/{id}/restore` | Restaurar ciclo eliminado |
| `DELETE` | `/api/ciclos/{id}/force` | Eliminar permanentemente |
| `GET` | `/api/ciclos/trashed` | Listar ciclos eliminados |
| `GET` | `/api/ciclos/filters` | Obtener opciones de filtros |
| `GET` | `/api/ciclos/statistics` | Obtener estadísticas |
| `POST` | `/api/ciclos/{id}/grupos` | Asignar grupos al ciclo |
| `DELETE` | `/api/ciclos/{id}/grupos` | Desasignar grupo del ciclo |
| `POST` | `/api/ciclos/{id}/calcular-fecha` | Calcular fecha de fin |
| `GET` | `/api/ciclos/{id}/cronograma` | Obtener cronograma detallado |

## Flujo de Trabajo Frontend

### 1. **Inicialización de la Aplicación**
```javascript
// Cargar datos iniciales necesarios
const loadInitialData = async () => {
  const [sedes, cursos, filtros] = await Promise.all([
    fetch('/api/sedes').then(r => r.json()),
    fetch('/api/cursos').then(r => r.json()),
    fetch('/api/ciclos/filters').then(r => r.json())
  ]);
  
  return { sedes, cursos, filtros };
};
```

### 2. **Listado de Ciclos**
```javascript
// Cargar lista de ciclos con paginación y filtros
const loadCiclos = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/ciclos?${params}`);
  return response.json();
};
```

### 3. **Creación de Ciclo**
```javascript
// Crear nuevo ciclo con grupos
const createCiclo = async (cicloData) => {
  const response = await fetch('/api/ciclos', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(cicloData)
  });
  return response.json();
};
```

### 4. **Gestión de Grupos**
```javascript
// Asignar grupos al ciclo
const asignarGrupos = async (cicloId, grupos) => {
  const response = await fetch(`/api/ciclos/${cicloId}/grupos`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ grupos })
  });
  return response.json();
};
```

## Ejemplos de Uso

### React con Hooks

```jsx
import React, { useState, useEffect } from 'react';

const CiclosManager = () => {
  const [ciclos, setCiclos] = useState([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState({});

  // Cargar ciclos
  const loadCiclos = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams(filters);
      const response = await fetch(`/api/ciclos?${params}`);
      const data = await response.json();
      setCiclos(data.data);
    } catch (error) {
      console.error('Error cargando ciclos:', error);
    } finally {
      setLoading(false);
    }
  };

  // Crear nuevo ciclo
  const createCiclo = async (cicloData) => {
    try {
      const response = await fetch('/api/ciclos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cicloData)
      });
      
      if (response.ok) {
        await loadCiclos(); // Recargar lista
        return await response.json();
      } else {
        throw new Error('Error creando ciclo');
      }
    } catch (error) {
      console.error('Error:', error);
      throw error;
    }
  };

  // Asignar grupos
  const asignarGrupos = async (cicloId, grupos) => {
    try {
      const response = await fetch(`/api/ciclos/${cicloId}/grupos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ grupos })
      });
      
      if (response.ok) {
        await loadCiclos();
        return await response.json();
      }
    } catch (error) {
      console.error('Error asignando grupos:', error);
    }
  };

  useEffect(() => {
    loadCiclos();
  }, [filters]);

  return (
    <div>
      {/* Tu componente aquí */}
    </div>
  );
};
```

### Vue 3 con Composition API

```vue
<template>
  <div>
    <div v-if="loading">Cargando...</div>
    <div v-else>
      <div v-for="ciclo in ciclos" :key="ciclo.id">
        <h3>{{ ciclo.nombre }}</h3>
        <p>Fecha inicio: {{ ciclo.fecha_inicio }}</p>
        <p>Fecha fin: {{ ciclo.fecha_fin }}</p>
        <p>Grupos: {{ ciclo.grupos_count }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

const ciclos = ref([]);
const loading = ref(false);
const filters = ref({});

// Cargar ciclos
const loadCiclos = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams(filters.value);
    const response = await fetch(`/api/ciclos?${params}`);
    const data = await response.json();
    ciclos.value = data.data;
  } catch (error) {
    console.error('Error cargando ciclos:', error);
  } finally {
    loading.value = false;
  }
};

// Crear ciclo
const createCiclo = async (cicloData) => {
  try {
    const response = await fetch('/api/ciclos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cicloData)
    });
    
    if (response.ok) {
      await loadCiclos();
      return await response.json();
    }
  } catch (error) {
    console.error('Error creando ciclo:', error);
  }
};

onMounted(() => {
  loadCiclos();
});
</script>
```

### JavaScript Vanilla

```javascript
class CiclosAPI {
  constructor(baseURL = '/api/ciclos') {
    this.baseURL = baseURL;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.message || 'Error en la petición');
      }
      
      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Listar ciclos
  async getCiclos(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.request(`?${params}`);
  }

  // Obtener ciclo específico
  async getCiclo(id) {
    return this.request(`/${id}`);
  }

  // Crear ciclo
  async createCiclo(cicloData) {
    return this.request('', {
      method: 'POST',
      body: JSON.stringify(cicloData)
    });
  }

  // Actualizar ciclo
  async updateCiclo(id, cicloData) {
    return this.request(`/${id}`, {
      method: 'PUT',
      body: JSON.stringify(cicloData)
    });
  }

  // Eliminar ciclo
  async deleteCiclo(id) {
    return this.request(`/${id}`, {
      method: 'DELETE'
    });
  }

  // Asignar grupos
  async asignarGrupos(cicloId, grupos) {
    return this.request(`/${cicloId}/grupos`, {
      method: 'POST',
      body: JSON.stringify({ grupos })
    });
  }

  // Obtener cronograma
  async getCronograma(cicloId) {
    return this.request(`/${cicloId}/cronograma`);
  }
}

// Uso
const api = new CiclosAPI();

// Ejemplo de uso
async function ejemploUso() {
  try {
    // Cargar ciclos
    const ciclos = await api.getCiclos({ status: 1 });
    console.log('Ciclos activos:', ciclos.data);

    // Crear nuevo ciclo
    const nuevoCiclo = await api.createCiclo({
      sede_id: 1,
      curso_id: 1,
      nombre: 'Ciclo Test 2024',
      descripcion: 'Ciclo de prueba',
      fecha_inicio: '2024-01-15',
      fecha_fin_automatica: true,
      grupos: [1, 2, 3]
    });
    console.log('Ciclo creado:', nuevoCiclo.data);

    // Obtener cronograma
    const cronograma = await api.getCronograma(nuevoCiclo.data.id);
    console.log('Cronograma:', cronograma.data);

  } catch (error) {
    console.error('Error:', error);
  }
}
```

## Estructura de Datos

### Ciclo (Response)
```json
{
  "id": 1,
  "nombre": "Ciclo I 2024",
  "descripcion": "Ciclo académico regular del año",
  "fecha_inicio": "2024-01-15",
  "fecha_fin": "2024-06-15",
  "fecha_fin_automatica": true,
  "duracion_dias": 151,
  "duracion_estimada": 151,
  "total_horas": 480,
  "horas_por_semana": 20,
  "en_curso": false,
  "finalizado": false,
  "por_iniciar": true,
  "status": 1,
  "status_text": "Activo",
  "created_at": "2024-01-10 10:30:00",
  "updated_at": "2024-01-10 10:30:00",
  "deleted_at": null,
  "sede": {
    "id": 1,
    "nombre": "Sede Principal",
    "direccion": "Av. Principal 123",
    "telefono": "+1234567890",
    "email": "sede@universidad.edu",
    "hora_inicio": "08:00:00",
    "hora_fin": "18:00:00",
    "status": 1,
    "status_text": "Activo"
  },
  "curso": {
    "id": 1,
    "nombre": "Ingeniería de Software",
    "duracion": 480,
    "status": 1,
    "status_text": "Activo"
  },
  "grupos": [
    {
      "id": 1,
      "nombre": "Grupo A",
      "inscritos": 25,
      "jornada": 0,
      "jornada_nombre": "Mañana",
      "status": 1,
      "status_text": "Activo",
      "orden": 1,
      "fecha_inicio_grupo": "2024-01-15",
      "fecha_fin_grupo": "2024-03-15",
      "modulo": {
        "id": 1,
        "nombre": "Fundamentos de Programación",
        "duracion": 120
      },
      "profesor": {
        "id": 1,
        "name": "Dr. Juan Pérez",
        "email": "juan.perez@universidad.edu"
      },
      "horarios": [
        {
          "id": 1,
          "dia": "Lunes",
          "hora": "08:00:00",
          "duracion_horas": 2
        },
        {
          "id": 2,
          "dia": "Miércoles",
          "hora": "08:00:00",
          "duracion_horas": 2
        }
      ]
    }
  ],
  "sede_count": 0,
  "curso_count": 0,
  "grupos_count": 3
}
```

### Crear Ciclo (Request)
```json
{
  "sede_id": 1,
  "curso_id": 1,
  "nombre": "Ciclo I 2024",
  "descripcion": "Ciclo académico regular del año",
  "fecha_inicio": "2024-01-15",
  "fecha_fin": "2024-06-15",
  "fecha_fin_automatica": true,
  "grupos": [1, 2, 3],
  "con_orden": false,
  "status": 1
}
```

### Asignar Grupos (Request)
```json
{
  "grupos": [1, 2, 3],
  "con_orden": true
}
```

### Con Orden Específico
```json
{
  "grupos": [
    {
      "grupo_id": 1,
      "orden": 1
    },
    {
      "grupo_id": 2,
      "orden": 2
    },
    {
      "grupo_id": 3,
      "orden": 3
    }
  ],
  "con_orden": true
}
```

## Validaciones

### Campos Requeridos
- `sede_id`: Debe existir en la tabla `sedes`
- `curso_id`: Debe existir en la tabla `cursos`
- `nombre`: Único, máximo 255 caracteres
- `fecha_inicio`: Fecha válida, igual o posterior a hoy

### Campos Opcionales
- `descripcion`: Máximo 1000 caracteres
- `fecha_fin`: Debe ser posterior a `fecha_inicio`
- `fecha_fin_automatica`: Boolean, por defecto `true`
- `grupos`: Array de IDs de grupos existentes
- `status`: 0 (Inactivo) o 1 (Activo)

### Validaciones Específicas
- **Fecha de inicio**: No puede ser anterior a hoy
- **Fecha de fin**: Debe ser posterior a la fecha de inicio
- **Grupos**: Todos los IDs deben existir en la tabla `grupos`
- **Nombre**: Debe ser único en la tabla `ciclos`

## Códigos de Error

### 400 - Bad Request
```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "sede_id": ["La sede es obligatoria."],
    "fecha_inicio": ["La fecha de inicio debe ser igual o posterior a hoy."]
  }
}
```

### 404 - Not Found
```json
{
  "message": "Ciclo no encontrado."
}
```

### 422 - Unprocessable Entity
```json
{
  "message": "No se puede eliminar el ciclo porque tiene grupos asociados."
}
```

### 500 - Internal Server Error
```json
{
  "message": "Error interno del servidor."
}
```

## Flujo de Trabajo Recomendado

### 1. **Carga Inicial**
```javascript
// Cargar datos necesarios para crear ciclos
const loadInitialData = async () => {
  const [sedes, cursos, grupos] = await Promise.all([
    fetch('/api/sedes').then(r => r.json()),
    fetch('/api/cursos').then(r => r.json()),
    fetch('/api/grupos').then(r => r.json())
  ]);
  
  return { sedes, cursos, grupos };
};
```

### 2. **Listado con Filtros**
```javascript
// Implementar filtros dinámicos
const applyFilters = (filters) => {
  const params = new URLSearchParams();
  
  if (filters.search) params.append('search', filters.search);
  if (filters.status !== undefined) params.append('status', filters.status);
  if (filters.sede_id) params.append('sede_id', filters.sede_id);
  if (filters.curso_id) params.append('curso_id', filters.curso_id);
  
  return params.toString();
};
```

### 3. **Creación de Ciclo**
```javascript
// Flujo completo de creación
const createCicloComplete = async (cicloData) => {
  try {
    // 1. Crear ciclo básico
    const ciclo = await api.createCiclo({
      sede_id: cicloData.sede_id,
      curso_id: cicloData.curso_id,
      nombre: cicloData.nombre,
      descripcion: cicloData.descripcion,
      fecha_inicio: cicloData.fecha_inicio,
      fecha_fin_automatica: true
    });

    // 2. Asignar grupos si se proporcionan
    if (cicloData.grupos && cicloData.grupos.length > 0) {
      await api.asignarGrupos(ciclo.data.id, cicloData.grupos);
    }

    // 3. Obtener cronograma calculado
    const cronograma = await api.getCronograma(ciclo.data.id);
    
    return { ciclo, cronograma };
  } catch (error) {
    console.error('Error creando ciclo:', error);
    throw error;
  }
};
```

### 4. **Gestión de Estados**
```javascript
// Manejar estados del ciclo
const getCicloStatus = (ciclo) => {
  if (ciclo.finalizado) return 'finalizado';
  if (ciclo.en_curso) return 'en_curso';
  if (ciclo.por_iniciar) return 'por_iniciar';
  return 'desconocido';
};

// Aplicar estilos según estado
const getStatusClass = (ciclo) => {
  const status = getCicloStatus(ciclo);
  const classes = {
    'finalizado': 'text-gray-500',
    'en_curso': 'text-green-600',
    'por_iniciar': 'text-blue-600',
    'desconocido': 'text-yellow-600'
  };
  return classes[status] || 'text-gray-500';
};
```

## Consideraciones de Rendimiento

### 1. **Paginación**
```javascript
// Implementar paginación en el frontend
const loadCiclosPaginated = async (page = 1, perPage = 15) => {
  const response = await api.getCiclos({
    page,
    per_page: perPage
  });
  
  return {
    data: response.data,
    meta: response.meta,
    hasMore: response.meta.current_page < response.meta.last_page
  };
};
```

### 2. **Carga Lazy de Relaciones**
```javascript
// Cargar relaciones solo cuando sea necesario
const loadCicloWithDetails = async (id) => {
  return api.getCiclo(id, {
    with: 'sede,curso,grupos.modulo,grupos.profesor,grupos.horarios'
  });
};
```

### 3. **Cache Local**
```javascript
// Implementar cache simple
class CiclosCache {
  constructor() {
    this.cache = new Map();
    this.ttl = 5 * 60 * 1000; // 5 minutos
  }

  get(key) {
    const item = this.cache.get(key);
    if (item && Date.now() - item.timestamp < this.ttl) {
      return item.data;
    }
    return null;
  }

  set(key, data) {
    this.cache.set(key, {
      data,
      timestamp: Date.now()
    });
  }
}
```

---

## Conclusión

Esta API proporciona una gestión completa de ciclos académicos con características avanzadas como cálculo automático de fechas, orden secuencial de grupos y cronogramas realistas. El frontend debe implementar un flujo de trabajo que aproveche estas características para ofrecer una experiencia de usuario óptima.

Para más información sobre endpoints específicos, consulta la documentación generada automáticamente con Scramble o contacta al equipo de desarrollo.
