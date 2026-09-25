<?php

namespace App\Models\Academico\Documentacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DocPlantillaBloque — configuración de impresión de un bloque en una versión.
 *
 * Guarda la selección que hizo el administrador sobre un bloque del catálogo:
 * qué columnas imprime, en qué orden, con qué títulos y si lleva fila de resumen.
 * La consulta del bloque vive en su clase; aquí solo está su presentación.
 *
 * @property int         $id
 * @property int         $plantilla_id
 * @property string      $bloque_key
 * @property array       $columnas
 * @property array|null  $titulos
 * @property bool        $mostrar_resumen
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @property-read DocPlantilla $plantilla
 *
 * @package App\Models\Academico\Documentacion
 */
class DocPlantillaBloque extends Model
{
    use HasFactory;

    protected $table = 'doc_plantilla_bloques';

    protected $guarded = ['id'];

    protected $casts = [
        'plantilla_id'    => 'integer',
        'columnas'        => 'array',
        'titulos'         => 'array',
        'mostrar_resumen' => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Versión de plantilla a la que pertenece la configuración.
     *
     * @return BelongsTo
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DocPlantilla::class, 'plantilla_id');
    }
}
