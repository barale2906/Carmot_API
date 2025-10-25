<?php

namespace App\Services;

use App\Models\Dashboard\Kpi;
use App\Models\Dashboard\DashboardCard;
use App\Services\DynamicFilterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Servicio ChartDataService
 *
 * Maneja la obtención y procesamiento de datos para gráficos de KPIs.
 * Proporciona métodos para generar datos agrupados y formateados para diferentes tipos de gráficos.
 *
 * @package App\Services
 */
class ChartDataService
{
    protected $dynamicFilterService;

    public function __construct(DynamicFilterService $dynamicFilterService)
    {
        $this->dynamicFilterService = $dynamicFilterService;
    }

    /**
     * Obtiene datos para gráficos basándose en un KPI y configuración.
     *
     * @param int $kpiId ID del KPI
     * @param string $groupBy Campo por el cual agrupar los datos
     * @param string $chartType Tipo de gráfico (bar, pie, line, area, scatter)
     * @param array $filters Filtros a aplicar a los datos
     * @return array Datos formateados para el gráfico
     * @throws \InvalidArgumentException Si el KPI no existe o no es válido
     */
    public function getChartData($kpiId, $groupBy, $chartType, $filters = [])
    {
        $kpi = Kpi::find($kpiId);

        if (!$kpi) {
            throw new \InvalidArgumentException("El KPI con ID {$kpiId} no existe.");
        }

        if (!$kpi->hasValidBaseModel()) {
            throw new \InvalidArgumentException("El KPI no tiene un modelo base válido configurado.");
        }

        $modelClass = $kpi->getBaseModelClass();

        try {
            // Aplicar filtros dinámicos
            $query = $this->dynamicFilterService->applyFilters($modelClass, $filters);

            // Agrupar datos según el tipo de gráfico
            $data = $this->groupDataByChartType($query, $groupBy, $chartType, $kpi);

            return [
                'kpi_id' => $kpiId,
                'name' => $kpi->name,
                'chart_type' => $chartType,
                'data' => $data,
                'total' => $this->calculateTotal($data),
                'period' => now()->format('Y-m'),
                'group_by' => $groupBy,
                'filters_applied' => count($filters)
            ];

        } catch (\Exception $e) {
            Log::error("Error obteniendo datos de gráfico", [
                'kpi_id' => $kpiId,
                'group_by' => $groupBy,
                'chart_type' => $chartType,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("Error procesando datos del gráfico: " . $e->getMessage());
        }
    }

    /**
     * Obtiene datos para una tarjeta de dashboard específica.
     *
     * @param int $cardId ID de la tarjeta de dashboard
     * @return array Datos formateados para el gráfico
     * @throws \InvalidArgumentException Si la tarjeta no existe o no tiene configuración válida
     */
    public function getChartDataForCard($cardId)
    {
        $card = DashboardCard::with('kpi')->find($cardId);

        if (!$card) {
            throw new \InvalidArgumentException("La tarjeta con ID {$cardId} no existe.");
        }

        if (!$card->hasChartConfiguration()) {
            throw new \InvalidArgumentException("La tarjeta no tiene configuración de gráfico válida.");
        }

        return $this->getChartData(
            $card->kpi_id,
            $card->getGroupBy(),
            $card->getChartType(),
            $card->getFilters()
        );
    }

    /**
     * Agrupa datos según el tipo de gráfico.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query builder con filtros aplicados
     * @param string $groupBy Campo por el cual agrupar
     * @param string $chartType Tipo de gráfico
     * @param Kpi $kpi Instancia del KPI
     * @return array Datos agrupados y formateados
     */
    private function groupDataByChartType($query, $groupBy, $chartType, $kpi)
    {
        switch ($chartType) {
            case 'bar':
            case 'pie':
                return $this->groupDataForBarPie($query, $groupBy);

            case 'line':
            case 'area':
                return $this->groupDataForLineArea($query, $groupBy);

            case 'scatter':
                return $this->groupDataForScatter($query, $groupBy);

            default:
                return $this->groupDataForBarPie($query, $groupBy);
        }
    }

    /**
     * Agrupa datos para gráficos de barras y tortas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query builder
     * @param string $groupBy Campo de agrupación
     * @return array Datos agrupados
     */
    private function groupDataForBarPie($query, $groupBy)
    {
        $results = $query->selectRaw("{$groupBy} as label, SUM(inscritos) as value")
            ->groupBy($groupBy)
            ->orderBy('value', 'desc')
            ->get();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'label' => $this->getLabelForValue($groupBy, $result->label),
                'value' => (float) $result->value,
                'percentage' => 0 // Se calculará después
            ];
        }

        // Calcular porcentajes
        $total = $this->calculateTotal($data);
        foreach ($data as &$item) {
            $item['percentage'] = $total > 0 ? round(($item['value'] / $total) * 100, 2) : 0;
        }

        return $data;
    }

    /**
     * Agrupa datos para gráficos de líneas y áreas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query builder
     * @param string $groupBy Campo de agrupación (generalmente fecha)
     * @return array Datos agrupados
     */
    private function groupDataForLineArea($query, $groupBy)
    {
        $results = $query->selectRaw("{$groupBy} as label, SUM(inscritos) as value")
            ->groupBy($groupBy)
            ->orderBy($groupBy, 'asc')
            ->get();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'label' => $this->formatDateLabel($result->label),
                'value' => (float) $result->value,
                'date' => $result->label
            ];
        }

        return $data;
    }

    /**
     * Agrupa datos para gráficos de dispersión.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query builder
     * @param string $groupBy Campo de agrupación
     * @return array Datos agrupados
     */
    private function groupDataForScatter($query, $groupBy)
    {
        $results = $query->selectRaw("{$groupBy} as x, SUM(inscritos) as y")
            ->groupBy($groupBy)
            ->get();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'x' => (float) $result->x,
                'y' => (float) $result->y,
                'label' => $this->getLabelForValue($groupBy, $result->x)
            ];
        }

        return $data;
    }

    /**
     * Calcula el total de los datos.
     *
     * @param array $data Datos del gráfico
     * @return float Total calculado
     */
    private function calculateTotal($data)
    {
        return array_sum(array_column($data, 'value'));
    }

    /**
     * Obtiene etiqueta legible para un valor.
     *
     * @param string $field Campo del valor
     * @param mixed $value Valor a convertir
     * @return string Etiqueta legible
     */
    private function getLabelForValue($field, $value)
    {
        // Aquí puedes agregar lógica específica para obtener etiquetas legibles
        // Por ejemplo, si es sede_id, obtener el nombre de la sede
        // Por ahora, devolvemos el valor tal como está
        return (string) $value;
    }

    /**
     * Formatea etiquetas de fecha para gráficos de líneas.
     *
     * @param string $date Fecha en formato string
     * @return string Fecha formateada
     */
    private function formatDateLabel($date)
    {
        try {
            return \Carbon\Carbon::parse($date)->format('M Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Obtiene estadísticas adicionales para los datos del gráfico.
     *
     * @param array $data Datos del gráfico
     * @return array Estadísticas calculadas
     */
    public function getChartStatistics($data)
    {
        if (empty($data)) {
            return [
                'total' => 0,
                'average' => 0,
                'min' => 0,
                'max' => 0,
                'count' => 0
            ];
        }

        $values = array_column($data, 'value');

        return [
            'total' => array_sum($values),
            'average' => round(array_sum($values) / count($values), 2),
            'min' => min($values),
            'max' => max($values),
            'count' => count($values)
        ];
    }

    /**
     * Valida si un campo puede ser usado para agrupación.
     *
     * @param string $modelClass Clase del modelo
     * @param string $field Campo a validar
     * @return bool True si el campo es válido para agrupación
     */
    public function validateGroupByField($modelClass, $field)
    {
        try {
            $model = new $modelClass();
            $tableName = $model->getTable();

            return Schema::hasColumn($tableName, $field);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene campos disponibles para agrupación de un modelo.
     *
     * @param string $modelClass Clase del modelo
     * @return array Lista de campos disponibles
     */
    public function getAvailableGroupByFields($modelClass)
    {
        try {
            $model = new $modelClass();
            $tableName = $model->getTable();
            $columns = Schema::getColumnListing($tableName);

            $fields = [];
            foreach ($columns as $column) {
                $fields[] = [
                    'name' => $column,
                    'type' => Schema::getColumnType($tableName, $column),
                    'display_name' => ucwords(str_replace('_', ' ', $column))
                ];
            }

            return $fields;
        } catch (\Exception $e) {
            return [];
        }
    }
}
