<?php

namespace App\Services;

use App\Models\Dashboard\Kpi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Servicio de cálculo de KPIs
 *
 * Encapsula la lógica para:
 * - Resolver rango temporal
 * - Ejecutar agregaciones (count/sum/avg/max/min)
 * - Aplicar filtros y agrupaciones
 * - Construir opciones básicas de ECharts a partir de series
 */
class KpiCalculationService
{
    /**
     * Calcula el valor de un KPI basado en su configuración.
     *
     * @param Kpi $kpi
     * @param array $options [
     *   'start_date' => 'Y-m-d',
     *   'end_date' => 'Y-m-d',
     *   'period_type' => 'daily|weekly|monthly|quarterly|yearly',
     *   'date_field' => 'created_at',
     *   'filters' => [campo => valor],
     *   'group_by' => 'campo_para_agrupar',
     *   'group_limit' => int
     * ]
     * @return array<string, mixed>
     */
    public function compute(Kpi $kpi, array $options = []): array
    {
        if (!$kpi->isFullyConfigured()) {
            throw new \InvalidArgumentException('El KPI no está completamente configurado.');
        }

        [$start, $end] = $this->resolveRange($kpi, $options);
        $dateField = $options['date_field'] ?? $kpi->getDateField();
        $filters = $options['filters'] ?? [];
        $groupBy = $options['group_by'] ?? null;
        $groupLimit = (int)($options['group_limit'] ?? 0);

        $numerator = $this->aggregate(
            $kpi->getNumeratorModelClass(),
            $kpi->numerator_operation,
            $kpi->numerator_field,
            $dateField,
            $start,
            $end,
            $filters,
            $groupBy,
            $groupLimit
        );

        $denominator = $this->aggregate(
            $kpi->getDenominatorModelClass(),
            $kpi->denominator_operation,
            $kpi->denominator_field,
            $dateField,
            $start,
            $end,
            $filters,
            $groupBy,
            $groupLimit
        );

        $factor = (float)($kpi->calculation_factor ?? 1);

        // Si hay group_by, construir series por grupo
        if (is_array($numerator) && is_array($denominator)) {
            $series = [];
            $keys = array_unique(array_merge(array_keys($numerator), array_keys($denominator)));
            foreach ($keys as $key) {
                $n = (float)($numerator[$key] ?? 0.0);
                $d = (float)($denominator[$key] ?? 0.0);
                $v = $d !== 0.0 ? ($n / $d) * $factor : 0.0;
                $series[] = [
                    'group' => $key,
                    'numerator' => $n,
                    'denominator' => $d,
                    'value' => $v,
                ];
            }

            $chart = $this->buildChartFromSeries($kpi, $series);

            return [
                'is_grouped' => true,
                'factor' => $factor,
                'formula' => $kpi->getCalculationFormula(),
                'description' => $kpi->getCalculationDescription(),
                'range' => ['start' => $start, 'end' => $end],
                'series' => $series,
                'chart' => $chart,
            ];
        }

        // Agregación simple (escalares)
        $n = (float)$numerator;
        $d = (float)$denominator;
        $value = $d !== 0.0 ? ($n / $d) * $factor : 0.0;

        return [
            'is_grouped' => false,
            'value' => $value,
            'numerator' => $n,
            'denominator' => $d,
            'factor' => $factor,
            'formula' => $kpi->getCalculationFormula(),
            'description' => $kpi->getCalculationDescription(),
            'range' => ['start' => $start, 'end' => $end],
        ];
    }

    /**
     * Ejecuta una agregación sobre un modelo/clase.
     *
     * @param string|null $modelClass Clase del modelo Eloquent
     * @param string $operation Operación de agregación (count|sum|avg|max|min)
     * @param string|null $field Campo al que aplicar la operación
     * @param string $dateField Campo de fecha para filtrado temporal
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @param array<string, mixed> $filters Filtros de igualdad
     * @param string|null $groupBy Campo para agrupar resultados
     * @param int $groupLimit Límite de grupos
     * @return float|array<string, float>
     */
    private function aggregate(?string $modelClass, string $operation, ?string $field, string $dateField, Carbon $start, Carbon $end, array $filters, ?string $groupBy = null, int $groupLimit = 0): float|array
    {
        if (!$modelClass || !class_exists($modelClass)) {
            return 0.0;
        }

        /** @var Builder $query */
        $query = call_user_func([$modelClass, 'query']);

        // Fecha
        if ($dateField) {
            $query->whereBetween($dateField, [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
        }

        // Filtros simples de igualdad
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query->where($key, $value);
        }

        // Agrupación
        if ($groupBy) {
            $aggExpr = match ($operation) {
                'count' => ($field ? "COUNT($field)" : 'COUNT(*)'),
                'sum'   => "SUM($field)",
                'avg'   => "AVG($field)",
                'max'   => "MAX($field)",
                'min'   => "MIN($field)",
                default => null,
            };

            if ($aggExpr === null) {
                return [];
            }

            $rows = $query
                ->select($groupBy)
                ->selectRaw("$aggExpr as agg_value")
                ->groupBy($groupBy)
                ->when($groupLimit > 0, fn ($q) => $q->limit($groupLimit))
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $result[(string)$row->{$groupBy}] = (float)$row->agg_value;
            }

            return $result;
        }

        switch ($operation) {
            case 'count':
                return (float)$query->count($field ?: '*');
            case 'sum':
                return (float)$query->sum($field);
            case 'avg':
                return (float)$query->avg($field);
            case 'max':
                $res = $query->max($field);
                return is_null($res) ? 0.0 : (float)$res;
            case 'min':
                $res = $query->min($field);
                return is_null($res) ? 0.0 : (float)$res;
            default:
                return 0.0;
        }
    }

    /**
     * Resuelve el rango de fechas con prioridad: options > KPI default.
     *
     * @param Kpi $kpi
     * @param array<string, mixed> $options
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Kpi $kpi, array $options): array
    {
        if (!empty($options['start_date']) && !empty($options['end_date'])) {
            return [
                Carbon::parse($options['start_date'])->startOfDay(),
                Carbon::parse($options['end_date'])->endOfDay(),
            ];
        }

        if (!empty($options['period_type'])) {
            $tmp = clone $kpi;
            $tmp->period_type = $options['period_type'];
            $range = $tmp->getDefaultTimeRange();
            return [$range['start'], $range['end']];
        }

        $range = $kpi->getDefaultTimeRange();
        return [$range['start'], $range['end']];
    }

    /**
     * Construye estructura básica de opciones de ECharts a partir de series agrupadas.
     * Retorna null si no hay información suficiente de gráfico.
     *
     * @param Kpi $kpi
     * @param array<int, array{group: string, numerator: float, denominator: float, value: float}> $series
     * @return array<string, mixed>|null
     */
    private function buildChartFromSeries(Kpi $kpi, array $series): ?array
    {
        if (empty($kpi->chart_type)) {
            return null;
        }

        $categories = array_map(fn ($item) => (string)$item['group'], $series);
        $data = array_map(fn ($item) => (float)$item['value'], $series);

        $base = [
            'tooltip' => ['trigger' => 'axis'],
            'xAxis' => [
                'type' => 'category',
                'data' => $categories,
            ],
            'yAxis' => [
                'type' => 'value',
            ],
            'series' => [[
                'name' => $kpi->name,
                'type' => $kpi->chart_type,
                'data' => $data,
            ]],
            'legend' => ['data' => [$kpi->name]],
            'title' => ['text' => $kpi->name],
        ];

        // Mezclar con chart_schema definido en el KPI, dándole prioridad a este último
        $schema = $kpi->getChartSchema();
        if (!is_array($schema) || empty($schema)) {
            return $base;
        }

        // Merge superficial (nivel 1) y específico para series[0]
        $merged = array_merge($base, $schema);

        if (isset($schema['series']) && is_array($schema['series'])) {
            // Si el schema define series, respetarlas pero asegurando data/categorías del cálculo
            $first = $schema['series'][0] ?? [];
            $merged['series'][0] = array_merge([
                'name' => $kpi->name,
                'type' => $kpi->chart_type,
                'data' => $data,
            ], $first);
        }

        // xAxis merge
        if (isset($schema['xAxis']) && is_array($schema['xAxis'])) {
            $merged['xAxis'] = array_merge([
                'type' => 'category',
                'data' => $categories,
            ], $schema['xAxis']);
        }

        // yAxis merge
        if (isset($schema['yAxis']) && is_array($schema['yAxis'])) {
            $merged['yAxis'] = array_merge(['type' => 'value'], $schema['yAxis']);
        }

        return $merged;
    }
}


