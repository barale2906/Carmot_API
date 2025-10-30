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
 * Cada KPI se calcula como: (numerador / denominador) * factor_calculo
 *
 * @property int $id Identificador único del KPI
 * @property string $name Nombre del KPI (ej. "Ventas Totales", "Nuevos Clientes")
 * @property string $code Código único del KPI (ej. "total_sales", "new_customers")
 * @property string|null $description Descripción detallada del KPI
 * @property string|null $unit Unidad de medida (ej. "USD", "%", "unidades")
 * @property bool $is_active Indica si el KPI está activo
 * @property int|null $numerator_model ID del modelo para el numerador (referencia a config/kpis.php)
 * @property string|null $numerator_field Campo del numerador
 * @property string $numerator_operation Operación del numerador (count, sum, avg, max, min)
 * @property int|null $denominator_model ID del modelo para el denominador (referencia a config/kpis.php)
 * @property string|null $denominator_field Campo del denominador
 * @property string $denominator_operation Operación del denominador (count, sum, avg, max, min)
 * @property float $calculation_factor Factor de cálculo (*1, *100, *1000, etc.)
 * @property float|null $target_value Meta del indicador (nullable)
 * @property string $date_field Campo de fecha para control (default: created_at)
 * @property string $period_type Tipo de periodo (daily, weekly, monthly, quarterly, yearly)
 * @property string|null $chart_type Tipo de gráfico (bar, pie, line, area, scatter)
 * @property array|null $chart_schema JSON con esquema del gráfico para ECharts
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
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
        'numerator_model', 'numerator_field', 'numerator_operation',
        'denominator_model', 'denominator_field', 'denominator_operation',
        'calculation_factor', 'target_value', 'date_field', 'period_type',
        'chart_type', 'chart_schema'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'numerator_model' => 'integer',
        'denominator_model' => 'integer',
        'calculation_factor' => 'float',
        'target_value' => 'float',
        'chart_schema' => 'array',
    ];

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
     * Obtiene el rango de fechas por defecto del KPI.
     * Según el documento, si no hay definición de período, toma la fecha inmediatamente anterior
     * y hace una regresión de tiempo con base en el período definido.
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    public function getDefaultTimeRange(): array
    {
        // Tomar el día anterior como fecha final y retroceder el período correspondiente
        $yesterday = \Carbon\Carbon::now()->subDay();

        switch ($this->period_type) {
            case 'daily':
                return [
                    'start' => $yesterday->copy()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay()
                ];
            case 'weekly':
                return [
                    'start' => $yesterday->copy()->subWeek()->addDay()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay()
                ];
            case 'monthly':
                return [
                    'start' => $yesterday->copy()->subMonth()->addDay()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay()
                ];
            case 'quarterly':
                return [
                    'start' => $yesterday->copy()->subMonths(3)->addDay()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay()
                ];
            case 'yearly':
                return [
                    'start' => $yesterday->copy()->subYear()->addDay()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay()
                ];
            default:
                // Por defecto, último año (más amplio para incluir datos de migraciones/seeders)
                return [
                    'start' => $yesterday->copy()->subYear()->addDay()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay()
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
        return !empty($this->period_type);
    }

    /**
     * Obtiene la configuración del modelo numerador desde config/kpis.php.
     *
     * @return array|null
     */
    public function getNumeratorModelConfig(): ?array
    {
        if (!$this->numerator_model) {
            return null;
        }

        return config("kpis.available_kpi_models.{$this->numerator_model}");
    }

    /**
     * Obtiene la configuración del modelo denominador desde config/kpis.php.
     *
     * @return array|null
     */
    public function getDenominatorModelConfig(): ?array
    {
        if (!$this->denominator_model) {
            return null;
        }

        return config("kpis.available_kpi_models.{$this->denominator_model}");
    }

    /**
     * Obtiene la clase del modelo numerador.
     *
     * @return string|null
     */
    public function getNumeratorModelClass(): ?string
    {
        $config = $this->getNumeratorModelConfig();
        return $config['class'] ?? null;
    }

    /**
     * Obtiene la clase del modelo denominador.
     *
     * @return string|null
     */
    public function getDenominatorModelClass(): ?string
    {
        $config = $this->getDenominatorModelConfig();
        return $config['class'] ?? null;
    }

    /**
     * Obtiene el nombre de visualización del modelo numerador.
     *
     * @return string|null
     */
    public function getNumeratorModelDisplayName(): ?string
    {
        $config = $this->getNumeratorModelConfig();
        return $config['display_name'] ?? null;
    }

    /**
     * Obtiene el nombre de visualización del modelo denominador.
     *
     * @return string|null
     */
    public function getDenominatorModelDisplayName(): ?string
    {
        $config = $this->getDenominatorModelConfig();
        return $config['display_name'] ?? null;
    }

    /**
     * Obtiene los campos permitidos del modelo numerador.
     *
     * @return array
     */
    public function getNumeratorModelFields(): array
    {
        $config = $this->getNumeratorModelConfig();
        return $config['fields'] ?? [];
    }

    /**
     * Obtiene los campos permitidos del modelo denominador.
     *
     * @return array
     */
    public function getDenominatorModelFields(): array
    {
        $config = $this->getDenominatorModelConfig();
        return $config['fields'] ?? [];
    }

    /**
     * Obtiene el nombre del campo numerador para mostrar.
     *
     * @return string|null
     */
    public function getNumeratorFieldDisplayName(): ?string
    {
        $fields = $this->getNumeratorModelFields();
        $value = $fields[$this->numerator_field] ?? $this->numerator_field;
        return is_array($value) ? ($value['label'] ?? $this->numerator_field) : $value;
    }

    /**
     * Obtiene el nombre del campo denominador para mostrar.
     *
     * @return string|null
     */
    public function getDenominatorFieldDisplayName(): ?string
    {
        $fields = $this->getDenominatorModelFields();
        $value = $fields[$this->denominator_field] ?? $this->denominator_field;
        return is_array($value) ? ($value['label'] ?? $this->denominator_field) : $value;
    }

    /**
     * Verifica si el modelo numerador está configurado correctamente.
     *
     * @return bool
     */
    public function hasValidNumeratorModel(): bool
    {
        $config = $this->getNumeratorModelConfig();
        return $config && isset($config['class']) && class_exists($config['class']);
    }

    /**
     * Verifica si el modelo denominador está configurado correctamente.
     *
     * @return bool
     */
    public function hasValidDenominatorModel(): bool
    {
        $config = $this->getDenominatorModelConfig();
        return $config && isset($config['class']) && class_exists($config['class']);
    }

    /**
     * Verifica si el campo numerador es válido para el modelo seleccionado.
     *
     * @return bool
     */
    public function hasValidNumeratorField(): bool
    {
        if (!$this->numerator_field || !$this->hasValidNumeratorModel()) {
            return false;
        }

        $fields = $this->getNumeratorModelFields();
        return array_key_exists($this->numerator_field, $fields);
    }

    /**
     * Verifica si el campo denominador es válido para el modelo seleccionado.
     *
     * @return bool
     */
    public function hasValidDenominatorField(): bool
    {
        if (!$this->denominator_field || !$this->hasValidDenominatorModel()) {
            return false;
        }

        $fields = $this->getDenominatorModelFields();
        return array_key_exists($this->denominator_field, $fields);
    }

    /**
     * Verifica si la operación numerador es válida.
     *
     * @return bool
     */
    public function hasValidNumeratorOperation(): bool
    {
        $validOperations = ['count', 'sum', 'avg', 'max', 'min'];
        return in_array($this->numerator_operation, $validOperations);
    }

    /**
     * Verifica si la operación denominador es válida.
     *
     * @return bool
     */
    public function hasValidDenominatorOperation(): bool
    {
        $validOperations = ['count', 'sum', 'avg', 'max', 'min'];
        return in_array($this->denominator_operation, $validOperations);
    }

    /**
     * Verifica si el KPI está completamente configurado y es válido.
     *
     * @return bool
     */
    public function isFullyConfigured(): bool
    {
        // Configuración mínima: numerador válido siempre
        $numeratorOk = $this->hasValidNumeratorModel() &&
                       $this->hasValidNumeratorField() &&
                       $this->hasValidNumeratorOperation();

        // Denominador opcional: si NO hay modelo, se considera KPI de solo numerador
        // Nota: denominator_operation tiene default 'count', por eso no lo usamos para decidir
        $hasDenominatorData = !empty($this->denominator_model);

        if (!$hasDenominatorData) {
            return $numeratorOk;
        }

        // Si hay datos de denominador, entonces deben ser válidos
        $denominatorOk = $this->hasValidDenominatorModel() &&
                         $this->hasValidDenominatorField() &&
                         $this->hasValidDenominatorOperation();

        return $numeratorOk && $denominatorOk;
    }

    /**
     * Verifica si el KPI tiene una meta definida.
     *
     * @return bool
     */
    public function hasTarget(): bool
    {
        return !is_null($this->target_value);
    }

    /**
     * Obtiene el campo de fecha para control, por defecto 'created_at'.
     *
     * @return string
     */
    public function getDateField(): string
    {
        return $this->date_field ?? 'created_at';
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
     * Verifica si el KPI tiene configuración de gráfico.
     *
     * @return bool
     */
    public function hasChartConfiguration(): bool
    {
        return !empty($this->chart_type) && !empty($this->chart_schema);
    }

    /**
     * Obtiene una descripción legible de cómo se calcula el KPI.
     *
     * @return string
     */
    public function getCalculationDescription(): string
    {
        if (!$this->isFullyConfigured()) {
            return 'KPI no configurado completamente';
        }

        $numeratorModel = $this->getNumeratorModelDisplayName();
        $numeratorField = $this->getNumeratorFieldDisplayName();
        $numeratorOp = strtoupper($this->numerator_operation);

        $hasDenominator = !empty($this->denominator_model);

        if (!$hasDenominator) {
            $description = "{$numeratorOp} de '{$numeratorField}' en {$numeratorModel}";
            if ($this->calculation_factor != 1) {
                $description .= " × {$this->calculation_factor}";
            }
            return $description;
        }

        $denominatorModel = $this->getDenominatorModelDisplayName();
        $denominatorField = $this->getDenominatorFieldDisplayName();
        $denominatorOp = strtoupper($this->denominator_operation);

        $description = "({$numeratorOp} de '{$numeratorField}' en {$numeratorModel}) / ";
        $description .= "({$denominatorOp} de '{$denominatorField}' en {$denominatorModel})";

        if ($this->calculation_factor != 1) {
            $description .= " × {$this->calculation_factor}";
        }

        return $description;
    }

    /**
     * Obtiene la fórmula matemática del KPI.
     *
     * @return string
     */
    public function getCalculationFormula(): string
    {
        if (!$this->isFullyConfigured()) {
            return 'KPI no configurado';
        }

        $numerator = "{$this->numerator_operation}({$this->numerator_field})";

        $hasDenominator = !empty($this->denominator_model);

        if (!$hasDenominator) {
            $formula = $numerator;
            if ($this->calculation_factor != 1) {
                $formula .= " × {$this->calculation_factor}";
            }
            return $formula;
        }

        $denominator = "{$this->denominator_operation}({$this->denominator_field})";
        $formula = "({$numerator} / {$denominator})";

        if ($this->calculation_factor != 1) {
            $formula .= " × {$this->calculation_factor}";
        }

        return $formula;
    }
}
