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
 * @property string $calculation_type Tipo de cálculo ('predefined', 'custom_fields')
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
        'calculation_type', 'base_model', 'default_period_type',
        'default_period_start_date', 'default_period_end_date', 'use_custom_time_range'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'base_model' => 'integer',
        'default_period_start_date' => 'date',
        'default_period_end_date' => 'date',
        'use_custom_time_range' => 'boolean',
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

    /**
     * Relación con KpiFieldRelation (uno a muchos).
     * Un KPI puede tener múltiples relaciones entre campos.
     *
     * @return HasMany
     */
    public function fieldRelations(): HasMany
    {
        return $this->hasMany(KpiFieldRelation::class);
    }

    /**
     * Obtiene el rango de fechas por defecto del KPI.
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    public function getDefaultTimeRange(): array
    {
        if ($this->use_custom_time_range && $this->default_period_start_date && $this->default_period_end_date) {
            return [
                'start' => $this->default_period_start_date,
                'end' => $this->default_period_end_date
            ];
        }

        // Si no tiene rango personalizado, usar el tipo de periodo por defecto
        $now = \Carbon\Carbon::now();

        switch ($this->default_period_type) {
            case 'daily':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay()
                ];
            case 'weekly':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek()
                ];
            case 'monthly':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
            case 'yearly':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear()
                ];
            default:
                // Por defecto, último mes
                return [
                    'start' => $now->copy()->subMonth(),
                    'end' => $now
                ];
        }
    }

    /**
     * Verifica si el KPI tiene un rango de tiempo configurado.
     *
     * @return bool
     */
    public function hasTimeRange(): bool
    {
        return $this->use_custom_time_range || !empty($this->default_period_type);
    }

    /**
     * Obtiene la configuración del modelo base desde config/kpis.php.
     *
     * @return array|null
     */
    public function getBaseModelConfig(): ?array
    {
        if (!$this->base_model) {
            return null;
        }

        return config("kpis.available_kpi_models.{$this->base_model}");
    }

    /**
     * Obtiene la clase del modelo base.
     *
     * @return string|null
     */
    public function getBaseModelClass(): ?string
    {
        $config = $this->getBaseModelConfig();
        return $config['class'] ?? null;
    }

    /**
     * Obtiene el nombre de visualización del modelo base.
     *
     * @return string|null
     */
    public function getBaseModelDisplayName(): ?string
    {
        $config = $this->getBaseModelConfig();
        return $config['display_name'] ?? null;
    }

    /**
     * Obtiene los campos permitidos del modelo base.
     *
     * @return array
     */
    public function getBaseModelFields(): array
    {
        $config = $this->getBaseModelConfig();
        return $config['fields'] ?? [];
    }

    /**
     * Verifica si el modelo base está configurado correctamente.
     *
     * @return bool
     */
    public function hasValidBaseModel(): bool
    {
        $config = $this->getBaseModelConfig();
        return $config && isset($config['class']) && class_exists($config['class']);
    }
}
