<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

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
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Translatable\HasTranslations;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, HasTranslations;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'documento',
        'deleted_at',
    ];

    /**
     * Los atributos que son traducibles.
     *
     * @var array<int, string>
     */
    public $translatable = [
        // 'name' removido - el nombre del usuario no debe ser traducible
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Los atributos que deben ser convertidos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    //Cursos en los que está inscrito el estudiante (relación muchos a muchos)
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_user')
                    ->withTimestamps();
    }

    //Gestor CRM - referidos que gestiona
    public function gestores(): HasMany
    {
        return $this->hasMany(Referido::class, 'gestor_id');
    }

    //Agendador CRM - agendas que ha creado
    public function agendadores(): HasMany
    {
        return $this->hasMany(Agenda::class, 'agendador_id');
    }

    //Gestor que hace el seguimiento
    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class, 'seguidor_id');
    }

    //Grupos que imparte el profesor
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'profesor_id');
    }

    /**
     * Dashboards creados por el usuario.
     * Un usuario puede crear múltiples dashboards.
     *
     * @return HasMany
     */
    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class, 'user_id');
    }

    // Matrículas del estudiante
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'estudiante_id');
    }

    // Matrículas realizadas por el usuario
    public function matriculasRealizadas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'matriculado_por_id');
    }

    // Notas del estudiante
    public function notasEstudiantes(): HasMany
    {
        return $this->hasMany(NotaEstudiante::class, 'estudiante_id');
    }

    // Notas registradas por el usuario
    public function notasRegistradas(): HasMany
    {
        return $this->hasMany(NotaEstudiante::class, 'registrado_por_id');
    }

    // Asistencias del estudiante
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'estudiante_id');
    }

    // Asistencias registradas por el usuario
    public function asistenciasRegistradas(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'registrado_por_id');
    }

    // Clases programadas creadas por el usuario
    public function clasesProgramadas(): HasMany
    {
        return $this->hasMany(AsistenciaClaseProgramada::class, 'creado_por_id');
    }

    /**
     * Relación con RecibosPago como Estudiante (uno a muchos).
     * Un estudiante puede tener múltiples recibos de pago.
     *
     * @return HasMany
     */
    public function recibosPagoComoEstudiante(): HasMany
    {
        return $this->hasMany(ReciboPago::class, 'estudiante_id');
    }

    /**
     * Relación con RecibosPago como Cajero (uno a muchos).
     * Un cajero puede generar múltiples recibos de pago.
     *
     * @return HasMany
     */
    public function recibosPagoComoCajero(): HasMany
    {
        return $this->hasMany(ReciboPago::class, 'cajero_id');
    }
}
