<?php

namespace App\Models\Dashboard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo DashboardCard
 *
 * Representa una tarjeta individual dentro de un dashboard.
 * Cada tarjeta muestra un KPI específico con su configuración visual y temporal.
 *
 * @property int $id Identificador único de la tarjeta
 * @property int $dashboard_id ID del dashboard al que pertenece
 * @property int $kpi_id ID del KPI que muestra esta tarjeta
 * @property string|null $title Título personalizado de la tarjeta
 * @property string|null $background_color Color de fondo de la tarjeta (ej. "#FF0000")
 * @property string|null $text_color Color del texto de la tarjeta (ej. "#FFFFFF")
 * @property int $width Ancho de la tarjeta en el grid (1, 2, 3, etc.)
 * @property int $height Alto de la tarjeta en el grid (1, 2, 3, etc.)
 * @property int $x_position Posición horizontal en el grid
 * @property int $y_position Posición vertical en el grid
 * @property string|null $period_type Tipo de periodo (daily, weekly, monthly, yearly)
 *
 * @property string|null $chart_type Tipo de gráfico (bar, pie, line, area, scatter)
 * @property array|null $chart_parameters Parámetros específicos del gráfico
 * @property array|null $chart_schema JSON con esquema del gráfico para ECharts
 * @property string|null $group_by Campo por el cual agrupar los datos
 * @property array|null $filters Filtros dinámicos a aplicar a los datos
 * @property int $order Orden de visualización de la tarjeta
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Dashboard\Dashboard $dashboard Dashboard al que pertenece
 * @property-read \App\Models\Dashboard\Kpi $kpi KPI que muestra esta tarjeta
 */
class DashboardCard extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que pueden ser asignados masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'dashboard_id', 'kpi_id', 'title', 'background_color', 'text_color',
        'width', 'height', 'x_position', 'y_position', 'period_type',
        'order', 'chart_type', 'chart_parameters', 'chart_schema', 'group_by', 'filters'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'chart_parameters' => 'array',
        'chart_schema' => 'array',
        'filters' => 'array',
    ];

    /**
     * Relación con Dashboard (muchos a uno).
     * Una tarjeta pertenece a un dashboard.
     *
     * @return BelongsTo
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * Relación con Kpi (muchos a uno).
     * Una tarjeta muestra un KPI específico.
     *
     * @return BelongsTo
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    /**
     * Verifica si la tarjeta tiene configuración de gráfico.
     *
     * @return bool True si tiene configuración de gráfico
     */
    public function hasChartConfiguration(): bool
    {
        return !empty($this->chart_type) && !empty($this->group_by);
    }

    /**
     * Obtiene los filtros aplicados a la tarjeta.
     *
     * @return array Array de filtros o array vacío si no hay filtros
     */
    public function getFilters(): array
    {
        return $this->filters ?? [];
    }

    /**
     * Obtiene los parámetros del gráfico.
     *
     * @return array Array de parámetros o array vacío si no hay parámetros
     */
    public function getChartParameters(): array
    {
        return $this->chart_parameters ?? [];
    }

    /**
     * Verifica si la tarjeta tiene filtros aplicados.
     *
     * @return bool True si tiene filtros
     */
    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    /**
     * Obtiene el tipo de gráfico con valores por defecto.
     *
     * @return string Tipo de gráfico o 'bar' por defecto
     */
    public function getChartType(): string
    {
        return $this->chart_type ?? 'bar';
    }

    /**
     * Obtiene el campo de agrupación.
     *
     * @return string|null Campo de agrupación o null si no está definido
     */
    public function getGroupBy(): ?string
    {
        return $this->group_by;
    }

    /**
     * Verifica si el tipo de gráfico es válido.
     *
     * @return bool True si el tipo de gráfico es válido
     */
    public function hasValidChartType(): bool
    {
        $validTypes = ['bar', 'pie', 'line', 'area', 'scatter'];
        return in_array($this->chart_type, $validTypes);
    }

    /**
     * Obtiene el esquema del gráfico con valores por defecto.
     *
     * @return array
     */
    public function getChartSchema(): array
    {
        return $this->chart_schema ?? [];
    }

    /**
     * Verifica si la tarjeta tiene esquema de gráfico configurado.
     *
     * @return bool True si tiene esquema de gráfico
     */
    public function hasChartSchema(): bool
    {
        return !empty($this->chart_schema);
    }

    /**
     * Obtiene el título de la tarjeta o el nombre del KPI como fallback.
     *
     * @return string
     */
    public function getDisplayTitle(): string
    {
        return $this->title ?? $this->kpi->name ?? 'Sin título';
    }

    /**
     * Obtiene el color de fondo con valor por defecto.
     *
     * @return string
     */
    public function getBackgroundColor(): string
    {
        return $this->background_color ?? '#ffffff';
    }

    /**
     * Obtiene el color del texto con valor por defecto.
     *
     * @return string
     */
    public function getTextColor(): string
    {
        return $this->text_color ?? '#000000';
    }

    // El periodo por defecto se toma del KPI o será provisto por el usuario en la vista.
    // Para compatibilidad, se puede usar $this->kpi->getDefaultTimeRange() desde el servicio/controlador.
}
