<?php

namespace App\Models\Academico;

use App\Models\Configuracion\Eps;
use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\Financiero\CarteraGeneradorService;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Matricula
 *
 * Representa la matrícula de un estudiante en un curso/ciclo, incluyendo
 * todos los datos personales, socioeconómicos y de proceso de inscripción.
 *
 * @property int $id
 * @property int $curso_id
 * @property int $ciclo_id
 * @property int $estudiante_id
 * @property int $matriculado_por_id
 * @property int $comercial_id
 * @property string $fecha_matricula
 * @property string $fecha_inicio
 * @property float $monto
 * @property float|null $valor_cuota
 * @property string|null $observaciones
 * @property string|null $tipo_identificacion CC | CE | TI | RC | PA | OT
 * @property string|null $departamento_expedicion
 * @property string|null $ciudad_expedicion
 * @property string|null $fecha_nacimiento
 * @property string|null $genero M | F | O
 * @property string|null $estado_civil SO | CA | UL | DI | VI | SE
 * @property string|null $grupo_sanguineo A | B | AB | O
 * @property string|null $rh P | N
 * @property string|null $direccion
 * @property int|null $lugar_origen_id
 * @property string|null $celular
 * @property string|null $telefono
 * @property string|null $nivel_educacion PR | SE | TC | TG | PF | ES | MA | DO | OT
 * @property string|null $ocupacion
 * @property string|null $empresa
 * @property int|null $estrato 1-6
 * @property string|null $regimen_salud CO | SU | ES | EX
 * @property bool|null $enfermedad_prioritaria
 * @property bool|null $discapacidad
 * @property bool|null $conocimiento_curso
 * @property string|null $como_entero_curso
 * @property string|null $talla_overol
 * @property string|null $talla_botas
 * @property string|null $nombre_contacto
 * @property string|null $telefono_contacto
 * @property string|null $correo_contacto
 * @property bool $aprueba_uso_imagen
 * @property string|null $multiculturalidad
 * @property string|null $foto
 * @property int $status 0: Inactivo | 1: Activo | 2: Anulado
 * @property-read \App\Models\Academico\Curso          $curso
 * @property-read \App\Models\Academico\Ciclo          $ciclo
 * @property-read \App\Models\User                     $estudiante
 * @property-read \App\Models\User                     $matriculadoPor
 * @property-read \App\Models\User                     $comercial
 * @property-read \App\Models\Configuracion\Poblacion  $lugarOrigen
 */
class Matricula extends Model
{
    use HasActiveStatus, HasFactory, HasFilterScopes, HasRelationScopes, HasSortingScopes, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    // -------------------------------------------------------------------------
    // Catálogos de valores permitidos (usados en validaciones y en el Resource)
    // -------------------------------------------------------------------------

    public const TIPOS_IDENTIFICACION = [
        'CC' => 'Cédula de Ciudadanía',
        'CE' => 'Cédula de Extranjería',
        'TI' => 'Tarjeta de Identidad',
        'RC' => 'Registro Civil',
        'PA' => 'Pasaporte',
        'OT' => 'Otro',
    ];

    public const GENEROS = [
        'M' => 'Masculino',
        'F' => 'Femenino',
        'O' => 'Otro',
    ];

    public const ESTADOS_CIVILES = [
        'SO' => 'Soltero',
        'CA' => 'Casado',
        'UL' => 'Unión libre',
        'DI' => 'Divorciado',
        'VI' => 'Viudo',
        'SE' => 'Separado',
    ];

    public const GRUPOS_SANGUINEOS = [
        'A' => 'A',
        'B' => 'B',
        'AB' => 'AB',
        'O' => 'O',
    ];

    public const RHS = [
        'P' => 'Positivo',
        'N' => 'Negativo',
    ];

    public const NIVELES_EDUCACION = [
        'PR' => 'Primaria',
        'SE' => 'Secundaria',
        'TC' => 'Técnico',
        'TG' => 'Tecnólogo',
        'PF' => 'Profesional',
        'ES' => 'Especialización',
        'MA' => 'Maestría',
        'DO' => 'Doctorado',
        'OT' => 'Otro',
    ];

    public const REGIMENES_SALUD = [
        'CO' => 'Contributivo',
        'SU' => 'Subsidiado',
        'ES' => 'Especial',
        'EX' => 'Excepción',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'status' => 'integer',
        'fecha_matricula' => 'date',
        'fecha_inicio' => 'date',
        'fecha_nacimiento' => 'date',
        'monto' => 'decimal:2',
        'valor_cuota' => 'decimal:2',
        'estrato' => 'integer',
        'enfermedad_prioritaria' => 'boolean',
        'discapacidad' => 'boolean',
        'conocimiento_curso' => 'boolean',
        'aprueba_uso_imagen' => 'boolean',
    ];

    /**
     * Opciones de estado: extiende el trait para incluir "Anulado".
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Anulado',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function matriculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matriculado_por_id');
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    /**
     * EPS a la que pertenece el estudiante al momento de la matrícula.
     */
    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    /**
     * Lugar de origen del estudiante (población del sistema).
     */
    public function lugarOrigen(): BelongsTo
    {
        return $this->belongsTo(Poblacion::class, 'lugar_origen_id');
    }

    /**
     * Recibos de pago asociados a esta matrícula (FK matricula_id).
     * Sirve para validar soportes de pago sin pasar por cartera.
     */
    public function recibosPago(): HasMany
    {
        return $this->hasMany(ReciboPago::class);
    }

    /**
     * Carteras (cuentas por cobrar) generadas para esta matrícula.
     */
    public function carteras(): HasMany
    {
        return $this->hasMany(Cartera::class);
    }

    /**
     * Precio de producto vigente en el momento de la matrícula.
     * Guarda la traza del valor y el plan de cuotas usados al matricular.
     */
    public function lpPrecioProducto(): BelongsTo
    {
        return $this->belongsTo(LpPrecioProducto::class, 'lp_precio_producto_id');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getAnuladaAttribute(): bool
    {
        return $this->status === 2;
    }

    public function getActivaAttribute(): bool
    {
        return $this->status === 1;
    }

    public function getTipoIdentificacionTextoAttribute(): ?string
    {
        return self::TIPOS_IDENTIFICACION[$this->tipo_identificacion] ?? null;
    }

    public function getGeneroTextoAttribute(): ?string
    {
        return self::GENEROS[$this->genero] ?? null;
    }

    public function getEstadoCivilTextoAttribute(): ?string
    {
        return self::ESTADOS_CIVILES[$this->estado_civil] ?? null;
    }

    public function getNivelEducacionTextoAttribute(): ?string
    {
        return self::NIVELES_EDUCACION[$this->nivel_educacion] ?? null;
    }

    public function getRegimenSaludTextoAttribute(): ?string
    {
        return self::REGIMENES_SALUD[$this->regimen_salud] ?? null;
    }

    public function getRhTextoAttribute(): ?string
    {
        return self::RHS[$this->rh] ?? null;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeSearch($query, $search)
    {
        return $query->whereHas('estudiante', function ($q) use ($search) {
            $q->where('primer_nombre', 'like', '%'.$search.'%')
                ->orWhere('segundo_nombre', 'like', '%'.$search.'%')
                ->orWhere('primer_apellido', 'like', '%'.$search.'%')
                ->orWhere('segundo_apellido', 'like', '%'.$search.'%');
        })->orWhereHas('curso', function ($q) use ($search) {
            $q->where('nombre', 'like', '%'.$search.'%');
        });
    }

    public function scopeByCurso($query, $cursoId)
    {
        return $query->where('curso_id', $cursoId);
    }

    public function scopeByCiclo($query, $cicloId)
    {
        return $query->where('ciclo_id', $cicloId);
    }

    public function scopeByEstudiante($query, $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    public function scopeByFechaMatriculaRange($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_matricula', [$fechaInicio, $fechaFin]);
    }

    public function scopeByMontoRange($query, $montoMin, $montoMax)
    {
        return $query->whereBetween('monto', [$montoMin, $montoMax]);
    }

    public function scopeAnuladas($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope compuesto de filtros (sobrescribe el del trait).
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], fn ($q) => $q->search($filters['search']))
            ->when(isset($filters['status']) && $filters['status'] !== null, fn ($q) => $q->byStatus($filters['status']))
            ->when(isset($filters['curso_id']) && $filters['curso_id'], fn ($q) => $q->byCurso($filters['curso_id']))
            ->when(isset($filters['ciclo_id']) && $filters['ciclo_id'], fn ($q) => $q->byCiclo($filters['ciclo_id']))
            ->when(isset($filters['estudiante_id']) && $filters['estudiante_id'], fn ($q) => $q->byEstudiante($filters['estudiante_id']))
            ->when(
                isset($filters['fecha_matricula_inicio']) && isset($filters['fecha_matricula_fin']),
                fn ($q) => $q->byFechaMatriculaRange($filters['fecha_matricula_inicio'], $filters['fecha_matricula_fin'])
            )
            ->when(
                isset($filters['monto_min']) && isset($filters['monto_max']),
                fn ($q) => $q->byMontoRange($filters['monto_min'], $filters['monto_max'])
            )
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], fn ($q) => $q->withTrashed())
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], fn ($q) => $q->onlyTrashed());
    }

    protected function getAllowedSortFields(): array
    {
        return [
            'fecha_matricula',
            'fecha_inicio',
            'fecha_nacimiento',
            'monto',
            'valor_cuota',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    protected function getAllowedRelations(): array
    {
        return [
            'curso',
            'ciclo',
            'estudiante',
            'matriculadoPor',
            'comercial',
            'lugarOrigen',
            'recibosPago',
            'carteras',
            'lpPrecioProducto',
        ];
    }

    protected function getDefaultRelations(): array
    {
        return ['curso', 'ciclo', 'estudiante'];
    }

    protected function getCountableRelations(): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Lógica de negocio — contadores de inscritos en ciclo/grupos y vínculo curso/estudiante
    // -------------------------------------------------------------------------

    protected function incrementarInscritos(?int $cicloId = null): void
    {
        $cicloId = $cicloId ?? $this->ciclo_id;

        if (! $cicloId) {
            return;
        }

        $ciclo = Ciclo::with('grupos')->find($cicloId);
        if (! $ciclo) {
            return;
        }

        $ciclo->increment('inscritos');

        foreach ($ciclo->grupos as $grupo) {
            $grupo->increment('inscritos');
        }
    }

    protected function decrementarInscritos(?int $cicloId = null): void
    {
        $cicloId = $cicloId ?? $this->ciclo_id;

        if (! $cicloId) {
            return;
        }

        $ciclo = Ciclo::with('grupos')->find($cicloId);
        if (! $ciclo) {
            return;
        }

        if ($ciclo->inscritos > 0) {
            $ciclo->decrement('inscritos');
        }

        foreach ($ciclo->grupos as $grupo) {
            if ($grupo->inscritos > 0) {
                $grupo->decrement('inscritos');
            }
        }
    }

    protected function attachEstudianteACurso(?int $cursoId = null): void
    {
        $cursoId = $cursoId ?? $this->curso_id;

        if (! $cursoId || ! $this->estudiante_id) {
            return;
        }

        $curso = Curso::find($cursoId);
        if (! $curso) {
            return;
        }

        $curso->estudiantes()->syncWithoutDetaching([$this->estudiante_id]);
    }

    /**
     * Desvincula al estudiante del curso, salvo que conserve otra matrícula
     * activa en ese mismo curso (p. ej. en un ciclo distinto).
     */
    protected function detachEstudianteDeCurso(?int $cursoId = null): void
    {
        $cursoId = $cursoId ?? $this->curso_id;

        if (! $cursoId || ! $this->estudiante_id) {
            return;
        }

        $tieneOtraMatriculaActiva = static::where('estudiante_id', $this->estudiante_id)
            ->where('curso_id', $cursoId)
            ->where('status', 1)
            ->where('id', '!=', $this->id)
            ->exists();

        if ($tieneOtraMatriculaActiva) {
            return;
        }

        $curso = Curso::find($cursoId);
        if (! $curso) {
            return;
        }

        $curso->estudiantes()->detach($this->estudiante_id);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($matricula) {
            if ($matricula->status === 1) {
                $matricula->incrementarInscritos();
                $matricula->attachEstudianteACurso();

                // Genera cargos de cartera cuando la matrícula tiene un precio de lista vinculado.
                // El recibo de pago se genera manualmente en la pantalla de recibos.
                if ($matricula->lp_precio_producto_id) {
                    $matricula->load('lpPrecioProducto', 'ciclo');
                    app(CarteraGeneradorService::class)->generarParaMatricula($matricula);
                }
            }
        });

        static::updated(function ($matricula) {
            $statusAnterior = $matricula->getOriginal('status');
            $statusNuevo = $matricula->status;
            $cicloAnterior = $matricula->getOriginal('ciclo_id');
            $cicloNuevo = $matricula->ciclo_id;
            $cursoAnterior = $matricula->getOriginal('curso_id');
            $cursoNuevo = $matricula->curso_id;

            if ($cicloAnterior !== $cicloNuevo) {
                if ($statusAnterior === 1 && $cicloAnterior) {
                    $matricula->decrementarInscritos($cicloAnterior);
                }
                if ($statusNuevo === 1 && $cicloNuevo) {
                    $matricula->incrementarInscritos($cicloNuevo);
                }
            } else {
                if ($statusAnterior === 1 && $statusNuevo !== 1) {
                    $matricula->decrementarInscritos();
                }
                if ($statusAnterior !== 1 && $statusNuevo === 1) {
                    $matricula->incrementarInscritos();
                }
            }

            if ($cursoAnterior !== $cursoNuevo) {
                if ($statusAnterior === 1 && $cursoAnterior) {
                    $matricula->detachEstudianteDeCurso($cursoAnterior);
                }
                if ($statusNuevo === 1 && $cursoNuevo) {
                    $matricula->attachEstudianteACurso($cursoNuevo);
                }
            } else {
                if ($statusAnterior === 1 && $statusNuevo !== 1) {
                    $matricula->detachEstudianteDeCurso();
                }
                if ($statusAnterior !== 1 && $statusNuevo === 1) {
                    $matricula->attachEstudianteACurso();
                }
            }
        });

        static::deleted(function ($matricula) {
            if ($matricula->status === 1) {
                $matricula->decrementarInscritos();
                $matricula->detachEstudianteDeCurso();
            }
        });

        static::restored(function ($matricula) {
            if ($matricula->status === 1) {
                $matricula->incrementarInscritos();
                $matricula->attachEstudianteACurso();
            }
        });
    }
}
