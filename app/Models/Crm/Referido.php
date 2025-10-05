<?php

namespace App\Models\Crm;

use App\Models\Academico\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Referido extends Model
{
    use HasFactory, HasTranslations;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    //Aspirantes a cada curso
    public function curso() : BelongsTo
    {
        return $this->BelongsTo(Curso::class);
    }

    //Aspirantes a cada curso
    public function gestor() : BelongsTo
    {
        return $this->BelongsTo(User::class);
    }
}
