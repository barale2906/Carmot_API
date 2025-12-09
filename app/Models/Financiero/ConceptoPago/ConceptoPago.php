<?php

namespace App\Models\Financiero\ConceptoPago;

use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo ConceptoPago
 *
 * Representa un concepto de pago en el sistema financiero.
 * Los conceptos de pago organizan los diferentes conceptos por los cuales se van a recibir pagos
 * al momento de matricular a los estudiantes, recibir sus pagos por cobros adicionales,
 * recargos por pago con tarjeta, pagos por acuerdo de pago, etc.
 *
 * @property int $id Identificador único del concepto de pago
 * @property string $nombre Nombre del concepto de pago
 * @property int $tipo Índice del tipo del concepto (0=Cartera, 1=Financiero, 2=Inventario, 3=Otro)
 * @property float $valor Valor del concepto de pago (hasta 2 decimales)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 */
class ConceptoPago extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'conceptos_pago';

    /**
     * Los atributos que no son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser tratados como fechas.
     *
     * @var array<string>
     */
    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => 'integer',
        'valor' => 'decimal:2',
    ];

    /**
     * Tipos de concepto de pago disponibles.
     * El índice del array corresponde al valor que se guarda en la base de datos.
     * Se puede ampliar dinámicamente usando el método agregarTipo().
     *
     * @var array<int, string>
     */
    private static ?array $tiposDisponibles = null;

    /**
     * Tipos de concepto de pago disponibles por defecto (constante para referencia).
     *
     * @var array<int, string>
     */
    public const TIPOS_DEFAULT = [
        0 => 'Cartera',
        1 => 'Financiero',
        2 => 'Inventario',
        3 => 'Otro',
    ];

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'tipo',
            'valor',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Relación con RecibosPago (muchos a muchos).
     * Un concepto de pago puede estar en múltiples recibos de pago.
     * La relación se establece a través de la tabla pivot recibo_pago_concepto_pago.
     *
     * @return BelongsToMany
     */
    public function recibosPago(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Financiero\ReciboPago\ReciboPago::class,
            'recibo_pago_concepto_pago',
            'concepto_pago_id',
            'recibo_pago_id'
        )->withPivot(['valor', 'tipo', 'producto', 'cantidad', 'unitario', 'subtotal', 'id_relacional', 'observaciones'])
         ->withTimestamps();
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array<string>
     */
    protected function getAllowedRelations(): array
    {
        return [
            'recibosPago'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return [];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [];
    }

    /**
     * Inicializa el array de tipos disponibles si aún no está inicializado.
     *
     * @return void
     */
    private static function inicializarTipos(): void
    {
        if (self::$tiposDisponibles === null) {
            self::$tiposDisponibles = self::TIPOS_DEFAULT;
        }
    }

    /**
     * Obtiene el nombre del tipo según su índice.
     *
     * @param int|null $indice Índice del tipo (si es null, usa el tipo del modelo actual)
     * @return string|null Nombre del tipo o null si no existe
     */
    public function getNombreTipo(?int $indice = null): ?string
    {
        self::inicializarTipos();
        $indice = $indice ?? $this->tipo;
        return self::$tiposDisponibles[$indice] ?? null;
    }

    /**
     * Obtiene todos los tipos disponibles con sus índices.
     *
     * @return array<int, string>
     */
    public static function getTiposDisponibles(): array
    {
        self::inicializarTipos();
        return self::$tiposDisponibles;
    }

    /**
     * Obtiene el índice de un tipo por su nombre.
     *
     * @param string $nombreTipo Nombre del tipo a buscar
     * @return int|null Índice del tipo o null si no existe
     */
    public static function getIndicePorNombre(string $nombreTipo): ?int
    {
        self::inicializarTipos();
        $indice = array_search($nombreTipo, self::$tiposDisponibles, true);
        return $indice !== false ? (int) $indice : null;
    }

    /**
     * Verifica si un índice de tipo es válido.
     *
     * @param int $indice Índice a verificar
     * @return bool True si el índice es válido
     */
    public static function esIndiceValido(int $indice): bool
    {
        self::inicializarTipos();
        return isset(self::$tiposDisponibles[$indice]);
    }

    /**
     * Agrega un nuevo tipo al array de tipos disponibles.
     * El nuevo tipo se agregará con el siguiente índice disponible.
     *
     * @param string $nuevoTipo Nombre del nuevo tipo a agregar
     * @return int|null Índice del nuevo tipo agregado, o null si ya existe
     */
    public static function agregarTipo(string $nuevoTipo): ?int
    {
        self::inicializarTipos();

        // Verificar si el tipo ya existe
        if (in_array($nuevoTipo, self::$tiposDisponibles, true)) {
            return null;
        }

        // Obtener el siguiente índice disponible
        $indices = array_keys(self::$tiposDisponibles);
        $nuevoIndice = !empty($indices) ? max($indices) + 1 : 0;

        // Agregar el nuevo tipo
        self::$tiposDisponibles[$nuevoIndice] = $nuevoTipo;

        return $nuevoIndice;
    }

    /**
     * Accessor para obtener el nombre del tipo como atributo del modelo.
     *
     * @return string|null
     */
    public function getTipoNombreAttribute(): ?string
    {
        return $this->getNombreTipo();
    }
}

