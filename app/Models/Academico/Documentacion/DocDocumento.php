<?php

namespace App\Models\Academico\Documentacion;

use App\Models\User;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo DocDocumento — bitácora de impresiones y repositorio de archivos subidos.
 *
 * Los documentos generados no se almacenan: se vuelven a resolver en cada
 * impresión a partir de la plantilla aplicable y de los datos del estudiante. Lo
 * que queda aquí es el rastro de cada impresión —de qué tipo, para qué registro,
 * con qué versión de plantilla, quién y cuándo—, sin el contenido.
 *
 * El número visible de un documento es el de la matrícula a la que corresponde,
 * así que no lleva consecutivo propio: se inserta en la plantilla con la variable
 * `numero_matricula`.
 *
 * Con `origen = ORIGEN_SUBIDO` la fila representa en cambio un archivo externo del
 * estudiante (cédula, diploma) guardado en el repositorio.
 *
 * @property int         $id
 * @property int         $tipo_documento_id
 * @property int|null    $plantilla_id   Versión de plantilla con la que se imprimió.
 * @property string|null $entidad_type
 * @property int|null    $entidad_id
 * @property int         $origen         0=Impresión generada, 1=Archivo subido.
 * @property \Carbon\Carbon|null $fecha_referencia Fecha con la que se resolvió la versión.
 * @property string|null $google_drive_file_id
 * @property string|null $google_drive_url
 * @property string|null $nombre_original
 * @property string|null $mime_type
 * @property int|null    $tamano_bytes
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
    use HasSortingScopes;

    public const ORIGEN_GENERADO = 0;
    public const ORIGEN_SUBIDO   = 1;

    protected $table = 'doc_documentos';

    protected $guarded = ['id'];

    protected $casts = [
        'tipo_documento_id' => 'integer',
        'plantilla_id'      => 'integer',
        'entidad_id'        => 'integer',
        'origen'            => 'integer',
        'fecha_referencia'  => 'date',
        'tamano_bytes'      => 'integer',
        'generado_por'      => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    /**
     * Orígenes posibles de una fila.
     *
     * @return array<int, string>
     */
    public static function getOrigenOptions(): array
    {
        return [
            self::ORIGEN_GENERADO => 'Impresión generada',
            self::ORIGEN_SUBIDO   => 'Archivo subido',
        ];
    }

    /**
     * Texto del origen.
     *
     * @param int|null $origen
     * @return string
     */
    public static function getOrigenText(?int $origen): string
    {
        return self::getOrigenOptions()[$origen] ?? 'Desconocido';
    }

    /**
     * Tipo de documento al que corresponde.
     *
     * @return BelongsTo
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(DocTipoDocumento::class, 'tipo_documento_id');
    }

    /**
     * Versión de plantilla con la que se imprimió.
     *
     * @return BelongsTo
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DocPlantilla::class, 'plantilla_id');
    }

    /**
     * Registro del sistema al que corresponde el documento.
     *
     * @return MorphTo
     */
    public function entidad(): MorphTo
    {
        return $this->morphTo('entidad');
    }

    /**
     * Usuario que imprimió o subió el documento.
     *
     * @return BelongsTo
     */
    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    /**
     * Scope de las impresiones de documentos generados.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeGenerados(Builder $query): Builder
    {
        return $query->where('origen', self::ORIGEN_GENERADO);
    }

    /**
     * Scope de los archivos subidos al repositorio.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSubidos(Builder $query): Builder
    {
        return $query->where('origen', self::ORIGEN_SUBIDO);
    }

    /**
     * Scope para aplicar los filtros del listado.
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
                fn (Builder $q) => $q->where('nombre_original', 'like', '%' . $filters['search'] . '%')
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
        return ['fecha_referencia', 'created_at', 'updated_at'];
    }
}
