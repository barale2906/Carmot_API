<?php

namespace App\Models;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\NotaEstudiante;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\Crm\Seguimiento;
use App\Models\Dashboard\Dashboard;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Inventarios\InvAlmacen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Translatable\HasTranslations;

/**
 * Modelo central de usuario del sistema.
 *
 * El nombre completo se almacena en cuatro campos independientes para
 * facilitar búsquedas, ordenamientos y formatos de presentación. El
 * accessor `name` concatena los campos para compatibilidad con el resto
 * del sistema (relaciones, resources, reportes).
 *
 * @property int         $id
 * @property string      $primer_nombre    Primer nombre (obligatorio).
 * @property string|null $segundo_nombre   Segundo nombre (opcional).
 * @property string      $primer_apellido  Primer apellido (obligatorio).
 * @property string|null $segundo_apellido Segundo apellido (opcional).
 * @property string      $email            Correo electrónico (único).
 * @property string      $documento        Número de documento (único).
 * @property string      $password
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read string $name             Nombre completo calculado (accessor).
 *
 * @package App\Models
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, HasTranslations;

    /**
     * Rol de los estudiantes: quien solo tiene este rol únicamente consulta su propia información.
     */
    public const ROL_ALUMNO = 'alumno';

    /**
     * Rol de los docentes: el profesor de un grupo debe tenerlo.
     */
    public const ROL_PROFESOR = 'profesor';

    protected $fillable = [
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'email',
        'password',
        'documento',
        'deleted_at',
    ];

    public $translatable = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    // -------------------------------------------------------------------------
    // Accessor — nombre completo
    // -------------------------------------------------------------------------

    /**
     * Retorna el nombre completo concatenando los cuatro campos.
     * Se usa en todo el sistema donde se espera `->name` del usuario.
     */
    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->primer_nombre,
            $this->segundo_nombre,
            $this->primer_apellido,
            $this->segundo_apellido,
        ])));
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Sedes asignadas explícitamente al usuario (muchos a muchos).
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'sede_user')->withTimestamps();
    }

    /**
     * Devuelve las sedes a las que el usuario tiene acceso efectivo.
     */
    public function sedesAccesibles(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->hasRole('superusuario')) {
            return Sede::all();
        }

        return $this->sedes()->get();
    }

    /**
     * IDs de las sedes a las que el usuario tiene acceso efectivo.
     *
     * @return array<int>
     */
    public function idsSedesAccesibles(): array
    {
        return $this->sedesAccesibles()->pluck('id')->all();
    }

    /**
     * Indica si el usuario tiene acceso a la sede indicada.
     */
    public function tieneAccesoSede(int $sedeId): bool
    {
        return in_array($sedeId, $this->idsSedesAccesibles(), true);
    }

    /**
     * Indica si el usuario solo tiene el rol de alumno. A estos usuarios las consultas de notas,
     * asistencias y recibos se les limitan a su propia información, aunque tengan el permiso.
     */
    public function esSoloEstudiante(): bool
    {
        $roles = $this->getRoleNames();

        return $roles->isNotEmpty() && $roles->every(fn (string $rol) => $rol === self::ROL_ALUMNO);
    }

    /**
     * Indica si el usuario puede consultar la información del estudiante indicado:
     * el personal puede ver a cualquiera; el alumno, solo a sí mismo.
     */
    public function puedeVerEstudiante(?int $estudianteId): bool
    {
        return ! $this->esSoloEstudiante() || $this->id === $estudianteId;
    }

    /**
     * Búsqueda por nombres, apellidos, correo o documento.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $texto
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBuscar($query, ?string $texto)
    {
        if (! filled($texto)) {
            return $query;
        }

        return $query->where(function ($q) use ($texto) {
            foreach (['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'email', 'documento'] as $campo) {
                $q->orWhere($campo, 'like', "%{$texto}%");
            }
        });
    }

    /**
     * Personal del instituto: usuarios con al menos un rol distinto de alumno
     * (los responsables de activos, por ejemplo, no pueden ser estudiantes, F15).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePersonal($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', '!=', self::ROL_ALUMNO));
    }

    /**
     * Regla de validación: el usuario indicado debe ser del personal (p. ej. el asesor de un
     * lead, N07). Se usa junto a `exists:users,id`.
     *
     * @param string $mensaje
     * @return \Closure
     */
    public static function reglaPersonal(string $mensaje): \Closure
    {
        return function (string $atributo, $valor, \Closure $fallar) use ($mensaje) {
            if ($valor && ! self::personal()->whereKey($valor)->exists()) {
                $fallar($mensaje);
            }
        };
    }

    /**
     * Estudiantes: quien no es personal (rol alumno o sin roles) o el personal que tiene
     * alguna matrícula. Deja fuera al personal y a los superusuarios que no estudian (N01).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEstudiantes($query)
    {
        return $query->where(fn ($q) => $q
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', '!=', self::ROL_ALUMNO))
            ->orWhereHas('matriculas'));
    }

    /**
     * Indica si el usuario solo dicta clases: tiene el rol profesor y ningún rol
     * del personal (puede tener además el de alumno).
     */
    public function esSoloProfesor(): bool
    {
        $roles = $this->getRoleNames();

        return $roles->contains(self::ROL_PROFESOR)
            && $roles->every(fn (string $rol) => in_array($rol, [self::ROL_PROFESOR, self::ROL_ALUMNO], true));
    }

    /**
     * Indica si el usuario puede registrar asistencia y notas del grupo: su profesor,
     * o personal (no solo profesor) con la sede del grupo asignada.
     */
    public function puedeGestionarGrupo(\App\Models\Academico\Grupo $grupo): bool
    {
        if ((int) $grupo->profesor_id === $this->id) {
            return true;
        }

        return ! $this->esSoloProfesor() && $this->tieneAccesoSede((int) $grupo->sede_id);
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_user')->withTimestamps();
    }

    public function gestores(): HasMany
    {
        return $this->hasMany(Referido::class, 'gestor_id');
    }

    public function agendadores(): HasMany
    {
        return $this->hasMany(Agenda::class, 'agendador_id');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class, 'seguidor_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'profesor_id');
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class, 'user_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'estudiante_id');
    }

    public function matriculasRealizadas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'matriculado_por_id');
    }

    public function notasEstudiantes(): HasMany
    {
        return $this->hasMany(NotaEstudiante::class, 'estudiante_id');
    }

    public function notasRegistradas(): HasMany
    {
        return $this->hasMany(NotaEstudiante::class, 'registrado_por_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'estudiante_id');
    }

    public function asistenciasRegistradas(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'registrado_por_id');
    }

    public function clasesProgramadas(): HasMany
    {
        return $this->hasMany(AsistenciaClaseProgramada::class, 'creado_por_id');
    }

    public function recibosPagoComoEstudiante(): HasMany
    {
        return $this->hasMany(ReciboPago::class, 'estudiante_id');
    }

    public function recibosPagoComoCajero(): HasMany
    {
        return $this->hasMany(ReciboPago::class, 'cajero_id');
    }

    /**
     * Almacenes a los que tiene acceso el usuario como cajero.
     *
     * @return BelongsToMany
     */
    public function almacenes(): BelongsToMany
    {
        return $this->belongsToMany(InvAlmacen::class, 'inv_almacen_usuario', 'user_id', 'almacen_id');
    }
}
