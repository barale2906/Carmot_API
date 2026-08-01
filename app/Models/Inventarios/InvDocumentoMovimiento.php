<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo InvDocumentoMovimiento — cabecera de una transacción de stock.
 *
 * Los documentos son inmutables una vez confirmados; solo se pueden anular.
 * Un documento contiene una o más líneas (InvMovimiento).
 *
 * @property int         $id
 * @property string      $numero_documento
 * @property string      $tipo_documento   entrada|salida|traslado|ajuste|devolucion
 * @property int         $almacen_id
 * @property int|null    $almacen_destino_id
 * @property int|null    $proveedor_id
 * @property int|null    $pedido_id
 * @property string|null $motivo
 * @property string      $status           borrador|confirmado|anulado
 * @property string|null $motivo_anulacion
 * @property int         $user_id
 */
class InvDocumentoMovimiento extends Model
{
    use HasFactory;

    // Constantes de tipo
    public const TIPO_ENTRADA    = 'entrada';
    public const TIPO_SALIDA     = 'salida';
    public const TIPO_TRASLADO   = 'traslado';
    public const TIPO_AJUSTE     = 'ajuste';
    public const TIPO_DEVOLUCION = 'devolucion';

    // Constantes de status
    public const STATUS_BORRADOR   = 'borrador';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_ANULADO    = 'anulado';

    // Prefijos de numeración por tipo
    public const PREFIJOS_TIPO = [
        'entrada'    => 'ENT',
        'salida'     => 'SAL',
        'traslado'   => 'TRL',
        'ajuste'     => 'AJU',
        'devolucion' => 'DEV',
    ];

    protected $table = 'inv_documentos_movimiento';

    protected $guarded = ['id'];

    protected $casts = [
        'almacen_id'         => 'integer',
        'almacen_destino_id' => 'integer',
        'proveedor_id'       => 'integer',
        'pedido_id'          => 'integer',
        'user_id'            => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /**
     * Genera el siguiente número de documento para el tipo indicado.
     * Formato: {PREFIJO}-{AÑO}-{CONSECUTIVO 6 dígitos}
     *
     * @param string $tipo
     * @return string
     */
    public static function generarNumero(string $tipo): string
    {
        $prefijo = self::PREFIJOS_TIPO[$tipo] ?? 'MOV';
        $anio    = now()->year;
        $patron  = "{$prefijo}-{$anio}-%";

        $ultimo = static::where('numero_documento', 'LIKE', $patron)
            ->orderByDesc('id')
            ->value('numero_documento');

        $consecutivo = $ultimo ? ((int) substr($ultimo, -6)) + 1 : 1;

        return sprintf('%s-%d-%06d', $prefijo, $anio, $consecutivo);
    }

    /**
     * Almacén origen.
     *
     * @return BelongsTo
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    /**
     * Almacén destino (solo traslados).
     *
     * @return BelongsTo
     */
    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_destino_id');
    }

    /**
     * Proveedor asociado (solo entradas de compra).
     *
     * @return BelongsTo
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(InvProveedor::class, 'proveedor_id');
    }

    /**
     * Usuario que registró el documento.
     *
     * @return BelongsTo
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Líneas de movimiento del documento.
     *
     * @return HasMany
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(InvMovimiento::class, 'documento_id');
    }

    /**
     * Scope para filtrar por tipo de documento.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tipo
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTipo($query, string $tipo)
    {
        return $query->where('tipo_documento', $tipo);
    }

    /**
     * Scope para filtrar por almacén (origen o destino).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $almacenId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAlmacen($query, int $almacenId)
    {
        return $query->where(function ($q) use ($almacenId) {
            $q->where('almacen_id', $almacenId)
              ->orWhere('almacen_destino_id', $almacenId);
        });
    }

    /**
     * Scope para filtrar por status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por rango de fechas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $inicio
     * @param string $fin
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechas($query, string $inicio, string $fin)
    {
        return $query->whereBetween('created_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59']);
    }

    /**
     * Aplica todos los filtros disponibles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(
                isset($filters['tipo_documento']) && $filters['tipo_documento'] !== '',
                fn ($q) => $q->byTipo($filters['tipo_documento'])
            )
            ->when(
                isset($filters['almacen_id']) && $filters['almacen_id'] !== '',
                fn ($q) => $q->byAlmacen((int) $filters['almacen_id'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->byStatus($filters['status'])
            )
            ->when(
                isset($filters['fecha_inicio'], $filters['fecha_fin'])
                && $filters['fecha_inicio'] && $filters['fecha_fin'],
                fn ($q) => $q->byFechas($filters['fecha_inicio'], $filters['fecha_fin'])
            );
    }
}
