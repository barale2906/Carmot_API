<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Academico\Curso;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\Crm\Seguimiento;
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
     * The attributes that are mass assignable.
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
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
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

    //Gestor CRM
    public function gestores(): HasMany
    {
        return $this->hasMany(Referido::class);
    }

    //Agendador CRM
    public function agendadores(): HasMany
    {
        return $this->hasMany(Agenda::class);
    }

    //Gestor que hace el seguimiento
    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class);
    }
}
