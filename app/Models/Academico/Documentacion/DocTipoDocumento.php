<?php

namespace App\Models\Academico\Documentacion;

use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo DocTipoDocumento — tipo de documento que el instituto puede generar.
 *
 * Define la identidad de un documento (contrato, pagaré, certificado, carta),
 * a qué entidad del sistema se asocia y si su contenido queda atado a una
 * fecha de referencia. Cuando `se_ata_fecha` es true, el documento se genera
 * con la versión de plantilla vigente en la fecha de referencia de la entidad
 * (p. ej. la fecha de matrícula), de modo que un contrato firmado conserva las
 * condiciones que estaban vigentes cuando se firmó. Cuando es false, siempre
 * se usa la versión activa (cartas, certificaciones).
 *
 * @property int         $id
 * @property string      $codigo                 Código único del tipo.
 * @property string      $nombre                 Nombre del tipo de documento.
 * @property string|null $descripcion
 * @property string|null $entidad_type           Clase Eloquent de la entidad asociada.
 * @property bool        $se_ata_fecha           Si la versión se resuelve por fecha de referencia.
 * @property string|null $campo_fecha_referencia Atributo fecha de la entidad; null = fecha de generación.
 * @property string      $prefijo_numero         Prefijo del consecutivo de los documentos.
 * @property int         $status                 Estado del tipo (1=activo, 0=inactivo).
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocTipoDocumentoVariable> $variables
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocPlantilla> $plantillas
 * @property-read int|null $variables_count
 *
 * @package App\Models\Academico\Documentacion
 */
class DocTipoDocumento extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasFilterScopes;
    use HasSortingScopes;
    use HasRelationScopes;

    protected $table = 'doc_tipos_documento';

    protected $guarded = ['id'];

    protected $casts = [
        'se_ata_fecha' => 'boolean',
        'status'       => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    /**
     * Variables del catálogo habilitadas para este tipo de documento.
     *
     * @return HasMany
     */
    public function variables(): HasMany
    {
        return $this->hasMany(DocTipoDocumentoVariable::class, 'tipo_documento_id');
    }

    /**
     * Versiones de plantilla registradas para este tipo de documento.
     *
     * @return HasMany
     */
    public function plantillas(): HasMany
    {
        return $this->hasMany(DocPlantilla::class, 'tipo_documento_id');
    }

    /**
     * Claves de las variables habilitadas para este tipo de documento.
     *
     * @return array<int, string>
     */
    public function clavesHabilitadas(): array
    {
        return $this->variables()->pluck('variable_key')->all();
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<int, string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['codigo', 'nombre', 'status', 'created_at', 'updated_at'];
    }

    /**
     * Relaciones permitidas para carga dinámica.
     *
     * @return array<int, string>
     */
    protected function getAllowedRelations(): array
    {
        return ['variables'];
    }

    /**
     * Relaciones cargadas por defecto.
     *
     * @return array<int, string>
     */
    protected function getDefaultRelations(): array
    {
        return [];
    }

    /**
     * Relaciones que admiten conteo.
     *
     * @return array<int, string>
     */
    protected function getCountableRelations(): array
    {
        return ['variables'];
    }
}
