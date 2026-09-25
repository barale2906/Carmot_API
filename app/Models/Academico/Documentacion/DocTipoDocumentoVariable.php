<?php

namespace App\Models\Academico\Documentacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DocTipoDocumentoVariable — variable del catálogo habilitada para un tipo de documento.
 *
 * Registra cuáles variables de `config/documentacion.php` quedan disponibles
 * para insertarse en las plantillas de un tipo de documento. El catálogo de
 * variables lo mantiene el backend; aquí solo se guarda la selección hecha
 * desde la plataforma.
 *
 * @property int    $id
 * @property int    $tipo_documento_id
 * @property string $variable_key Clave de la variable en el catálogo.
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @property-read DocTipoDocumento $tipoDocumento
 *
 * @package App\Models\Academico\Documentacion
 */
class DocTipoDocumentoVariable extends Model
{
    use HasFactory;

    protected $table = 'doc_tipo_documento_variables';

    protected $guarded = ['id'];

    protected $casts = [
        'tipo_documento_id' => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * Tipo de documento al que pertenece la variable habilitada.
     *
     * @return BelongsTo
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(DocTipoDocumento::class, 'tipo_documento_id');
    }
}
