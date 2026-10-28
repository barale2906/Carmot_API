<?php

namespace App\Models\Crm;

use App\Models\User;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use App\Traits\HasSeguimientoScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seguimiento extends Model
{
    use HasFactory, HasFilterScopes, HasRelationScopes, HasSortingScopes, HasSeguimientoScopes, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Conversión de atributos: la fecha del seguimiento se expone como Y-m-d (B13).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha' => 'date',
        'estado_referido' => 'integer',
    ];

    /**
     * Cada seguimiento guarda el estado en que quedó el lead, para leer su evolución en la
     * bitácora. Si no se indica, toma el estado vigente del lead (ya actualizado por quien
     * registra el seguimiento: {@see Referido::registrarSeguimiento}, marcarMatriculado...).
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (Seguimiento $seguimiento) {
            $seguimiento->estado_referido ??= Referido::whereKey($seguimiento->referido_id)->value('status');
        });
    }

    /**
     * Texto del estado del lead registrado en el seguimiento; null en seguimientos anteriores a este registro.
     *
     * @return string|null
     */
    public function getEstadoReferidoTextAttribute(): ?string
    {
        return $this->estado_referido === null ? null : Referido::getStatusText($this->estado_referido);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento específicos de seguimientos.
     * Sobrescribe el método del trait HasSortingScopes.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'fecha',
            'seguimiento',
            'referido_id',
            'seguidor_id',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return [
            'referido',
            'seguidor'
        ];
    }

    /**
     * Obtiene las relaciones que se cargan por defecto.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['referido', 'seguidor'];
    }

    /**
     * Relación con el referido al que pertenece este seguimiento.
     *
     * @return BelongsTo
     */
    public function referido() : BelongsTo
    {
        return $this->BelongsTo(Referido::class);
    }

    /**
     * Relación con el usuario que realizó el seguimiento.
     *
     * @return BelongsTo
     */
    public function seguidor() : BelongsTo
    {
        return $this->BelongsTo(User::class);
    }

}
