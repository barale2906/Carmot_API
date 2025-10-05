<?php

namespace App\Models\Academico;

use App\Models\Crm\Referido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Curso extends Model
{
    use HasFactory, HasTranslations;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    //Relación uno a muchos
    public function referido(): HasMany
    {
        return $this->hasMany(Referido::class);
    }

    //Estudiantes registrados al curso
    public function estudiante() : BelongsTo
    {
        return $this->BelongsTo(User::class);
    }
}
