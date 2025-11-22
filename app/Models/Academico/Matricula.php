<?php

namespace App\Models\Academico;

use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Matricula
 *
 * Representa una matrícula de un estudiante en un curso y ciclo específico.
 * Una matrícula pertenece a un curso, un ciclo, un estudiante, un usuario que la realizó y un comercial.
 *
 * @property int $id Identificador único de la matrícula
 * @property int $curso_id ID del curso al que pertenece
 * @property int $ciclo_id ID del ciclo al que pertenece
 * @property int $estudiante_id ID del estudiante matriculado
 * @property int $matriculado_por_id ID del usuario que realizó la matrícula
 * @property int $comercial_id ID del usuario comercial que gestionó la venta
 * @property \Carbon\Carbon $fecha_matricula Fecha en que se realizó la matrícula
 * @property \Carbon\Carbon $fecha_inicio Fecha de inicio de las clases
 * @property float $monto Monto de la matrícula
 * @property string|null $observaciones Observaciones adicionales
 * @property int $status Estado de la matrícula (0: Inactivo, 1: Activo, 2: Anulado)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Academico\Curso $curso Curso al que pertenece
 * @property-read \App\Models\Academico\Ciclo $ciclo Ciclo al que pertenece
 * @property-read \App\Models\User $estudiante Estudiante matriculado
 * @property-read \App\Models\User $matriculadoPor Usuario que realizó la matrícula
 * @property-read \App\Models\User $comercial Usuario comercial que gestionó la venta
 */
class Matricula extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
        'fecha_matricula' => 'date',
        'fecha_inicio' => 'date',
        'monto' => 'decimal:2',
    ];

    /**
     * Obtiene las opciones de estado para matrículas.
     * Sobrescribe el método del trait para incluir el estado "Anulado".
     *
     * @return array<string, string> Array con los estados disponibles
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Anulado',
        ];
    }

    /**
     * Relación con Curso (muchos a uno).
     * Una matrícula pertenece a un curso.
     *
     * @return BelongsTo
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con Ciclo (muchos a uno).
     * Una matrícula pertenece a un ciclo.
     *
     * @return BelongsTo
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * Relación con User - Estudiante (muchos a uno).
     * Una matrícula pertenece a un estudiante.
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Relación con User - Matriculado Por (muchos a uno).
     * Una matrícula fue realizada por un usuario.
     *
     * @return BelongsTo
     */
    public function matriculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matriculado_por_id');
    }

    /**
     * Relación con User - Comercial (muchos a uno).
     * Una matrícula fue gestionada por un usuario comercial.
     *
     * @return BelongsTo
     */
    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    /**
     * Scope para filtrar por búsqueda (por nombre del estudiante o curso).
     */
    public function scopeSearch($query, $search)
    {
        return $query->whereHas('estudiante', function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%');
        })->orWhereHas('curso', function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para filtrar por curso.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cursoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCurso($query, $cursoId)
    {
        return $query->where('curso_id', $cursoId);
    }

    /**
     * Scope para filtrar por ciclo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cicloId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCiclo($query, $cicloId)
    {
        return $query->where('ciclo_id', $cicloId);
    }

    /**
     * Scope para filtrar por estudiante.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $estudianteId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEstudiante($query, $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    /**
     * Scope para filtrar por rango de fechas de matrícula.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaMatriculaRange($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_matricula', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope para filtrar por rango de montos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $montoMin
     * @param float $montoMax
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMontoRange($query, $montoMin, $montoMax)
    {
        return $query->whereBetween('monto', [$montoMin, $montoMax]);
    }

    /**
     * Scope para filtrar por estado anulado.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAnuladas($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica (sobrescribe el del trait).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->search($filters['search']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['curso_id']) && $filters['curso_id'], function ($q) use ($filters) {
                return $q->byCurso($filters['curso_id']);
            })
            ->when(isset($filters['ciclo_id']) && $filters['ciclo_id'], function ($q) use ($filters) {
                return $q->byCiclo($filters['ciclo_id']);
            })
            ->when(isset($filters['estudiante_id']) && $filters['estudiante_id'], function ($q) use ($filters) {
                return $q->byEstudiante($filters['estudiante_id']);
            })
            ->when(isset($filters['fecha_matricula_inicio']) && isset($filters['fecha_matricula_fin']), function ($q) use ($filters) {
                return $q->byFechaMatriculaRange($filters['fecha_matricula_inicio'], $filters['fecha_matricula_fin']);
            })
            ->when(isset($filters['monto_min']) && isset($filters['monto_max']), function ($q) use ($filters) {
                return $q->byMontoRange($filters['monto_min'], $filters['monto_max']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'fecha_matricula',
            'fecha_inicio',
            'monto',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return [
            'curso',
            'ciclo',
            'estudiante',
            'matriculadoPor',
            'comercial'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['curso', 'ciclo', 'estudiante'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return [];
    }

    /**
     * Verifica si la matrícula está anulada.
     *
     * @return bool
     */
    public function getAnuladaAttribute(): bool
    {
        return $this->status === 2;
    }

    /**
     * Verifica si la matrícula está activa.
     *
     * @return bool
     */
    public function getActivaAttribute(): bool
    {
        return $this->status === 1;
    }

    /**
     * Incrementa el contador de inscritos en el ciclo y sus grupos.
     *
     * @param int|null $cicloId ID del ciclo (opcional, usa el ciclo_id del modelo si no se proporciona)
     * @return void
     */
    protected function incrementarInscritos(?int $cicloId = null): void
    {
        $cicloId = $cicloId ?? $this->ciclo_id;

        if (!$cicloId) {
            return;
        }

        $ciclo = Ciclo::with('grupos')->find($cicloId);
        if (!$ciclo) {
            return;
        }

        // Incrementar inscritos en el ciclo
        $ciclo->increment('inscritos');

        // Incrementar inscritos en todos los grupos del ciclo
        foreach ($ciclo->grupos as $grupo) {
            $grupo->increment('inscritos');
        }
    }

    /**
     * Decrementa el contador de inscritos en el ciclo y sus grupos.
     *
     * @param int|null $cicloId ID del ciclo (opcional, usa el ciclo_id del modelo si no se proporciona)
     * @return void
     */
    protected function decrementarInscritos(?int $cicloId = null): void
    {
        $cicloId = $cicloId ?? $this->ciclo_id;

        if (!$cicloId) {
            return;
        }

        $ciclo = Ciclo::with('grupos')->find($cicloId);
        if (!$ciclo) {
            return;
        }

        // Decrementar inscritos en el ciclo (asegurarse de que no sea negativo)
        if ($ciclo->inscritos > 0) {
            $ciclo->decrement('inscritos');
        }

        // Decrementar inscritos en todos los grupos del ciclo
        foreach ($ciclo->grupos as $grupo) {
            if ($grupo->inscritos > 0) {
                $grupo->decrement('inscritos');
            }
        }
    }

    /**
     * Boot del modelo para eventos automáticos.
     */
    protected static function boot()
    {
        parent::boot();

        // Cuando se crea una matrícula activa, incrementar inscritos
        static::created(function ($matricula) {
            if ($matricula->status === 1) {
                $matricula->incrementarInscritos();
            }
        });

        // Cuando se actualiza una matrícula
        static::updated(function ($matricula) {
            $statusAnterior = $matricula->getOriginal('status');
            $statusNuevo = $matricula->status;
            $cicloAnterior = $matricula->getOriginal('ciclo_id');
            $cicloNuevo = $matricula->ciclo_id;

            // Si cambió el ciclo
            if ($cicloAnterior !== $cicloNuevo) {
                // Si estaba activa en el ciclo anterior, decrementar
                if ($statusAnterior === 1 && $cicloAnterior) {
                    $matricula->decrementarInscritos($cicloAnterior);
                }
                // Si está activa en el ciclo nuevo, incrementar
                if ($statusNuevo === 1 && $cicloNuevo) {
                    $matricula->incrementarInscritos($cicloNuevo);
                }
            } else {
                // Si solo cambió el status
                // De activo a inactivo/anulado: decrementar
                if ($statusAnterior === 1 && $statusNuevo !== 1) {
                    $matricula->decrementarInscritos();
                }
                // De inactivo/anulado a activo: incrementar
                if ($statusAnterior !== 1 && $statusNuevo === 1) {
                    $matricula->incrementarInscritos();
                }
            }
        });

        // Cuando se elimina una matrícula (soft delete), decrementar si estaba activa
        static::deleted(function ($matricula) {
            if ($matricula->status === 1) {
                $matricula->decrementarInscritos();
            }
        });

        // Cuando se restaura una matrícula, incrementar si está activa
        static::restored(function ($matricula) {
            if ($matricula->status === 1) {
                $matricula->incrementarInscritos();
            }
        });
    }
}
