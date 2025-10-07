<?php

namespace App\Models\Crm;

use App\Models\Academico\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Referido extends Model
{
    use HasFactory, HasTranslations;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    // RElación uno a mucho seguimiento a los referidos
    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class);
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
}
