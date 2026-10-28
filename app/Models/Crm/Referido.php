<?php

namespace App\Models\Crm;

use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\User;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use App\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Translatable\HasTranslations;

class Referido extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;
    use HasFilterScopes, HasSortingScopes, HasRelationScopes, HasStatus;

    /** Estado que solo asigna la conversión del lead en matrícula ({@see marcarMatriculado}). */
    public const ESTADO_MATRICULADO = 3;

    /** Estados que el asesor puede asignar a mano: todos menos Matriculado. */
    public const ESTADOS_ASIGNABLES = [0, 1, 2, 4];

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
     * Matrículas generadas a partir de este lead.
     *
     * @return HasMany
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Busca por nombre o celular: el asesor suele identificar al lead por su número.
     * Sobrescribe el scope genérico de HasFilterScopes, que solo busca por nombre.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('celular', 'like', '%' . $search . '%');
        });
    }

    /**
     * Marca el lead como matriculado y deja constancia en su bitácora de seguimiento.
     *
     * @param Matricula $matricula
     * @return void
     */
    public function marcarMatriculado(Matricula $matricula): void
    {
        $this->update(['status' => self::ESTADO_MATRICULADO]);

        $this->seguimientos()->create([
            'seguidor_id' => $matricula->matriculado_por_id,
            'fecha'       => now()->toDateString(),
            'seguimiento' => "MATRÍCULA: Convertido en la matrícula #{$matricula->id}.",
        ]);
    }

    /**
     * Registra un seguimiento en la bitácora del lead y, si se indica, actualiza su estado y
     * deja programada la próxima acción, todo en la misma transacción: la gestión del asesor,
     * el estado resultante y lo que sigue van juntos.
     *
     * @param int $seguidorId Usuario que hace el seguimiento
     * @param string $fecha Fecha del seguimiento (Y-m-d)
     * @param string $texto Detalle del seguimiento
     * @param int|null $estado Nuevo estado del lead (uno de ESTADOS_ASIGNABLES); null lo deja igual
     * @param array{tipo: string, fecha: string, hora: string, nota?: string|null}|null $proximaAccion
     * @return Seguimiento
     */
    public function registrarSeguimiento(int $seguidorId, string $fecha, string $texto, ?int $estado = null, ?array $proximaAccion = null): Seguimiento
    {
        return DB::transaction(function () use ($seguidorId, $fecha, $texto, $estado, $proximaAccion) {
            if ($estado !== null) {
                $this->update(['status' => $estado]);
            }

            $seguimiento = $this->seguimientos()->create([
                'seguidor_id' => $seguidorId,
                'fecha'       => $fecha,
                'seguimiento' => $texto,
            ]);

            if ($proximaAccion) {
                $this->programarAccion($seguidorId, $proximaAccion);
            }

            return $seguimiento;
        });
    }

    /**
     * Programa en la agenda la próxima acción con el lead (llamada, visita, mensaje...).
     * La agenda deja su propia entrada en la bitácora ({@see Agenda::crearSeguimientoAutomatico}).
     *
     * @param int $agendadorId Usuario que programa la acción
     * @param array{tipo: string, fecha: string, hora: string, nota?: string|null} $accion
     * @return Agenda
     */
    public function programarAccion(int $agendadorId, array $accion): Agenda
    {
        return $this->agendamientos()->create([
            'agendador_id' => $agendadorId,
            'tipo'         => $accion['tipo'],
            'fecha'        => $accion['fecha'],
            'hora'         => $accion['hora'],
            'jornada'      => Agenda::jornadaDeHora($accion['hora']),
            'nota'         => $accion['nota'] ?? null,
        ]);
    }

    /**
     * Leads con al menos un seguimiento en el rango de fechas indicado (extremos opcionales).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $desde
     * @param string|null $hasta
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConSeguimientoEntre($query, ?string $desde, ?string $hasta)
    {
        return $query->when($desde || $hasta, fn ($q) => $q->whereHas('seguimientos', function ($s) use ($desde, $hasta) {
            $s->when($desde, fn ($s) => $s->whereDate('fecha', '>=', $desde))
                ->when($hasta, fn ($s) => $s->whereDate('fecha', '<=', $hasta));
        }));
    }

    /**
     * Ordena los leads por la fecha de su seguimiento más reciente y, en la misma fecha, por
     * nombre; los que no tienen seguimientos van al final.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdenadoPorUltimoSeguimiento($query)
    {
        return $query->withMax('seguimientos', 'fecha')
            ->orderByDesc('seguimientos_max_fecha')
            ->orderBy('nombre')
            ->orderByDesc('id');
    }

    /**
     * Deja en la bitácora el cambio de asesor: el lead lo gestiona el asesor asignado hasta
     * matricularlo o hasta que lo reasignen (N07).
     *
     * @param int|null $gestorAnteriorId
     * @param int $reasignadoPorId
     * @return void
     */
    public function registrarReasignacion(?int $gestorAnteriorId, int $reasignadoPorId): void
    {
        $anterior = $gestorAnteriorId ? User::find($gestorAnteriorId)?->name : null;

        $this->seguimientos()->create([
            'seguidor_id' => $reasignadoPorId,
            'fecha'       => now()->toDateString(),
            'seguimiento' => 'REASIGNACIÓN: de ' . ($anterior ?? 'sin asesor') . " a {$this->gestor->name}.",
        ]);
    }

    /**
     * Determina si el referido está matriculado.
     *
     * @return bool
     */
    public function isMatriculado(): bool
    {
        return $this->status === self::ESTADO_MATRICULADO;
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

    /**
     * Obtiene las relaciones permitidas para este modelo.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return [
            'curso',
            'gestor',
            'seguimientos',
            'agendamientos'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['curso', 'gestor'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return ['seguimientos', 'agendamientos'];
    }

}
