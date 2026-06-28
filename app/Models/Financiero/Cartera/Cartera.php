<?php

namespace App\Models\Financiero\Cartera;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use App\Traits\Financiero\HasCarteraStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Cartera
 *
 * Representa una cuenta por cobrar (cuota o cargo) generada al matricular a un estudiante.
 * La cartera es **inmutable**: no se edita ni se elimina. Solo cambia de estado mediante
 * acciones controladas: aplicarPago(), revertirPago(), anular() y marcarEnAcuerdo().
 *
 * Reglas de negocio:
 * - numero_cuota = 0 → cargo de matrícula (primer pago en el día de la matrícula)
 * - numero_cuota ≥ 1 → cuota mensual
 * - saldo = valor - abono - descuento
 * - El estado cambia automáticamente al aplicar/revertir pagos.
 *
 * @property int         $id
 * @property int         $matricula_id
 * @property int         $sede_id
 * @property int         $estudiante_id
 * @property int         $numero_cuota         0 = matrícula; 1..N = cuotas
 * @property float       $valor                valor original de la cuota
 * @property float       $saldo                pendiente de pago
 * @property float       $abono                total abonado
 * @property float       $descuento            descuento acumulado
 * @property string      $fecha_vencimiento    fecha límite de pago
 * @property int         $status               ver HasCarteraStatus::getStatusOptions()
 * @property string|null $observaciones
 *
 * @property-read Matricula $matricula
 * @property-read Sede      $sede
 * @property-read User      $estudiante
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReciboPago> $recibosPago
 */
class Cartera extends Model
{
    use HasFactory, HasFilterScopes, HasGenericScopes, HasRelationScopes, HasSortingScopes, HasCarteraStatus;

    protected $table = 'carteras';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'numero_cuota'     => 'integer',
        'valor'            => 'decimal:2',
        'saldo'            => 'decimal:2',
        'abono'            => 'decimal:2',
        'descuento'        => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'status'           => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Matrícula que origina esta deuda.
     */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /**
     * Sede donde se generó la matrícula.
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Estudiante deudor.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Recibos de pago que referencian directamente esta cartera (FK directa).
     * Solo el recibo de matrícula auto-generado usa esta relación.
     * Los recibos manuales post-matrícula usan recibo_pago_concepto_pago.id_relacional.
     */
    public function recibosPago(): HasMany
    {
        return $this->hasMany(ReciboPago::class, 'cartera_id');
    }

    // -------------------------------------------------------------------------
    // Lógica de negocio — inmutabilidad garantizada por ausencia de update/delete
    // -------------------------------------------------------------------------

    /**
     * Aplica un pago parcial o total a esta cartera.
     * Actualiza saldo, abono y status en una sola operación.
     *
     * @param  float $monto   monto a abonar (debe ser > 0 y <= saldo actual)
     */
    public function aplicarPago(float $monto): void
    {
        $nuevoAbono = (float) $this->abono + $monto;
        $nuevoSaldo = max(0, (float) $this->valor - $nuevoAbono - (float) $this->descuento);

        $status = $nuevoSaldo <= 0
            ? self::getStatusKey('Cerrada')
            : self::getStatusKey('Abonada');

        $this->update([
            'abono'  => $nuevoAbono,
            'saldo'  => $nuevoSaldo,
            'status' => $status,
        ]);
    }

    /**
     * Revierte un pago previamente aplicado (al anular un recibo).
     *
     * @param  float $monto   monto a revertir
     */
    public function revertirPago(float $monto): void
    {
        $nuevoAbono = max(0, (float) $this->abono - $monto);
        $nuevoSaldo = (float) $this->valor - $nuevoAbono - (float) $this->descuento;

        $status = $nuevoAbono > 0
            ? self::getStatusKey('Abonada')
            : self::getStatusKey('Activa');

        $this->update([
            'abono'  => $nuevoAbono,
            'saldo'  => $nuevoSaldo,
            'status' => $status,
        ]);
    }

    /**
     * Marca la cartera como Anulada.
     */
    public function anular(): void
    {
        $this->update(['status' => self::getStatusKey('Anulada')]);
    }

    /**
     * Marca la cartera como En Acuerdo (para reestructuración de deuda).
     */
    public function marcarEnAcuerdo(): void
    {
        $this->update(['status' => self::getStatusKey('En Acuerdo')]);
    }

    // -------------------------------------------------------------------------
    // Scopes de filtro
    // -------------------------------------------------------------------------

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeByMatricula($query, int $matriculaId)
    {
        return $query->where('matricula_id', $matriculaId);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeByEstudiante($query, int $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeBySede($query, int $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeByFechaVencimientoRange($query, string $desde, string $hasta)
    {
        return $query->whereBetween('fecha_vencimiento', [$desde, $hasta]);
    }

    // -------------------------------------------------------------------------
    // HasRelationScopes / HasSortingScopes / HasFilterScopes — configuración
    // -------------------------------------------------------------------------

    protected function getAllowedSortFields(): array
    {
        return [
            'numero_cuota',
            'valor',
            'saldo',
            'abono',
            'fecha_vencimiento',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    protected function getAllowedRelations(): array
    {
        return [
            'matricula',
            'sede',
            'estudiante',
            'recibosPago',
            'matricula.curso',
            'matricula.ciclo',
        ];
    }

    protected function getDefaultRelations(): array
    {
        return [];
    }

    protected function getCountableRelations(): array
    {
        return ['recibosPago'];
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    protected static function newFactory()
    {
        return \Database\Factories\Financiero\Cartera\CarteraFactory::new();
    }
}
