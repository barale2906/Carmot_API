<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seguimiento extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    //Seguimiento por referido
    public function referido() : BelongsTo
    {
        return $this->BelongsTo(Referido::class);
    }

    //Seguidor del referido
    public function seguidor() : BelongsTo
    {
        return $this->BelongsTo(User::class);
    }
}
