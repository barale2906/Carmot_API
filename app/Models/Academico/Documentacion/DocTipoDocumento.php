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
 * Define la identidad de un documento (contrato, pagaré, certificado, carta) y
 * a qué entidad del sistema se asocia.
 *
 * `conforma_matricula` marca los documentos legales o que forman parte de la
 * matrícula: se imprimen con la versión de plantilla que estaba vigente en la
 * fecha de esa matrícula, de modo que reimprimir un contrato devuelve siempre
 * las condiciones bajo las que se firmó. Los demás (sábanas de notas,
 * constancias, cartas) usan la versión vigente al momento de imprimirlos.
 *
 * @property int         $id
 * @property string      $codigo                 Código único del tipo.
 * @property string      $nombre                 Nombre del tipo de documento.
 * @property string|null $descripcion
 * @property string|null $entidad_type           Clase Eloquent de la entidad asociada.
 * @property bool        $conforma_matricula     Documento legal o que forma parte de la matrícula.
 * @property string|null $campo_fecha_referencia Atributo fecha que ancla la versión aplicable.
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
        'conforma_matricula' => 'boolean',
        'status'             => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
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
