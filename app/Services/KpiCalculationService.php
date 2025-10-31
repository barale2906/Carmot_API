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

        // Determinar si hay denominador configurado; si no, usar 1 como base
        // Consideramos solo la presencia de denominator_model, ya que operation tiene default 'count'
        $hasDenominator = !empty($kpi->denominator_model);

        if ($hasDenominator) {
            $denominator = $this->aggregate(
                $kpi->getDenominatorModelClass(),
                $kpi->denominator_operation,
                $kpi->denominator_field,
                $dateField,
                $start,
                $end,
                $filters,
                // No agrupar denominador: usar denominador escalar para todo el rango
                null,
                0
            );
        } else {
            // Si está agrupado, construir un array con 1.0 por cada clave del numerador; si no, 1.0 escalar
            if (is_array($numerator)) {
                $denominator = [];
                foreach (array_keys($numerator) as $key) {
                    $denominator[$key] = 1.0;
                }
            } else {
                $denominator = 1.0;
            }
        }

        $factor = (float)($kpi->calculation_factor ?? 1);

        // Determinar si debe ignorar el chart_schema guardado
        // Si viene el flag ignore_stored_schema (cualquier valor), NO usar el chart guardado
        // Si NO viene el flag, usar el chart guardado (comportamiento por defecto)
        $ignoreStoredSchema = isset($options['ignore_stored_schema']) &&
                             $options['ignore_stored_schema'] !== null &&
                             $options['ignore_stored_schema'] !== '';

        // Si el flag está presente, NO usar el chart guardado (activar edición)
        // Si el flag NO está presente, usar el chart guardado
        $shouldUseStoredSchema = !$ignoreStoredSchema;

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

            $chart = $this->buildChartFromSeries($kpi, $series, $shouldUseStoredSchema);

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

        // Numerador agrupado y denominador escalar
        if (is_array($numerator) && !is_array($denominator)) {
            $series = [];
            $d = (float)$denominator;
            foreach ($numerator as $key => $numVal) {
                $n = (float)$numVal;
                $v = $d !== 0.0 ? ($n / $d) * $factor : 0.0;
                $series[] = [
                    'group' => (string)$key,
                    'numerator' => $n,
                    'denominator' => $d,
                    'value' => $v,
                ];
            }

            $chart = $this->buildChartFromSeries($kpi, $series, $shouldUseStoredSchema);

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

        $chart = $this->buildChartFromValue($kpi, $value, $shouldUseStoredSchema);

        return [
            'is_grouped' => false,
            'value' => $value,
            'numerator' => $n,
            'denominator' => $d,
            'factor' => $factor,
            'formula' => $kpi->getCalculationFormula(),
            'description' => $kpi->getCalculationDescription(),
            'range' => ['start' => $start, 'end' => $end],
            'chart' => $chart,
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
     * @param bool $shouldUseStoredSchema Si debe usar el schema guardado del KPI
     * @return array<string, mixed>|null
     */
    private function buildChartFromSeries(Kpi $kpi, array $series, bool $shouldUseStoredSchema = true): ?array
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

        // Si hay parámetros dinámicos, NO usar el chart_schema guardado
        // Retornar solo el chart base con los datos nuevos
        if (!$shouldUseStoredSchema) {
            return $base;
        }

        // Si NO hay parámetros dinámicos, usar el chart_schema guardado del KPI si existe
        if (empty($kpi->chart_schema)) {
            return $base;
        }

        $schema = $kpi->getChartSchema();
        if (!is_array($schema) || empty($schema)) {
            return $base;
        }

        // Merge inteligente: preservar datos y categorías generados dinámicamente
        // Solo aplicar estilos y configuraciones del schema
        $merged = array_merge($base, $schema);

        // PRESERVAR datos nuevos en series (NUNCA sobrescribir data)
        if (isset($merged['series'][0])) {
            $merged['series'][0] = array_merge($merged['series'][0], [
                'name' => $kpi->name,
                'type' => $kpi->chart_type,
                'data' => $data, // SIEMPRE usar los datos nuevos generados
            ]);
        } else {
            $merged['series'] = [[
                'name' => $kpi->name,
                'type' => $kpi->chart_type,
                'data' => $data,
            ]];
        }

        // PRESERVAR categorías nuevas en xAxis (NUNCA sobrescribir data)
        if (isset($merged['xAxis']) && is_array($merged['xAxis'])) {
            $merged['xAxis'] = array_merge($merged['xAxis'], [
                'type' => 'category',
                'data' => $categories, // SIEMPRE usar las categorías nuevas generadas
            ]);
        } else {
            $merged['xAxis'] = [
                'type' => 'category',
                'data' => $categories,
            ];
        }

        return $merged;
    }

    /**
     * Construye un gráfico básico cuando el KPI no está agrupado (valor único),
     * utilizando el chart_type del KPI y mezclándolo con chart_schema si existe.
     *
     * @param Kpi $kpi
     * @param float $value
     * @param bool $shouldUseStoredSchema Si debe usar el schema guardado del KPI
     * @return array<string, mixed>|null
     */
    private function buildChartFromValue(Kpi $kpi, float $value, bool $shouldUseStoredSchema = true): ?array
    {
        if (empty($kpi->chart_type)) {
            return null;
        }

        $type = $kpi->chart_type;

        switch ($type) {
            case 'pie':
                $base = [
                    'title' => ['text' => $kpi->name],
                    'tooltip' => ['trigger' => 'item'],
                    'series' => [[
                        'name' => $kpi->name,
                        'type' => 'pie',
                        'radius' => '50%',
                        'data' => [[
                            'name' => $kpi->name,
                            'value' => $value,
                        ]],
                    ]],
                ];
                break;
            case 'line':
            case 'area':
                $series = [
                    'name' => $kpi->name,
                    'type' => 'line',
                    'data' => [$value],
                ];
                if ($type === 'area') {
                    $series['areaStyle'] = new \stdClass();
                }
                $base = [
                    'tooltip' => ['trigger' => 'axis'],
                    'xAxis' => [
                        'type' => 'category',
                        'data' => [$kpi->name],
                    ],
                    'yAxis' => [
                        'type' => 'value',
                    ],
                    'series' => [ $series ],
                    'legend' => ['data' => [$kpi->name]],
                    'title' => ['text' => $kpi->name],
                ];
                break;
            case 'bar':
            default:
                $base = [
                    'tooltip' => ['trigger' => 'axis'],
                    'xAxis' => [
                        'type' => 'category',
                        'data' => [$kpi->name],
                    ],
                    'yAxis' => [
                        'type' => 'value',
                    ],
                    'series' => [[
                        'name' => $kpi->name,
                        'type' => 'bar',
                        'data' => [$value],
                    ]],
                    'legend' => ['data' => [$kpi->name]],
                    'title' => ['text' => $kpi->name],
                ];
                break;
        }

        // Si hay parámetros dinámicos, NO usar el chart_schema guardado
        // Retornar solo el chart base con el valor nuevo
        if (!$shouldUseStoredSchema) {
            return $base;
        }

        // Si NO hay parámetros dinámicos, usar el chart_schema guardado del KPI si existe
        if (empty($kpi->chart_schema)) {
            return $base;
        }

        $schema = $kpi->getChartSchema();
        if (!is_array($schema) || empty($schema)) {
            return $base;
        }

        $merged = array_merge($base, $schema);

        // PRESERVAR el valor nuevo en series (NUNCA sobrescribir data)
        if (isset($merged['series'][0])) {
            // Preservar data original del base
            $originalData = $base['series'][0]['data'] ?? [$value];
            $merged['series'][0] = array_merge($merged['series'][0], [
                'data' => $originalData, // SIEMPRE usar el valor nuevo generado
            ]);
        }

        if (isset($schema['xAxis']) && is_array($schema['xAxis']) && isset($base['xAxis'])) {
            $merged['xAxis'] = array_merge($base['xAxis'], $schema['xAxis']);
        }
        if (isset($schema['yAxis']) && is_array($schema['yAxis']) && isset($base['yAxis'])) {
            $merged['yAxis'] = array_merge($base['yAxis'], $schema['yAxis']);
        }

        return $merged;
    }
}


