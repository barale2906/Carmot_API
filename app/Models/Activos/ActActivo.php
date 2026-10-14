<?php

namespace App\Models\Activos;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Traits\HasActivoFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Activo fijo (herramienta, equipo, mueble) ubicado en una sede y, opcionalmente, en un área.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property int $categoria_id
 * @property int $sede_id
 * @property int|null $area_id
 * @property int|null $responsable_id
 * @property \Illuminate\Support\Carbon $fecha_ingreso
 * @property string $valor
 * @property string $estado operativo | mantenimiento | disponible | baja
 */
class ActActivo extends Model
{
    use HasFactory, SoftDeletes, HasActivoFilterScopes, HasSortingScopes;

    public const ESTADOS = [
        'operativo'     => 'Operativo',
        'mantenimiento' => 'En mantenimiento',
        'disponible'    => 'Disponible',
        'baja'          => 'De baja',
    ];

    /** Un activo dado de baja ya no se traslada ni cambia de estado. */
    public const ESTADO_BAJA = 'baja';

    protected $table = 'act_activos';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Igual al default de la columna, para que el modelo recién creado ya lo conozca. */
    protected $attributes = [
        'estado' => 'operativo',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'valor'         => 'decimal:2',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ActCategoria::class, 'categoria_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Historial del activo, del movimiento más reciente al más antiguo.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(ActMovimiento::class, 'activo_id')->latest('fecha')->latest('id');
    }

    public function getEstadoTextoAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function estaDeBaja(): bool
    {
        return $this->estado === self::ESTADO_BAJA;
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['codigo', 'nombre', 'fecha_ingreso', 'valor', 'estado', 'created_at', 'updated_at'];
    }
}
