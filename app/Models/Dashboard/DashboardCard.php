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
 * @property string|null $period_start_date Fecha de inicio del periodo personalizado
 * @property string|null $period_end_date Fecha de fin del periodo personalizado
 * @property array|null $custom_field_values Valores personalizados para campos del KPI
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
        'period_start_date', 'period_end_date', 'custom_field_values', 'order'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'custom_field_values' => 'array',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
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
}
