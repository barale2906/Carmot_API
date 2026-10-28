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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Activo fijo (herramienta, equipo, mueble) ubicado en una sede y, opcionalmente, en un área.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $imagen ruta en el disco public
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

    /** Disco donde se guarda la imagen del activo. */
    public const DISCO_IMAGEN = 'public';

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

    /**
     * Al eliminar el activo de forma definitiva se borra también su imagen del disco.
     */
    protected static function booted(): void
    {
        static::forceDeleted(function (ActActivo $activo) {
            if ($activo->imagen) {
                Storage::disk(self::DISCO_IMAGEN)->delete($activo->imagen);
            }
        });
    }

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

    /**
     * Traslado enviado que aún no se ha recibido en la sede de destino (como máximo uno).
     */
    public function trasladoPendiente(): HasOne
    {
        return $this->hasOne(ActMovimiento::class, 'activo_id')
            ->where('tipo', ActMovimiento::TIPO_TRASLADO)
            ->where('estado_traslado', ActMovimiento::TRASLADO_PENDIENTE);
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
     * Si tiene un traslado pendiente de recepción: mientras tanto no se traslada de nuevo ni cambia de estado.
     */
    public function estaEnTransito(): bool
    {
        return $this->trasladoPendiente()->exists();
    }

    /**
     * URL pública de la imagen, o null si no tiene o el archivo ya no existe.
     */
    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen && Storage::disk(self::DISCO_IMAGEN)->exists($this->imagen)
            ? asset('storage/' . $this->imagen)
            : null;
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'nombre', 'fecha_ingreso', 'valor', 'estado', 'created_at', 'updated_at'];
    }
}
