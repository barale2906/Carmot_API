<?php

namespace App\Models\Dashboard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Kpi
 *
 * Representa un indicador clave de rendimiento (KPI) en el sistema.
 * Los KPIs definen métricas que se pueden calcular y mostrar en dashboards.
 *
 * @property int $id Identificador único del KPI
 * @property string $name Nombre del KPI (ej. "Ventas Totales", "Nuevos Clientes")
 * @property string $code Código único del KPI (ej. "total_sales", "new_customers")
 * @property string|null $description Descripción detallada del KPI
 * @property string|null $unit Unidad de medida (ej. "USD", "%", "unidades")
 * @property bool $is_active Indica si el KPI está activo
 * @property string $calculation_type Tipo de cálculo ('predefined', 'custom_fields', 'sql_query')
 * @property string|null $base_model Modelo Eloquent base para el cálculo
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Dashboard\KpiField[] $kpiFields Campos de configuración del KPI
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Dashboard\DashboardCard[] $dashboardCards Tarjetas de dashboard que usan este KPI
 */
class Kpi extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que pueden ser asignados masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name', 'code', 'description', 'unit', 'is_active',
        'calculation_type', 'base_model'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con KpiField (uno a muchos).
     * Un KPI puede tener múltiples campos de configuración.
     *
     * @return HasMany
     */
    public function kpiFields(): HasMany
    {
        return $this->hasMany(KpiField::class);
    }

    /**
     * Relación con DashboardCard (uno a muchos).
     * Un KPI puede ser usado en múltiples tarjetas de dashboard.
     *
     * @return HasMany
     */
    public function dashboardCards(): HasMany
    {
        return $this->hasMany(DashboardCard::class);
    }
}
