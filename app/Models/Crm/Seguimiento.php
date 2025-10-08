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
