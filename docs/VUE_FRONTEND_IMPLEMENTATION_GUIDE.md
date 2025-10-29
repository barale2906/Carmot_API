# Guía de Implementación Frontend - Sistema de KPIs con Vue.js

Esta guía proporciona los pasos detallados para implementar el sistema de KPIs en el frontend usando Vue.js, vue-echarts y vue-grid-layout.

## 📋 Tabla de Contenidos

1. [Configuración Inicial](#configuración-inicial)
2. [Endpoints Disponibles](#endpoints-disponibles)
3. [Implementación de Componentes](#implementación-de-componentes)
4. [Gestión de Dashboards](#gestión-de-dashboards)
5. [Gestión de Dashboard Cards](#gestión-de-dashboard-cards)
6. [Implementación de Gráficos](#implementación-de-gráficos)
7. [Ejemplos de Uso](#ejemplos-de-uso)

## 🚀 Configuración Inicial

### Dependencias Requeridas (Vue 2)

```json
{
  "dependencies": {
    "vue": "^2.6.14",
    "vue-echarts": "^5.0.0",
    "echarts": "^5.3.1",
    "vue-grid-layout": "^2.3.12",
    "vue-router": "^3.5.4",
    "vuex": "^3.6.2",
    "axios": "^1.0.0"
  }
}
```

### Instalación

```bash
npm install vue@^2.6.14 vue-echarts@^5 echarts@^5.3.1 vue-grid-layout@^2.3.12 vue-router@^3.5.4 vuex@^3.6.2 axios
```

### Configuración de ECharts (Vue 2)

```javascript
// main.js (Vue 2)
import Vue from 'vue'
import App from './App.vue'
import ECharts from 'vue-echarts'
// Cargar el bundle completo de ECharts (simple y compatible)
import * as echarts from 'echarts'

// Registrar componente globalmente
Vue.component('v-chart', ECharts)

Vue.config.productionTip = false

new Vue({
  render: h => h(App)
}).$mount('#app')
```

## 🔗 Endpoints Disponibles

### 1. Configuración del Sistema

#### Obtener Configuración de KPIs
```http
GET /api/dashboard/kpis/config
```

**Respuesta:**
```json
{
  "data": {
    "allowed_operations": {
      "numeric": ["count", "sum", "avg", "max", "min"],
      "integer": ["count", "sum", "avg", "max", "min"],
      "float": ["count", "sum", "avg", "max", "min"],
      "string": ["count"],
      "boolean": ["count"],
      "date": ["count"],
      "datetime": ["count"]
    },
    "period_presets": ["daily", "weekly", "monthly", "quarterly", "yearly"],
    "available_kpi_models": {
      "1": {
        "class": "App\\Models\\Academico\\Grupo",
        "display_name": "Grupos",
        "display_field": "id",
        "date_fields": ["created_at", "updated_at"],
        "default_date_field": "created_at",
        "fields": {
          "id": {"label": "ID", "type": "integer"},
          "sede_id": {"label": "Sede", "type": "integer"},
          "inscritos": {"label": "Inscritos", "type": "integer"}
        }
      }
    }
  }
}
```

### 2. Gestión de KPIs

#### Crear KPI
```http
POST /api/dashboard/kpis
```

**Body:**
```json
{
  "name": "Ciclos que inician después del 31/10/2025",
  "code": "ciclos_inicio_despues_oct2025",
  "description": "Cantidad de ciclos académicos que tienen fecha de inicio posterior al 31 de octubre de 2025",
  "unit": "ciclos",
  "is_active": true,
  "numerator_model": 3,
  "numerator_field": "id",
  "numerator_operation": "count",
  "denominator_model": null,
  "denominator_field": null,
  "denominator_operation": "count",
  "calculation_factor": 1,
  "target_value": null,
  "date_field": "fecha_inicio",
  "period_type": "monthly",
  "chart_type": "bar",
  "chart_schema": {
    "title": {
      "text": "Ciclos que inician después del 31/10/2025",
      "left": "center"
    },
    "xAxis": {
      "name": "Período",
      "nameLocation": "middle",
      "nameGap": 30
    },
    "yAxis": {
      "name": "Cantidad de Ciclos",
      "nameLocation": "middle",
      "nameGap": 50
    },
    "series": [{
      "itemStyle": {
        "color": "#5470c6"
      },
      "emphasis": {
        "itemStyle": {
          "color": "#91cc75"
        }
      }
    }]
  }
}
```

#### Obtener KPI
```http
GET /api/dashboard/kpis/{id}
```

#### Actualizar KPI
```http
PUT /api/dashboard/kpis/{id}
```

#### Eliminar KPI
```http
DELETE /api/dashboard/kpis/{id}
```

#### Calcular KPI
```http
GET /api/dashboard/kpis/{id}/compute
```

**Query Parameters:**
- `period_type`: daily|weekly|monthly|quarterly|yearly
- `start_date`: YYYY-MM-DD
- `end_date`: YYYY-MM-DD
- `date_field`: campo de fecha
- `filters[field]`: valor del filtro
- `group_by`: campo para agrupar
- `group_limit`: límite de grupos (1-1000)

**Ejemplo:**
```
GET /api/dashboard/kpis/1/compute?start_date=2025-10-31&end_date=2025-12-31&date_field=fecha_inicio&group_by=sede_id&group_limit=10
```

**Respuesta:**
```json
{
  "data": {
    "is_grouped": true,
    "factor": 1,
    "formula": "COUNT(id) / COUNT(*) * 1",
    "description": "COUNT(id) de Ciclos / COUNT(*) de  * 1",
    "range": {
      "start": "2025-10-31T00:00:00.000000Z",
      "end": "2025-12-31T23:59:59.000000Z"
    },
    "series": [
      {
        "group": "1",
        "numerator": 2,
        "denominator": 1,
        "value": 2
      }
    ],
    "chart": {
      "tooltip": {"trigger": "axis"},
      "xAxis": {
        "type": "category",
        "data": ["1"],
        "name": "Período"
      },
      "yAxis": {
        "type": "value",
        "name": "Cantidad de Ciclos"
      },
      "series": [{
        "name": "Ciclos que inician después del 31/10/2025",
        "type": "bar",
        "data": [2],
        "itemStyle": {"color": "#5470c6"}
      }],
      "legend": {"data": ["Ciclos que inician después del 31/10/2025"]},
      "title": {"text": "Ciclos que inician después del 31/10/2025"}
    }
  }
}
```

### 3. Opciones de Agrupación

#### Obtener Opciones de Agrupación
```http
GET /api/dashboard/kpis/models/{modelId}/group-by/{field}
```

**Query Parameters:**
- `filters[field]`: valor del filtro
- `date_field`: campo de fecha
- `start_date`: YYYY-MM-DD
- `end_date`: YYYY-MM-DD
- `limit`: límite de opciones (1-100)

**Ejemplo:**
```
GET /api/dashboard/kpis/models/3/group-by/sede_id?filters[status]=1&date_field=fecha_inicio&start_date=2025-10-31&end_date=2025-12-31
```

**Respuesta:**
```json
{
  "field": "sede_id",
  "model": {
    "id": 3,
    "display_name": "Ciclos"
  },
  "options": [
    {
      "value": 1,
      "label": "1 - Sede Central",
      "count": 2
    }
  ],
  "total": 1
}
```

### 4. Gestión de Dashboards

#### CRUD de Dashboards
```http
GET    /api/dashboard/dashboards
POST   /api/dashboard/dashboards
GET    /api/dashboard/dashboards/{id}
PUT    /api/dashboard/dashboards/{id}
DELETE /api/dashboard/dashboards/{id}
```

#### Exportar Dashboard a PDF
```http
POST /api/dashboard/dashboards/{id}/export-pdf
```

### 5. Gestión de Dashboard Cards

#### CRUD de Dashboard Cards
```http
GET    /api/dashboard/dashboard-cards
POST   /api/dashboard/dashboard-cards
GET    /api/dashboard/dashboard-cards/{id}
PUT    /api/dashboard/dashboard-cards/{id}
DELETE /api/dashboard/dashboard-cards/{id}
```

#### Calcular Dashboard Card
```http
GET /api/dashboard/dashboard-cards/{id}/compute
```

## 🧩 Implementación de Componentes

### 1. Servicio API

```javascript
// services/kpiService.js
import axios from 'axios'

const API_BASE = '/api/dashboard'

export const kpiService = {
  // Configuración
  async getConfig() {
    const response = await axios.get(`${API_BASE}/kpis/config`)
    return response.data
  },

  // KPIs
  async getKpis() {
    const response = await axios.get(`${API_BASE}/kpis`)
    return response.data
  },

  async getKpi(id) {
    const response = await axios.get(`${API_BASE}/kpis/${id}`)
    return response.data
  },

  async createKpi(kpiData) {
    const response = await axios.post(`${API_BASE}/kpis`, kpiData)
    return response.data
  },

  async updateKpi(id, kpiData) {
    const response = await axios.put(`${API_BASE}/kpis/${id}`, kpiData)
    return response.data
  },

  async deleteKpi(id) {
    const response = await axios.delete(`${API_BASE}/kpis/${id}`)
    return response.data
  },

  async computeKpi(id, params = {}) {
    const response = await axios.get(`${API_BASE}/kpis/${id}/compute`, { params })
    return response.data
  },

  // Opciones de agrupación
  async getGroupByOptions(modelId, field, params = {}) {
    const response = await axios.get(`${API_BASE}/kpis/models/${modelId}/group-by/${field}`, { params })
    return response.data
  },

  // Dashboards
  async getDashboards() {
    const response = await axios.get(`${API_BASE}/dashboards`)
    return response.data
  },

  async createDashboard(dashboardData) {
    const response = await axios.post(`${API_BASE}/dashboards`, dashboardData)
    return response.data
  },

  async updateDashboard(id, dashboardData) {
    const response = await axios.put(`${API_BASE}/dashboards/${id}`, dashboardData)
    return response.data
  },

  async deleteDashboard(id) {
    const response = await axios.delete(`${API_BASE}/dashboards/${id}`)
    return response.data
  },

  async exportDashboardPdf(id) {
    const response = await axios.post(`${API_BASE}/dashboards/${id}/export-pdf`, {}, {
      responseType: 'blob'
    })
    return response.data
  },

  // Dashboard Cards
  async getDashboardCards() {
    const response = await axios.get(`${API_BASE}/dashboard-cards`)
    return response.data
  },

  async createDashboardCard(cardData) {
    const response = await axios.post(`${API_BASE}/dashboard-cards`, cardData)
    return response.data
  },

  async updateDashboardCard(id, cardData) {
    const response = await axios.put(`${API_BASE}/dashboard-cards/${id}`, cardData)
    return response.data
  },

  async deleteDashboardCard(id) {
    const response = await axios.delete(`${API_BASE}/dashboard-cards/${id}`)
    return response.data
  },

  async computeDashboardCard(id, params = {}) {
    const response = await axios.get(`${API_BASE}/dashboard-cards/${id}/compute`, { params })
    return response.data
  }
}
```

### 2. Componente de Gráfico (Vue 2 - Options API)

```vue
<!-- components/KpiChart.vue -->
<template>
  <div class="kpi-chart">
    <div v-if="loading" class="loading">
      Cargando gráfico...
    </div>
    <div v-else-if="error" class="error">
      Error: {{ error }}
    </div>
    <v-chart
      v-else
      :option="chartOption"
      :style="{ width: '100%', height: '400px' }"
      @click="onChartClick"
    />
  </div>
</template>

<script>
import { kpiService } from '@/services/kpiService'

export default {
  name: 'KpiChart',
  props: {
    kpiId: {
      type: [Number, String],
      required: true
    },
    params: {
      type: Object,
      default: () => ({})
    },
    autoRefresh: {
      type: Boolean,
      default: false
    },
    refreshInterval: {
      type: Number,
      default: 30000
    }
  },
  data() {
    return {
      loading: false,
      error: null,
      chartData: null,
      refreshTimer: null
    }
  },
  computed: {
    chartOption() {
      if (!this.chartData || !this.chartData.chart) return {}
      return this.chartData.chart
    }
  },
  watch: {
    kpiId: {
      handler() { this.loadChartData() },
      immediate: true
    },
    params: {
      handler() { this.loadChartData() },
      deep: true
    },
    autoRefresh(newVal) {
      if (newVal) this.startAutoRefresh()
      else this.stopAutoRefresh()
    }
  },
  mounted() {
    if (this.autoRefresh) this.startAutoRefresh()
  },
  beforeDestroy() {
    this.stopAutoRefresh()
  },
  methods: {
    async loadChartData() {
      try {
        this.loading = true
        this.error = null
        const response = await kpiService.computeKpi(this.kpiId, this.params)
        this.chartData = response.data
        this.$emit('data-loaded', response.data)
      } catch (err) {
        this.error = err.message || 'Error al cargar el gráfico'
        this.$emit('error', err)
      } finally {
        this.loading = false
      }
    },
    onChartClick(event) {
      this.$emit('chart-click', event)
    },
    startAutoRefresh() {
      if (this.refreshInterval > 0) {
        this.refreshTimer = setInterval(this.loadChartData, this.refreshInterval)
      }
    },
    stopAutoRefresh() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer)
        this.refreshTimer = null
      }
    }
  }
}
</script>

<style scoped>
.kpi-chart {
  width: 100%;
  height: 100%;
}

.loading, .error {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 400px;
  font-size: 16px;
}

.error {
  color: #f56565;
}

.loading {
  color: #4299e1;
}
</style>
```

### 3. Componente de Dashboard Card (Vue 2 - Options API)

```vue
<!-- components/DashboardCard.vue -->
<template>
  <div 
    class="dashboard-card"
    :class="{ 'is-dragging': isDragging }"
    :style="cardStyle"
  >
    <div class="card-header">
      <h3 class="card-title">{{ card.title || kpiData?.name }}</h3>
      <div class="card-actions">
        <button @click="toggleSettings" class="btn-settings">
          ⚙️
        </button>
        <button @click="removeCard" class="btn-remove">
          ✕
        </button>
      </div>
    </div>

    <div class="card-content">
      <div v-if="showSettings" class="card-settings">
        <div class="form-group">
          <label>Período:</label>
          <select v-model="localParams.period_type" @change="updateChart">
            <option value="daily">Diario</option>
            <option value="weekly">Semanal</option>
            <option value="monthly">Mensual</option>
            <option value="quarterly">Trimestral</option>
            <option value="yearly">Anual</option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Fecha Inicio:</label>
          <input 
            type="date" 
            v-model="localParams.start_date"
            @change="updateChart"
          />
        </div>
        
        <div class="form-group">
          <label>Fecha Fin:</label>
          <input 
            type="date" 
            v-model="localParams.end_date"
            @change="updateChart"
          />
        </div>

        <div class="form-group">
          <label>Agrupar por:</label>
          <select v-model="localParams.group_by" @change="updateChart">
            <option value="">Sin agrupación</option>
            <option 
              v-for="option in groupByOptions" 
              :key="option.value" 
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="chart-container">
        <KpiChart
          :kpi-id="card.kpi_id"
          :params="localParams"
          :auto-refresh="true"
          :refresh-interval="30000"
          @data-loaded="onDataLoaded"
          @error="onChartError"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { kpiService } from '@/services/kpiService'
import KpiChart from './KpiChart.vue'

export default {
  name: 'DashboardCard',
  components: { KpiChart },
  props: {
    card: { type: Object, required: true },
    isDragging: { type: Boolean, default: false }
  },
  data() {
    return {
      showSettings: false,
      kpiData: null,
      groupByOptions: [],
      localParams: {
        period_type: 'monthly',
        start_date: '',
        end_date: '',
        group_by: '',
        group_limit: 10
      }
    }
  },
  computed: {
    cardStyle() {
      return {
        backgroundColor: this.card.background_color || '#ffffff',
        borderColor: this.card.border_color || '#e2e8f0',
        borderRadius: '8px',
        border: '1px solid',
        padding: '16px',
        boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
      }
    }
  },
  watch: {
    'card.kpi_id': {
      handler() { this.loadKpiData() },
      immediate: true
    },
    localParams: {
      handler() { this.loadGroupByOptions() },
      deep: true
    }
  },
  mounted() {
    this.loadKpiData()
  },
  methods: {
    async loadKpiData() {
      try {
        const response = await kpiService.getKpi(this.card.kpi_id)
        this.kpiData = response.data
      } catch (error) {
        console.error('Error loading KPI data:', error)
      }
    },
    async loadGroupByOptions() {
      if (!this.kpiData || !this.kpiData.numerator_model) return
      try {
        const response = await kpiService.getGroupByOptions(
          this.kpiData.numerator_model,
          'sede_id',
          {
            date_field: this.kpiData.date_field,
            start_date: this.localParams.start_date,
            end_date: this.localParams.end_date
          }
        )
        this.groupByOptions = response.options || []
      } catch (error) {
        console.error('Error loading group by options:', error)
      }
    },
    updateChart() {
      // KpiChart reaccionará por props
    },
    toggleSettings() { this.showSettings = !this.showSettings },
    removeCard() { this.$emit('remove-card', this.card.id) },
    onDataLoaded(data) { /* manejar si se requiere */ },
    onChartError(error) { console.error('Chart error:', error) }
  }
}
</script>

<style scoped>
.dashboard-card {
  position: relative;
  min-height: 300px;
  transition: all 0.3s ease;
}

.dashboard-card.is-dragging {
  opacity: 0.8;
  transform: rotate(5deg);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e2e8f0;
}

.card-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
}

.card-actions {
  display: flex;
  gap: 8px;
}

.btn-settings, .btn-remove {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  font-size: 14px;
}

.btn-settings:hover {
  background-color: #f7fafc;
}

.btn-remove:hover {
  background-color: #fed7d7;
  color: #e53e3e;
}

.card-settings {
  background-color: #f7fafc;
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 16px;
}

.form-group {
  margin-bottom: 12px;
}

.form-group label {
  display: block;
  margin-bottom: 4px;
  font-size: 12px;
  font-weight: 500;
  color: #4a5568;
}

.form-group select,
.form-group input {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  font-size: 12px;
}

.chart-container {
  height: 300px;
}
</style>
```

### 4. Componente de Dashboard Principal (Vue 2 - Options API)

```vue
<!-- components/Dashboard.vue -->
<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <h1>{{ dashboard.name }}</h1>
      <div class="dashboard-actions">
        <button @click="addCard" class="btn-add-card">
          + Agregar Tarjeta
        </button>
        <button @click="exportPdf" class="btn-export">
          📄 Exportar PDF
        </button>
      </div>
    </div>

    <div class="dashboard-content">
      <grid-layout
        :layout="layout"
        :col-num="12"
        :row-height="30"
        :is-draggable="true"
        :is-resizable="true"
        :is-mirrored="false"
        :vertical-compact="true"
        :margin="[10, 10]"
        :use-css-transforms="true"
        @layout-updated="onLayoutUpdated"
      >
        <grid-item
          v-for="item in layout"
          :key="item.i"
          :x="item.x"
          :y="item.y"
          :w="item.w"
          :h="item.h"
          :i="item.i"
        >
          <DashboardCard
            :card="getCardById(item.i)"
            :is-dragging="false"
            @remove-card="removeCard"
          />
        </grid-item>
      </grid-layout>
    </div>
  </div>
</template>

<script>
import Vue from 'vue'
import VueGridLayout from 'vue-grid-layout'
import { kpiService } from '@/services/kpiService'
import DashboardCard from './DashboardCard.vue'

export default {
  name: 'Dashboard',
  components: {
    GridLayout: VueGridLayout.GridLayout,
    GridItem: VueGridLayout.GridItem,
    DashboardCard
  },
  props: {
    dashboardId: { type: [Number, String], required: true }
  },
  data() {
    return {
      dashboard: {},
      cards: [],
      layout: []
    }
  },
  created() {
    this.loadDashboard()
    this.loadCards()
  },
  methods: {
    async loadDashboard() {
      try {
        const response = await kpiService.getDashboard(this.dashboardId)
        this.dashboard = response.data
      } catch (error) {
        console.error('Error loading dashboard:', error)
      }
    },
    async loadCards() {
      try {
        const response = await kpiService.getDashboardCards()
        this.cards = response.data.filter(card => card.dashboard_id == this.dashboardId)
        this.layout = this.cards.map(card => ({
          i: card.id.toString(),
          x: card.position_x || 0,
          y: card.position_y || 0,
          w: card.width || 4,
          h: card.height || 8
        }))
      } catch (error) {
        console.error('Error loading cards:', error)
      }
    },
    getCardById(id) { return this.cards.find(card => card.id.toString() === id) },
    addCard() { console.log('Add card functionality') },
    async removeCard(cardId) {
      try {
        await kpiService.deleteDashboardCard(cardId)
        this.cards = this.cards.filter(card => card.id !== cardId)
        this.layout = this.layout.filter(item => item.i !== cardId.toString())
      } catch (error) {
        console.error('Error removing card:', error)
      }
    },
    onLayoutUpdated(newLayout) {
      this.layout = newLayout
      newLayout.forEach(item => {
        const card = this.cards.find(c => c.id.toString() === item.i)
        if (card) {
          kpiService.updateDashboardCard(card.id, {
            position_x: item.x,
            position_y: item.y,
            width: item.w,
            height: item.h
          })
        }
      })
    },
    async exportPdf() {
      try {
        const response = await kpiService.exportDashboardPdf(this.dashboardId)
        const blob = new Blob([response], { type: 'application/pdf' })
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = `dashboard-${this.dashboardId}.pdf`
        link.click()
        window.URL.revokeObjectURL(url)
      } catch (error) {
        console.error('Error exporting PDF:', error)
      }
    }
  }
}
</script>

<style scoped>
.dashboard {
  padding: 20px;
  background-color: #f7fafc;
  min-height: 100vh;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 2px solid #e2e8f0;
}

.dashboard-header h1 {
  margin: 0;
  color: #2d3748;
  font-size: 24px;
}

.dashboard-actions {
  display: flex;
  gap: 12px;
}

.btn-add-card, .btn-export {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-add-card {
  background-color: #4299e1;
  color: white;
}

.btn-add-card:hover {
  background-color: #3182ce;
}

.btn-export {
  background-color: #48bb78;
  color: white;
}

.btn-export:hover {
  background-color: #38a169;
}

.dashboard-content {
  background-color: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>
```

## 📊 Gestión de Dashboards

### 1. Lista de Dashboards (Vue 2 - Options API)

```vue
<!-- views/DashboardsView.vue -->
<template>
  <div class="dashboards-view">
    <div class="page-header">
      <h1>Dashboards</h1>
      <button @click="createDashboard" class="btn-primary">
        + Nuevo Dashboard
      </button>
    </div>

    <div class="dashboards-grid">
      <div 
        v-for="dashboard in dashboards" 
        :key="dashboard.id"
        class="dashboard-card"
        @click="openDashboard(dashboard.id)"
      >
        <h3>{{ dashboard.name }}</h3>
        <p>{{ dashboard.description }}</p>
        <div class="dashboard-meta">
          <span>{{ dashboard.cards_count }} tarjetas</span>
          <span>{{ formatDate(dashboard.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { kpiService } from '@/services/kpiService'

export default {
  name: 'DashboardsView',
  data() {
    return { dashboards: [] }
  },
  created() { this.loadDashboards() },
  methods: {
    async loadDashboards() {
      try {
        const response = await kpiService.getDashboards()
        this.dashboards = response.data
      } catch (error) {
        console.error('Error loading dashboards:', error)
      }
    },
    createDashboard() { console.log('Create dashboard') },
    openDashboard(id) { this.$router.push(`/dashboard/${id}`) },
    formatDate(date) { return new Date(date).toLocaleDateString() }
  }
}
</script>
```

## 🎯 Ejemplos de Uso

### 1. Crear un KPI desde el Frontend

```javascript
// Ejemplo de creación de KPI
const createKpiExample = async () => {
  const kpiData = {
    name: "Ventas Mensuales",
    code: "ventas_mensuales",
    description: "Total de ventas por mes",
    unit: "USD",
    is_active: true,
    numerator_model: 1,
    numerator_field: "total_ventas",
    numerator_operation: "sum",
    denominator_model: null,
    denominator_field: null,
    denominator_operation: "count",
    calculation_factor: 1,
    target_value: 100000,
    date_field: "fecha_venta",
    period_type: "monthly",
    chart_type: "bar",
    chart_schema: {
      title: {
        text: "Ventas Mensuales",
        left: "center"
      },
      xAxis: {
        name: "Mes",
        nameLocation: "middle"
      },
      yAxis: {
        name: "Ventas (USD)",
        nameLocation: "middle"
      },
      series: [{
        itemStyle: {
          color: "#48bb78"
        }
      }]
    }
  }

  try {
    const response = await kpiService.createKpi(kpiData)
    console.log('KPI creado:', response.data)
  } catch (error) {
    console.error('Error creando KPI:', error)
  }
}
```

### 2. Calcular KPI con Parámetros

```javascript
// Ejemplo de cálculo de KPI con parámetros
const calculateKpiExample = async (kpiId) => {
  const params = {
    period_type: 'monthly',
    start_date: '2025-01-01',
    end_date: '2025-12-31',
    group_by: 'sede_id',
    group_limit: 10,
    filters: {
      status: 1
    }
  }

  try {
    const response = await kpiService.computeKpi(kpiId, params)
    console.log('Resultado del KPI:', response.data)
    
    // Usar los datos en un gráfico
    if (response.data.chart) {
      // El chart está listo para usar con ECharts
      console.log('Configuración del gráfico:', response.data.chart)
    }
  } catch (error) {
    console.error('Error calculando KPI:', error)
  }
}
```

### 3. Configuración de Rutas (Vue 2 - Vue Router v3)

```javascript
// router/index.js (Vue 2)
import Vue from 'vue'
import Router from 'vue-router'
import DashboardsView from '@/views/DashboardsView.vue'
import DashboardView from '@/views/DashboardView.vue'
import KpisView from '@/views/KpisView.vue'

Vue.use(Router)

export default new Router({
  mode: 'history',
  routes: [
    { path: '/', redirect: '/dashboards' },
    { path: '/dashboards', name: 'Dashboards', component: DashboardsView },
    { path: '/dashboard/:id', name: 'Dashboard', component: DashboardView, props: true },
    { path: '/kpis', name: 'KPIs', component: KpisView }
  ]
})
```

## 🔧 Configuración Adicional

### 1. Interceptor de Axios para Autenticación

```javascript
// services/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: process.env.VUE_APP_API_URL || 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Interceptor para agregar token de autenticación
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Interceptor para manejar errores de respuesta
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirigir al login
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
```

### 2. Store de Vuex para Estado Global

```javascript
// store/modules/kpis.js
import { kpiService } from '@/services/kpiService'

const state = {
  kpis: [],
  config: null,
  loading: false,
  error: null
}

const mutations = {
  SET_KPIS(state, kpis) {
    state.kpis = kpis
  },
  SET_CONFIG(state, config) {
    state.config = config
  },
  SET_LOADING(state, loading) {
    state.loading = loading
  },
  SET_ERROR(state, error) {
    state.error = error
  }
}

const actions = {
  async fetchKpis({ commit }) {
    commit('SET_LOADING', true)
    try {
      const response = await kpiService.getKpis()
      commit('SET_KPIS', response.data)
    } catch (error) {
      commit('SET_ERROR', error.message)
    } finally {
      commit('SET_LOADING', false)
    }
  },

  async fetchConfig({ commit }) {
    try {
      const response = await kpiService.getConfig()
      commit('SET_CONFIG', response.data)
    } catch (error) {
      commit('SET_ERROR', error.message)
    }
  }
}

const getters = {
  activeKpis: state => state.kpis.filter(kpi => kpi.is_active),
  kpiById: state => id => state.kpis.find(kpi => kpi.id === id)
}

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters
}
```

## 📝 Notas Importantes

1. **Autenticación**: Todos los endpoints requieren autenticación con Sanctum. Asegúrate de incluir el token en las peticiones.

2. **Validación**: Los requests incluyen validación tanto en el frontend como en el backend.

3. **Manejo de Errores**: Implementa manejo de errores robusto para todas las operaciones.

4. **Performance**: Usa lazy loading para componentes pesados y considera implementar caché para datos que no cambian frecuentemente.

5. **Responsive**: Los componentes están diseñados para ser responsive, pero puedes ajustar los breakpoints según tus necesidades.

6. **Accesibilidad**: Considera agregar atributos ARIA y navegación por teclado para mejorar la accesibilidad.

Esta guía proporciona una base sólida para implementar el sistema de KPIs en Vue.js. Puedes adaptar y extender estos componentes según las necesidades específicas de tu aplicación.
