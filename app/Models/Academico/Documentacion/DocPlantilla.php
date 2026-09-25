<?php

namespace App\Models\Academico\Documentacion;

use App\Models\User;
use App\Traits\Academico\HasPlantillaDocumentoStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasSortingScopes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo DocPlantilla — versión del contenido de un tipo de documento.
 *
 * Cada fila es una versión completa e independiente: al publicar un contenido
 * nuevo se crea otra versión y se cierra la vigencia de la anterior, de modo
 * que el histórico queda intacto. Así, un contrato firmado bajo la versión de
 * 2024 se sigue resolviendo contra esa versión aunque hoy esté vigente otra.
 *
 * Flujo de estados: En Proceso → Aprobada → Activa, con Inactiva alcanzable
 * desde cualquier estado. Solo las versiones que llegaron a estar activas
 * (Activa o Inactiva con fecha_inicio) pueden generar documentos.
 *
 * @property int         $id
 * @property int         $tipo_documento_id
 * @property string      $nombre
 * @property int         $version             Número de versión dentro del tipo.
 * @property string      $contenido_html      Contenido con los marcadores {{variable}}.
 * @property int         $status              0=Inactiva, 1=En Proceso, 2=Aprobada, 3=Activa.
 * @property \Carbon\Carbon|null $fecha_inicio
 * @property \Carbon\Carbon|null $fecha_fin
 * @property int|null    $version_anterior_id
 * @property int|null    $creado_por
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read DocTipoDocumento $tipoDocumento
 * @property-read DocPlantilla|null $versionAnterior
 * @property-read User|null $creador
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocPlantillaBloque> $bloques
 *
 * @package App\Models\Academico\Documentacion
 */
class DocPlantilla extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasPlantillaDocumentoStatus;
    use HasFilterScopes;
    use HasSortingScopes;

    public const STATUS_INACTIVA   = 0;
    public const STATUS_EN_PROCESO = 1;
    public const STATUS_APROBADA   = 2;
    public const STATUS_ACTIVA     = 3;

    protected $table = 'doc_plantillas';

    protected $guarded = ['id'];

    protected $casts = [
        'tipo_documento_id'   => 'integer',
        'version'             => 'integer',
        'status'              => 'integer',
        'fecha_inicio'        => 'date',
        'fecha_fin'           => 'date',
        'version_anterior_id' => 'integer',
        'creado_por'          => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
    ];

    /**
     * Tipo de documento al que pertenece la versión.
     *
     * @return BelongsTo
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(DocTipoDocumento::class, 'tipo_documento_id');
    }

    /**
     * Versión de la que se clonó esta plantilla.
     *
     * @return BelongsTo
     */
    public function versionAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'version_anterior_id');
    }

    /**
     * Configuración de impresión de los bloques de esta versión.
     *
     * @return HasMany
     */
    public function bloques(): HasMany
    {
        return $this->hasMany(DocPlantillaBloque::class, 'plantilla_id');
    }

    /**
     * Usuario que creó la versión.
     *
     * @return BelongsTo
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Scope de las versiones publicables como origen de un documento.
     *
     * Solo las versiones que alcanzaron el estado Activa participan: las que
     * siguen activas y las que ya fueron cerradas conservando su ventana de
     * vigencia histórica.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePublicadas(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVA, self::STATUS_INACTIVA])
            ->whereNotNull('fecha_inicio');
    }

    /**
     * Scope de las versiones cuya vigencia contiene la fecha indicada.
     *
     * @param Builder $query
     * @param Carbon  $fecha
     * @return Builder
     */
    public function scopeVigentesEn(Builder $query, Carbon $fecha): Builder
    {
        return $query->publicadas()
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(function (Builder $q) use ($fecha) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha);
            });
    }

    /**
     * Calcula el siguiente número de versión de un tipo de documento.
     *
     * @param int $tipoDocumentoId
     * @return int
     */
    public static function siguienteVersion(int $tipoDocumentoId): int
    {
        return (int) static::withTrashed()
            ->where('tipo_documento_id', $tipoDocumentoId)
            ->max('version') + 1;
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<int, string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'version', 'status', 'fecha_inicio', 'fecha_fin', 'created_at', 'updated_at'];
    }
}
