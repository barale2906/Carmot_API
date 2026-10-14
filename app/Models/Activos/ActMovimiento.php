<?php

namespace App\Models\Activos;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro del historial de un activo: ingreso, traslado o cambio de estado.
 *
 * @property int $id
 * @property int $activo_id
 * @property string $tipo ingreso | traslado | cambio_estado
 */
class ActMovimiento extends Model
{
    public const TIPOS = [
        'ingreso'       => 'Ingreso',
        'traslado'      => 'Traslado',
        'cambio_estado' => 'Cambio de estado',
    ];

    protected $table = 'act_movimientos';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(ActActivo::class, 'activo_id');
    }

    public function sedeOrigen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_origen_id');
    }

    public function areaOrigen(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_origen_id');
    }

    public function sedeDestino(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destino_id');
    }

    public function areaDestino(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
