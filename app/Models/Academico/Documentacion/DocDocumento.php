<?php

namespace App\Models\Academico\Documentacion;

use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo DocDocumento — documento generado o archivo subido al repositorio.
 *
 * Un documento generado guarda en `contenido_renderizado` el HTML con las
 * variables ya resueltas al momento de generarlo. Ese contenido no se vuelve a
 * calcular: aunque después se publique otra versión de la plantilla o cambien
 * los datos del estudiante, el documento conserva exactamente lo que se emitió.
 *
 * Los archivos subidos (cédulas, diplomas) comparten la tabla y se distinguen
 * por `origen`, guardando la referencia al archivo en Google Drive.
 *
 * @property int         $id
 * @property int         $tipo_documento_id
 * @property int|null    $plantilla_id
 * @property string|null $entidad_type
 * @property int|null    $entidad_id
 * @property string      $numero_documento
 * @property int         $origen                0=Generado, 1=Subido.
 * @property string|null $contenido_renderizado
 * @property array|null  $variables_aplicadas
 * @property \Carbon\Carbon|null $fecha_referencia
 * @property string|null $google_drive_file_id
 * @property string|null $google_drive_url
 * @property string|null $nombre_original
 * @property string|null $mime_type
 * @property int|null    $tamano_bytes
 * @property int         $status                1=Vigente, 2=Anulado.
 * @property string|null $motivo_anulacion
 * @property int|null    $generado_por
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read DocTipoDocumento $tipoDocumento
 * @property-read DocPlantilla|null $plantilla
 * @property-read Model|null $entidad
 * @property-read User|null $generador
 *
 * @package App\Models\Academico\Documentacion
 */
class DocDocumento extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasSortingScopes;

    public const STATUS_VIGENTE = 1;
    public const STATUS_ANULADO = 2;

    public const ORIGEN_GENERADO = 0;
    public const ORIGEN_SUBIDO   = 1;

    protected $table = 'doc_documentos';

    protected $guarded = ['id'];

    protected $casts = [
        'tipo_documento_id'   => 'integer',
        'plantilla_id'        => 'integer',
        'entidad_id'          => 'integer',
        'origen'              => 'integer',
        'variables_aplicadas' => 'array',
        'fecha_referencia'    => 'date',
        'tamano_bytes'        => 'integer',
        'status'              => 'integer',
        'generado_por'        => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
    ];

    /**
     * Estados posibles de un documento.
     *
     * @return array<int, string>
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            self::STATUS_VIGENTE => 'Vigente',
            self::STATUS_ANULADO => 'Anulado',
        ];
    }

    /**
     * Tipo de documento al que pertenece.
     *
     * @return BelongsTo
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(DocTipoDocumento::class, 'tipo_documento_id');
    }

    /**
     * Versión de plantilla con la que se generó el documento.
     *
     * @return BelongsTo
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DocPlantilla::class, 'plantilla_id');
    }

    /**
     * Entidad del sistema a la que se asocia el documento.
     *
     * @return MorphTo
     */
    public function entidad(): MorphTo
    {
        return $this->morphTo('entidad');
    }

    /**
     * Usuario que generó o subió el documento.
     *
     * @return BelongsTo
     */
    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    /**
     * Scope para filtrar documentos generados desde una plantilla.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeGenerados(Builder $query): Builder
    {
        return $query->where('origen', self::ORIGEN_GENERADO);
    }

    /**
     * Scope para filtrar archivos subidos al repositorio.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSubidos(Builder $query): Builder
    {
        return $query->where('origen', self::ORIGEN_SUBIDO);
    }

    /**
     * Scope para aplicar los filtros del listado de documentos.
     *
     * @param Builder              $query
     * @param array<string, mixed> $filters
     * @return Builder
     */
    public function scopeWithFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                isset($filters['search']) && $filters['search'],
                fn (Builder $q) => $q->where('numero_documento', 'like', '%' . $filters['search'] . '%')
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '',
                fn (Builder $q) => $q->where('status', (int) $filters['status'])
            )
            ->when(
                isset($filters['tipo_documento_id']) && $filters['tipo_documento_id'],
                fn (Builder $q) => $q->where('tipo_documento_id', (int) $filters['tipo_documento_id'])
            )
            ->when(
                isset($filters['origen']) && $filters['origen'] !== null && $filters['origen'] !== '',
                fn (Builder $q) => $q->where('origen', (int) $filters['origen'])
            )
            ->when(
                isset($filters['entidad_type']) && $filters['entidad_type'],
                fn (Builder $q) => $q->where('entidad_type', $filters['entidad_type'])
            )
            ->when(
                isset($filters['entidad_id']) && $filters['entidad_id'],
                fn (Builder $q) => $q->where('entidad_id', (int) $filters['entidad_id'])
            )
            ->when(
                isset($filters['include_trashed']) && $filters['include_trashed'],
                fn (Builder $q) => $q->withTrashed()
            )
            ->when(
                isset($filters['only_trashed']) && $filters['only_trashed'],
                fn (Builder $q) => $q->onlyTrashed()
            );
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<int, string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['numero_documento', 'status', 'fecha_referencia', 'created_at', 'updated_at'];
    }
}
