<?php

namespace App\Models\Financiero\Lp;

use App\Models\Configuracion\Poblacion;
use App\Traits\Financiero\HasListaPrecioStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo LpListaPrecio
 *
 * Representa una lista de precios en el sistema financiero.
 * Una lista de precios define los precios de productos para un período de vigencia específico
 * y puede aplicarse a una o más poblaciones (ciudades).
 * Las listas de precios tienen estados: Inactiva, En Proceso, Aprobada y Activa.
 *
 * @property int $id Identificador único de la lista de precios
 * @property string $nombre Nombre descriptivo de la lista
 * @property string|null $codigo Código único de la lista
 * @property \Carbon\Carbon $fecha_inicio Fecha de inicio de vigencia
 * @property \Carbon\Carbon $fecha_fin Fecha de fin de vigencia
 * @property string|null $descripcion Descripción de la lista de precios
 * @property int $status Estado de la lista (0: inactiva, 1: en proceso, 2: aprobada, 3: activa)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Configuracion\Poblacion> $poblaciones Poblaciones donde aplica la lista
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpPrecioProducto> $preciosProductos Precios de productos en esta lista
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpProducto> $productos Productos incluidos en esta lista
 */
class LpListaPrecio extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes, HasListaPrecioStatus;

    /**
     * Constante para el estado Inactiva.
     */
    const STATUS_INACTIVA = 0;

    /**
     * Constante para el estado En Proceso.
     */
    const STATUS_EN_PROCESO = 1;

    /**
     * Constante para el estado Aprobada.
     */
    const STATUS_APROBADA = 2;

    /**
     * Constante para el estado Activa.
     */
    const STATUS_ACTIVA = 3;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'lp_listas_precios';

    /**
     * Los atributos que no son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'status' => 'integer',
    ];

    /**
     * Relación con Poblacion (muchos a muchos).
     * Una lista de precios puede aplicarse a múltiples poblaciones (ciudades).
     * La relación se establece a través de la tabla pivot lp_lista_precio_poblacion.
     *
     * @return BelongsToMany
     */
    public function poblaciones(): BelongsToMany
    {
        return $this->belongsToMany(Poblacion::class, 'lp_lista_precio_poblacion', 'lista_precio_id', 'poblacion_id')
                    ->withTimestamps();
    }

    /**
     * Relación con LpPrecioProducto (uno a muchos).
     * Una lista de precios puede tener múltiples precios de productos.
     *
     * @return HasMany
     */
    public function preciosProductos(): HasMany
    {
        return $this->hasMany(LpPrecioProducto::class, 'lista_precio_id');
    }

    /**
     * Relación con LpProducto (muchos a muchos).
     * Una lista de precios puede incluir múltiples productos.
     * La relación se establece a través de la tabla pivot lp_precios_producto.
     *
     * @return BelongsToMany
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(LpProducto::class, 'lp_precios_producto', 'lista_precio_id', 'producto_id')
                    ->withPivot(['precio_contado', 'precio_total', 'matricula', 'numero_cuotas', 'valor_cuota', 'observaciones'])
                    ->withTimestamps();
    }

    /**
     * Verifica si la lista está vigente en una fecha específica.
     * Una lista está vigente si está activa (status = 3) y la fecha está dentro del rango de vigencia.
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return bool True si la lista está vigente, false en caso contrario
     */
    public function estaVigente(?Carbon $fecha = null): bool
    {
        $fecha = $fecha ?? Carbon::now();
        return $fecha->between($this->fecha_inicio, $this->fecha_fin)
            && $this->status === self::STATUS_ACTIVA;
    }

    /**
     * Scope para filtrar listas vigentes.
     * Solo retorna listas que están activas y cuya fecha está dentro del rango de vigencia.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVigentes($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('fecha_inicio', '<=', $fecha)
                    ->where('fecha_fin', '>=', $fecha)
                    ->where('status', self::STATUS_ACTIVA);
    }

    /**
     * Scope para filtrar listas aprobadas que deben activarse automáticamente.
     * Retorna listas que están en estado "Aprobada" y cuya fecha de inicio ya llegó o pasó.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAprobadasParaActivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::STATUS_APROBADA)
                    ->where('fecha_inicio', '<=', $fecha);
    }

    /**
     * Scope para filtrar listas activas que deben inactivarse por pérdida de vigencia.
     * Retorna listas que están activas pero cuya fecha de fin ya pasó.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivasParaInactivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::STATUS_ACTIVA)
                    ->where('fecha_fin', '<', $fecha);
    }

    /**
     * Activa automáticamente las listas aprobadas cuando inicia su vigencia.
     * Este método debe ejecutarse mediante un comando programado (cron).
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return void
     */
    public static function activarListasAprobadas(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::aprobadasParaActivar($fecha)->update([
            'status' => self::STATUS_ACTIVA
        ]);
    }

    /**
     * Inactiva automáticamente las listas activas que han perdido su vigencia.
     * Este método debe ejecutarse mediante un comando programado (cron).
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return void
     */
    public static function inactivarListasVencidas(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::activasParaInactivar($fecha)->update([
            'status' => self::STATUS_INACTIVA
        ]);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'codigo',
            'fecha_inicio',
            'fecha_fin',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array<string>
     */
    protected function getAllowedRelations(): array
    {
        return [
            'poblaciones',
            'preciosProductos',
            'productos',
            'preciosProductos.producto',
            'preciosProductos.producto.tipoProducto'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return ['poblaciones'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [
            'poblaciones',
            'preciosProductos',
            'productos'
        ];
    }
}
