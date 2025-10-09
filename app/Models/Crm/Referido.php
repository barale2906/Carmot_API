<?php

namespace App\Models\Crm;

use App\Models\Academico\Curso;
use App\Models\User;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Referido extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;
    use HasFilterScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Obtiene los atributos que deben ser convertidos a fechas.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    // RElación uno a mucho seguimiento a los referidos
    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class);
    }

    // RElación uno a mucho agendamiento a los referidos
    public function agendamientos(): HasMany
    {
        return $this->hasMany(Agenda::class);
    }

    //Aspirantes a cada curso
    public function curso() : BelongsTo
    {
        return $this->BelongsTo(Curso::class);
    }

    //Gestores que registran los referidos
    public function gestor() : BelongsTo
    {
        return $this->BelongsTo(User::class);
    }

    /**
     * Determina si el referido está matriculado.
     *
     * @return bool
     */
    public function isMatriculado(): bool
    {
        return $this->status === 3;
    }

    /**
     * Determina si el referido puede ser eliminado.
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return $this->seguimientos()->count() === 0;
    }

    /**
     * Obtiene los días transcurridos desde la creación.
     *
     * @return int
     */
    public function getDaysSinceCreated(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Obtiene el siguiente estado sugerido basado en el estado actual.
     *
     * @return int|null
     */
    public function getNextSuggestedStatus(): ?int
    {
        return match ($this->status) {
            0 => 1, // Creado -> Interesado
            1 => 2, // Interesado -> Pendiente por matricular
            2 => 3, // Pendiente -> Matriculado
            default => null,
        };
    }

    /**
     * Obtiene el texto del siguiente estado sugerido.
     *
     * @return string|null
     */
    public function getNextSuggestedStatusText(): ?string
    {
        $nextStatus = $this->getNextSuggestedStatus();
        return $nextStatus ? self::getStatusText($nextStatus) : null;
    }
}
