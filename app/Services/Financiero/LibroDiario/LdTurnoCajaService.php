<?php

namespace App\Services\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdAutorizacionTurno;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\LibroDiario\LdTurnoEvento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio LdTurnoCajaService
 *
 * Ciclo de vida del turno de caja de un cajero: apertura con base inicial,
 * resumen (arqueo), cierre ciego, aprobación o rechazo del cierre, reversión y
 * ajuste del cierre cuando se anula un registro después de cerrado.
 *
 * Reglas:
 *  - Un cajero tiene a lo sumo un turno abierto.
 *  - Tras cerrar un turno, el cajero no abre otro ese día sin una autorización.
 *  - El cierre queda Por aprobar; lo aprueba o rechaza alguien distinto del cajero.
 *  - Al cerrar, los recibos creados del turno pasan a Cerrado con cierre = id del turno;
 *    al reversar vuelven a Creado. Las transferencias por aprobar no se cierran.
 *  - efectivo esperado = base + ingresos en efectivo - egresos en efectivo - consignaciones.
 */
class LdTurnoCajaService
{
    /**
     * Turno abierto del cajero, en cualquier sede.
     */
    public function turnoAbiertoDe(int $cajeroId): ?LdTurnoCaja
    {
        return LdTurnoCaja::where('cajero_id', $cajeroId)
            ->where('status', LdTurnoCaja::STATUS_ABIERTO)
            ->first();
    }

    /**
     * Motivo por el que el cajero no puede abrir un turno hoy, o null si puede.
     */
    public function motivoNoPuedeAbrir(int $cajeroId): ?string
    {
        if ($this->turnoAbiertoDe($cajeroId)) {
            return 'Ya tiene un turno de caja abierto. Debe cerrarlo antes de abrir otro.';
        }

        if ($this->cerroTurnoHoy($cajeroId) && ! $this->autorizacionDisponible($cajeroId)) {
            return 'Ya cerró caja hoy. Para abrir un nuevo turno necesita la autorización de un supervisor.';
        }

        return null;
    }

    /**
     * Abre el turno con la base inicial. Si el cajero ya cerró caja hoy,
     * consume la autorización disponible.
     *
     * @throws RuntimeException Si el cajero no puede abrir turno.
     */
    public function abrir(User $cajero, int $sedeId, float $baseInicial): LdTurnoCaja
    {
        return DB::transaction(function () use ($cajero, $sedeId, $baseInicial) {
            $motivo = $this->motivoNoPuedeAbrir($cajero->id);
            if ($motivo) {
                throw new RuntimeException($motivo);
            }

            $autorizacion = $this->cerroTurnoHoy($cajero->id)
                ? $this->autorizacionDisponible($cajero->id)
                : null;

            $turno = LdTurnoCaja::create([
                'sede_id' => $sedeId,
                'cajero_id' => $cajero->id,
                'fecha' => today(),
                'base_inicial' => $baseInicial,
                'abierto_at' => now(),
                'status' => LdTurnoCaja::STATUS_ABIERTO,
                'autorizacion_id' => $autorizacion?->id,
            ]);

            $autorizacion?->update(['turno_caja_id' => $turno->id]);

            $this->registrarEvento($turno, LdTurnoEvento::ACCION_APERTURA, $cajero, null, [
                'base_inicial' => $baseInicial,
            ]);

            return $turno;
        });
    }

    /**
     * Calcula el resumen del turno con lo registrado a la fecha.
     *
     * Los ingresos por recibos se toman de sus medios de pago (lo realmente recibido).
     * El desglose por medio de pago incluye todos los medios (aunque estén en cero),
     * con los ingresos separados en académico, inventario y otros ingresos;
     * cuentan los recibos Creados y Cerrados, no los anulados ni los por aprobar.
     *
     * @return array<string, mixed>
     */
    public function calcularResumen(LdTurnoCaja $turno): array
    {
        $porMedio = collect(array_keys(ReciboPagoMedioPago::MEDIOS))
            ->mapWithKeys(fn ($medio) => [$medio => self::filaMedioVacia()])
            ->all();

        $ingresos = ['academico' => 0.0, 'inventario' => 0.0, 'otros' => 0.0];

        $recibos = ReciboPagoMedioPago::query()
            ->join('recibos_pago', 'recibos_pago.id', '=', 'recibo_pago_medio_pago.recibo_pago_id')
            ->where('recibos_pago.turno_caja_id', $turno->id)
            ->whereIn('recibos_pago.status', [ReciboPago::STATUS_CREADO, ReciboPago::STATUS_CERRADO])
            ->groupBy('recibos_pago.origen', 'recibo_pago_medio_pago.medio_pago')
            ->selectRaw('recibos_pago.origen, recibo_pago_medio_pago.medio_pago, SUM(recibo_pago_medio_pago.valor) AS total')
            ->get();

        foreach ($recibos as $fila) {
            $clave = (int) $fila->origen === ReciboPago::ORIGEN_ACADEMICO ? 'academico' : 'inventario';
            $ingresos[$clave] += (float) $fila->total;
            $porMedio[$fila->medio_pago] ??= self::filaMedioVacia();
            $porMedio[$fila->medio_pago][$clave] += (float) $fila->total;
            $porMedio[$fila->medio_pago]['ingresos'] += (float) $fila->total;
        }

        $egresos = 0.0;
        $movimientos = LdMovimiento::registrados()
            ->where('turno_caja_id', $turno->id)
            ->groupBy('clase', 'medio_pago')
            ->selectRaw('clase, medio_pago, SUM(valor_total) AS total, COUNT(*) AS cantidad')
            ->get();

        foreach ($movimientos as $fila) {
            $esIngreso = (int) $fila->clase === LdTipoMovimiento::CLASE_INGRESO;
            $porMedio[$fila->medio_pago] ??= self::filaMedioVacia();
            if ($esIngreso) {
                $ingresos['otros'] += (float) $fila->total;
                $porMedio[$fila->medio_pago]['otros'] += (float) $fila->total;
                $porMedio[$fila->medio_pago]['ingresos'] += (float) $fila->total;
            } else {
                $egresos += (float) $fila->total;
                $porMedio[$fila->medio_pago]['egresos'] += (float) $fila->total;
            }
        }

        $consignaciones = LdConsignacion::registrados()->where('turno_caja_id', $turno->id);
        $totalConsignado = (float) (clone $consignaciones)->sum('valor');

        $pendientes = ReciboPago::where('turno_caja_id', $turno->id)
            ->where('status', ReciboPago::STATUS_PENDIENTE_APROBACION);

        $base = (float) $turno->base_inicial;
        $efectivoEsperado = $base
            + $porMedio['efectivo']['ingresos']
            - $porMedio['efectivo']['egresos']
            - $totalConsignado;

        return [
            'base_inicial' => round($base, 2),
            'ingresos' => array_map(fn ($v) => round($v, 2), $ingresos) + [
                'total' => round(array_sum($ingresos), 2),
            ],
            'egresos' => round($egresos, 2),
            'consignaciones' => round($totalConsignado, 2),
            'por_medio_pago' => array_map(fn ($m) => array_map(fn ($v) => round($v, 2), $m), $porMedio),
            'efectivo_esperado' => round($efectivoEsperado, 2),
            'cantidades' => [
                'recibos' => ReciboPago::where('turno_caja_id', $turno->id)
                    ->whereIn('status', [ReciboPago::STATUS_CREADO, ReciboPago::STATUS_CERRADO])->count(),
                'movimientos' => (int) $movimientos->sum('cantidad'),
                'consignaciones' => (clone $consignaciones)->count(),
            ],
            'transferencias_pendientes' => [
                'cantidad' => (clone $pendientes)->count(),
                'valor' => round((float) (clone $pendientes)->sum('valor_total'), 2),
            ],
        ];
    }

    /**
     * El cajero cierra su turno con el efectivo contado (arqueo ciego). El turno queda
     * Por aprobar: ya no admite pagos ni registros, y sus recibos creados pasan a Cerrado.
     *
     * @throws RuntimeException Si el turno no está abierto.
     */
    public function cerrar(LdTurnoCaja $turno, float $efectivoContado, ?string $observaciones, User $usuario): LdTurnoCaja
    {
        if (! $turno->estaAbierto()) {
            throw new RuntimeException('El turno de caja ya fue cerrado.');
        }

        return DB::transaction(function () use ($turno, $efectivoContado, $observaciones, $usuario) {
            ReciboPago::where('turno_caja_id', $turno->id)
                ->where('status', ReciboPago::STATUS_CREADO)
                ->update(['status' => ReciboPago::STATUS_CERRADO, 'cierre' => $turno->id]);

            $resumen = $this->calcularResumen($turno);

            $turno->update([
                'status' => LdTurnoCaja::STATUS_POR_APROBAR,
                'cerrado_at' => now(),
                'cerrado_por_id' => $usuario->id,
                'resumen' => $resumen,
                'efectivo_esperado' => $resumen['efectivo_esperado'],
                'efectivo_contado' => $efectivoContado,
                'diferencia' => round($efectivoContado - $resumen['efectivo_esperado'], 2),
                'observaciones_cierre' => $observaciones,
            ]);

            $this->registrarEvento($turno, LdTurnoEvento::ACCION_CIERRE, $usuario, $observaciones, [
                'efectivo_esperado' => $resumen['efectivo_esperado'],
                'efectivo_contado' => $efectivoContado,
            ]);

            return $turno->fresh();
        });
    }

    /**
     * Aprueba el cierre de un turno Por aprobar. Quien aprueba no puede ser quien cerró
     * ni el cajero del turno.
     *
     * @throws RuntimeException Si el cierre no está por aprobar o el usuario aprueba su propio cierre.
     */
    public function aprobar(LdTurnoCaja $turno, ?string $observaciones, User $usuario): LdTurnoCaja
    {
        $this->validarRevisionDelCierre($turno, $usuario);

        return DB::transaction(function () use ($turno, $observaciones, $usuario) {
            $turno->update([
                'status' => LdTurnoCaja::STATUS_CERRADO,
                'aprobado_por_id' => $usuario->id,
                'aprobado_at' => now(),
                'observaciones_aprobacion' => $observaciones,
            ]);

            $this->registrarEvento($turno, LdTurnoEvento::ACCION_APROBACION, $usuario, $observaciones, [
                'efectivo_esperado' => (float) $turno->efectivo_esperado,
                'efectivo_contado' => (float) $turno->efectivo_contado,
                'diferencia' => (float) $turno->diferencia,
            ]);

            return $turno->fresh();
        });
    }

    /**
     * Rechaza el cierre: el turno vuelve a Abierto para que el cajero corrija o
     * vuelva a contar, y sus recibos vuelven a Creado.
     *
     * @throws RuntimeException Si el cierre no está por aprobar, es propio o el cajero tiene otro turno abierto.
     */
    public function rechazar(LdTurnoCaja $turno, string $motivo, User $usuario): LdTurnoCaja
    {
        $this->validarRevisionDelCierre($turno, $usuario);

        return $this->reabrir($turno, LdTurnoEvento::ACCION_RECHAZO, $motivo, $usuario);
    }

    /**
     * Reabre un turno ya aprobado para corregirlo. Los recibos cerrados por este turno
     * vuelven a Creado y el cierre anterior queda en la bitácora.
     *
     * @throws RuntimeException Si el turno no está cerrado o el cajero ya tiene otro turno abierto.
     */
    public function reversar(LdTurnoCaja $turno, string $motivo, User $usuario): LdTurnoCaja
    {
        if ($turno->estaPorAprobar()) {
            throw new RuntimeException('El cierre está pendiente de aprobación; recházelo para reabrir el turno.');
        }

        if ($turno->estaAbierto()) {
            throw new RuntimeException('El turno de caja está abierto; no hay cierre que reversar.');
        }

        return $this->reabrir($turno, LdTurnoEvento::ACCION_REVERSION, $motivo, $usuario);
    }

    /**
     * Anula un movimiento o consignación. Si su turno ya está cerrado, recalcula
     * el resumen y la diferencia del cierre, lo marca como ajustado y lo deja en la bitácora.
     *
     * @param  LdMovimiento|LdConsignacion  $registro
     */
    public function anularRegistro(Model $registro, string $motivo, User $usuario): void
    {
        DB::transaction(function () use ($registro, $motivo, $usuario) {
            $registro->anular($usuario->id, $motivo);

            $turno = $registro->turnoCaja;
            if ($turno->estaAbierto()) {
                return;
            }

            $resumen = $this->calcularResumen($turno);

            $turno->update([
                'resumen' => $resumen,
                'efectivo_esperado' => $resumen['efectivo_esperado'],
                'diferencia' => round((float) $turno->efectivo_contado - $resumen['efectivo_esperado'], 2),
                'ajustado' => true,
            ]);

            $this->registrarEvento($turno, LdTurnoEvento::ACCION_ANULACION, $usuario, $motivo, [
                'registro' => $registro instanceof LdMovimiento ? 'movimiento' : 'consignacion',
                'id' => $registro->id,
                'valor' => (float) ($registro->valor_total ?? $registro->valor),
            ]);
        });
    }

    /**
     * Fila del desglose por medio de pago: ingresos por origen (académico, inventario,
     * otros), total de ingresos y egresos.
     *
     * @return array<string, float>
     */
    private static function filaMedioVacia(): array
    {
        return ['academico' => 0.0, 'inventario' => 0.0, 'otros' => 0.0, 'ingresos' => 0.0, 'egresos' => 0.0];
    }

    /**
     * El cierre debe estar Por aprobar y lo revisa alguien distinto del cajero y de quien cerró.
     *
     * @throws RuntimeException
     */
    private function validarRevisionDelCierre(LdTurnoCaja $turno, User $usuario): void
    {
        if (! $turno->estaPorAprobar()) {
            throw new RuntimeException('El cierre de este turno no está pendiente de aprobación.');
        }

        if (in_array($usuario->id, [$turno->cajero_id, $turno->cerrado_por_id], true)) {
            throw new RuntimeException('No puede aprobar ni rechazar un cierre de caja propio.');
        }
    }

    /**
     * Devuelve el turno a Abierto: los recibos que cerró vuelven a Creado y el cierre
     * anterior queda en la bitácora con la acción indicada (rechazo o reversión).
     *
     * @throws RuntimeException Si el cajero ya tiene otro turno abierto.
     */
    private function reabrir(LdTurnoCaja $turno, string $accion, string $motivo, User $usuario): LdTurnoCaja
    {
        if ($this->turnoAbiertoDe($turno->cajero_id)) {
            throw new RuntimeException('El cajero tiene otro turno abierto. Debe cerrarlo antes de reabrir este.');
        }

        return DB::transaction(function () use ($turno, $accion, $motivo, $usuario) {
            ReciboPago::where('turno_caja_id', $turno->id)
                ->where('status', ReciboPago::STATUS_CERRADO)
                ->where('cierre', $turno->id)
                ->update(['status' => ReciboPago::STATUS_CREADO, 'cierre' => null]);

            $this->registrarEvento($turno, $accion, $usuario, $motivo, [
                'resumen' => $turno->resumen,
                'efectivo_contado' => $turno->efectivo_contado,
                'diferencia' => $turno->diferencia,
            ]);

            $turno->update([
                'status' => LdTurnoCaja::STATUS_ABIERTO,
                'cerrado_at' => null,
                'cerrado_por_id' => null,
                'resumen' => null,
                'efectivo_esperado' => null,
                'efectivo_contado' => null,
                'diferencia' => null,
                'observaciones_cierre' => null,
                'aprobado_por_id' => null,
                'aprobado_at' => null,
                'observaciones_aprobacion' => null,
                'ajustado' => false,
            ]);

            return $turno->fresh();
        });
    }

    /**
     * Indica si el cajero ya cerró algún turno hoy (aprobado o por aprobar).
     */
    private function cerroTurnoHoy(int $cajeroId): bool
    {
        return LdTurnoCaja::where('cajero_id', $cajeroId)
            ->whereIn('status', [LdTurnoCaja::STATUS_CERRADO, LdTurnoCaja::STATUS_POR_APROBAR])
            ->whereDate('fecha', today())
            ->exists();
    }

    /**
     * Autorización sin usar del cajero para hoy.
     */
    private function autorizacionDisponible(int $cajeroId): ?LdAutorizacionTurno
    {
        return LdAutorizacionTurno::disponibleDe($cajeroId, today()->toDateString())->first();
    }

    /**
     * Agrega un evento a la bitácora del turno.
     *
     * @param  array<string, mixed>|null  $datos
     */
    private function registrarEvento(LdTurnoCaja $turno, string $accion, User $usuario, ?string $motivo, ?array $datos = null): void
    {
        LdTurnoEvento::create([
            'turno_caja_id' => $turno->id,
            'accion' => $accion,
            'usuario_id' => $usuario->id,
            'motivo' => $motivo,
            'datos' => $datos,
        ]);
    }
}
