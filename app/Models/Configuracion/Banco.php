<?php

namespace App\Models\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasBancoFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Banco
 *
 * Catálogo de entidades bancarias usadas en pagos por transferencia.
 *
 * @property int $id Identificador único
 * @property string $nombre Nombre completo del banco
 * @property string|null $codigo Código o NIT del banco
 * @property int $status 0=Inactivo, 1=Activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Financiero\ReciboPago\ReciboPagoMedioPago[] $mediosPago
 */
class Banco extends Model
{
    use HasFactory, SoftDeletes, HasActiveStatus, HasBancoFilterScopes, HasSortingScopes;

    protected $table = 'bancos';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Medios de pago por transferencia que referencian este banco.
     *
     * @return HasMany
     */
    public function mediosPago(): HasMany
    {
        return $this->hasMany(\App\Models\Financiero\ReciboPago\ReciboPagoMedioPago::class);
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'codigo', 'status', 'created_at', 'updated_at'];
    }
}
