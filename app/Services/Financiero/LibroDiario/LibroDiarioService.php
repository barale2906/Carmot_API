<?php

namespace App\Services\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Servicio LibroDiarioService
 *
 * Arma el libro diario de las sedes: une en una sola consulta los recibos de pago
 * (ingresos académicos y de inventario), los movimientos manuales (egresos y
 * otros ingresos) y las consignaciones de efectivo, sin duplicar datos.
 *
 * Los recibos cuentan solo si están Creados o Cerrados (no anulados ni
 * transferencias por aprobar); los registros manuales, solo si no están anulados.
 */
class LibroDiarioService
{
    public const ORIGEN_RECIBO_ACADEMICO = 'recibo_academico';

    public const ORIGEN_RECIBO_INVENTARIO = 'recibo_inventario';

    public const ORIGEN_MOVIMIENTO = 'movimiento';

    public const ORIGEN_CONSIGNACION = 'consignacion';

    public const NATURALEZA_INGRESO = 'ingreso';

    public const NATURALEZA_EGRESO = 'egreso';

    public const NATURALEZA_CONSIGNACION = 'consignacion';

    /**
     * Orígenes de los registros del libro.
     *
     * @return array<string, string>
     */
    public static function getOrigenOptions(): array
    {
        return [
            self::ORIGEN_RECIBO_ACADEMICO => 'Recibo académico',
            self::ORIGEN_RECIBO_INVENTARIO => 'Recibo de inventario',
            self::ORIGEN_MOVIMIENTO => 'Movimiento manual',
            self::ORIGEN_CONSIGNACION => 'Consignación',
        ];
    }

    /**
     * Naturalezas de los registros del libro.
     *
     * @return array<string, string>
     */
    public static function getNaturalezaOptions(): array
    {
        return [
            self::NATURALEZA_INGRESO => 'Ingreso',
            self::NATURALEZA_EGRESO => 'Egreso',
            self::NATURALEZA_CONSIGNACION => 'Consignación',
        ];
    }

    /**
     * Registros del libro paginados, del más reciente al más antiguo.
     *
     * @param  array<int>  $sedeIds  Sedes accesibles por el usuario
     * @param  array<string, mixed>  $filtros  sede_id, fecha_desde, fecha_hasta, origen, naturaleza, turno_caja_id, search
     */
    public function registros(array $sedeIds, array $filtros, int $porPagina): LengthAwarePaginator
    {
        return DB::query()
            ->fromSub($this->union($sedeIds, $filtros), 'libro')
            ->when(! empty($filtros['origen']), fn ($q) => $q->where('origen', $filtros['origen']))
            ->when(! empty($filtros['naturaleza']), fn ($q) => $q->where('naturaleza', $filtros['naturaleza']))
            ->when(! empty($filtros['search']), fn ($q) => $q->where(function ($q) use ($filtros) {
                $q->where('tercero', 'like', '%'.$filtros['search'].'%')
                    ->orWhere('documento', 'like', '%'.$filtros['search'].'%')
                    ->orWhere('usuario', 'like', '%'.$filtros['search'].'%');
            }))
            ->orderByDesc('fecha')
            ->orderByDesc('creado_at')
            ->paginate($porPagina);
    }

    /**
     * Totales por sede: ingresos académicos, de inventario y otros, egresos,
     * consignaciones y neto (ingresos - egresos).
     *
     * @param  array<int>  $sedeIds  Sedes accesibles por el usuario
     * @param  array<string, mixed>  $filtros  sede_id, fecha_desde, fecha_hasta, turno_caja_id
     * @return array{sedes: array<int, array<string, mixed>>, totales: array<string, float>}
     */
    public function resumen(array $sedeIds, array $filtros): array
    {
        $filas = DB::query()
            ->fromSub($this->union($sedeIds, $filtros), 'libro')
            ->groupBy('sede_id', 'origen', 'naturaleza')
            ->selectRaw('sede_id, origen, naturaleza, SUM(valor) AS total')
            ->get()
            ->groupBy('sede_id');

        $nombres = Sede::whereIn('id', $filas->keys())->pluck('nombre', 'id');

        $sedes = $filas->map(function ($grupo, $sedeId) use ($nombres) {
            $total = fn (string $origen, ?string $naturaleza = null) => round((float) $grupo
                ->where('origen', $origen)
                ->when($naturaleza, fn ($c) => $c->where('naturaleza', $naturaleza))
                ->sum('total'), 2);

            $fila = [
                'sede_id' => (int) $sedeId,
                'sede' => $nombres[$sedeId] ?? null,
                'ingresos_academico' => $total(self::ORIGEN_RECIBO_ACADEMICO),
                'ingresos_inventario' => $total(self::ORIGEN_RECIBO_INVENTARIO),
                'otros_ingresos' => $total(self::ORIGEN_MOVIMIENTO, self::NATURALEZA_INGRESO),
                'egresos' => $total(self::ORIGEN_MOVIMIENTO, self::NATURALEZA_EGRESO),
                'consignaciones' => $total(self::ORIGEN_CONSIGNACION),
            ];
            $fila['total_ingresos'] = round($fila['ingresos_academico'] + $fila['ingresos_inventario'] + $fila['otros_ingresos'], 2);
            $fila['neto'] = round($fila['total_ingresos'] - $fila['egresos'], 2);

            return $fila;
        })->values();

        $campos = ['ingresos_academico', 'ingresos_inventario', 'otros_ingresos', 'egresos', 'consignaciones', 'total_ingresos', 'neto'];

        return [
            'sedes' => $sedes->all(),
            'totales' => collect($campos)->mapWithKeys(fn ($c) => [$c => round($sedes->sum($c), 2)])->all(),
        ];
    }

    /**
     * Une recibos, movimientos y consignaciones con columnas comunes.
     *
     * @param  array<int>  $sedeIds
     * @param  array<string, mixed>  $filtros
     */
    private function union(array $sedeIds, array $filtros): Builder
    {
        $nombreUsuario = fn (string $tabla) => "TRIM(CONCAT_WS(' ', {$tabla}.primer_nombre, {$tabla}.segundo_nombre, {$tabla}.primer_apellido, {$tabla}.segundo_apellido))";

        $recibos = DB::table('recibos_pago as r')
            ->leftJoin('users as e', 'e.id', '=', 'r.estudiante_id')
            ->join('sedes as s', 's.id', '=', 'r.sede_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.cajero_id')
            ->whereIn('r.status', [ReciboPago::STATUS_CREADO, ReciboPago::STATUS_CERRADO])
            ->selectRaw("
                CASE WHEN r.origen = ? THEN ? ELSE ? END AS origen,
                r.id AS registro_id, r.sede_id, s.nombre AS sede, r.turno_caja_id,
                r.cajero_id AS usuario_id, {$nombreUsuario('u')} AS usuario,
                r.fecha_recibo AS fecha, ? AS naturaleza,
                CASE WHEN r.origen = ? THEN 'Pago académico' ELSE 'Pago de inventario' END AS concepto,
                {$nombreUsuario('e')} AS tercero, r.numero_recibo AS documento,
                r.valor_total AS valor, r.status, r.created_at AS creado_at
            ", [
                ReciboPago::ORIGEN_ACADEMICO, self::ORIGEN_RECIBO_ACADEMICO, self::ORIGEN_RECIBO_INVENTARIO,
                self::NATURALEZA_INGRESO, ReciboPago::ORIGEN_ACADEMICO,
            ]);
        $this->filtrar($recibos, 'r', 'fecha_recibo', $sedeIds, $filtros);

        $movimientos = DB::table('ld_movimientos as m')
            ->join('ld_tipos_movimiento as t', 't.id', '=', 'm.tipo_movimiento_id')
            ->join('sedes as s', 's.id', '=', 'm.sede_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.status', LdMovimiento::STATUS_REGISTRADO)
            ->selectRaw("
                ? AS origen, m.id AS registro_id, m.sede_id, s.nombre AS sede, m.turno_caja_id,
                m.usuario_id, {$nombreUsuario('u')} AS usuario,
                m.fecha, CASE WHEN m.clase = ? THEN ? ELSE ? END AS naturaleza,
                t.nombre AS concepto, m.tercero_nombre AS tercero, m.documento_numero AS documento,
                m.valor_total AS valor, m.status, m.created_at AS creado_at
            ", [
                self::ORIGEN_MOVIMIENTO, LdTipoMovimiento::CLASE_INGRESO,
                self::NATURALEZA_INGRESO, self::NATURALEZA_EGRESO,
            ]);
        $this->filtrar($movimientos, 'm', 'fecha', $sedeIds, $filtros);

        $consignaciones = DB::table('ld_consignaciones as c')
            ->join('bancos as b', 'b.id', '=', 'c.banco_id')
            ->join('sedes as s', 's.id', '=', 'c.sede_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.usuario_id')
            ->where('c.status', LdConsignacion::STATUS_REGISTRADO)
            ->selectRaw("
                ? AS origen, c.id AS registro_id, c.sede_id, s.nombre AS sede, c.turno_caja_id,
                c.usuario_id, {$nombreUsuario('u')} AS usuario,
                c.fecha, ? AS naturaleza, CONCAT('Consignación ', b.nombre) AS concepto,
                c.titular AS tercero, c.numero_comprobante AS documento,
                c.valor, c.status, c.created_at AS creado_at
            ", [self::ORIGEN_CONSIGNACION, self::NATURALEZA_CONSIGNACION]);
        $this->filtrar($consignaciones, 'c', 'fecha', $sedeIds, $filtros);

        return $recibos->unionAll($movimientos)->unionAll($consignaciones);
    }

    /**
     * Aplica a cada parte de la unión el alcance por sede, sede, turno y rango de fechas.
     *
     * @param  array<int>  $sedeIds
     * @param  array<string, mixed>  $filtros
     */
    private function filtrar(Builder $query, string $alias, string $campoFecha, array $sedeIds, array $filtros): void
    {
        $query->whereIn("{$alias}.sede_id", $sedeIds)
            ->when(! empty($filtros['sede_id']), fn ($q) => $q->where("{$alias}.sede_id", (int) $filtros['sede_id']))
            ->when(! empty($filtros['turno_caja_id']), fn ($q) => $q->where("{$alias}.turno_caja_id", (int) $filtros['turno_caja_id']))
            ->when(! empty($filtros['fecha_desde']), fn ($q) => $q->whereDate("{$alias}.{$campoFecha}", '>=', $filtros['fecha_desde']))
            ->when(! empty($filtros['fecha_hasta']), fn ($q) => $q->whereDate("{$alias}.{$campoFecha}", '<=', $filtros['fecha_hasta']));
    }
}
