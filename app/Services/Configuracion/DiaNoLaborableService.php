<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DiaNoLaborableService
 *
 * Altas en bloque del calendario de días no laborables:
 *  - registrarRango: un día por cada fecha del rango (vacaciones, cierres).
 *  - generarFestivos: festivos nacionales de Colombia de un año.
 *
 * Las fechas que ya existen (misma sede o generales) se omiten y se reportan.
 * La generación de festivos además respeta los festivos que el usuario
 * eliminó a propósito, para que regenerar el año no los reviva.
 */
class DiaNoLaborableService
{
    public function __construct(private readonly FestivosColombiaService $festivos)
    {
    }

    /**
     * Registra un día no laborable por cada fecha entre fecha_inicio y fecha_fin.
     *
     * @param array $datos fecha_inicio, fecha_fin, nombre, tipo, sede_id?, observaciones?
     * @return array{creados: Collection<int, DiaNoLaborable>, omitidos: array<int, array{fecha: string, motivo: string}>}
     */
    public function registrarRango(array $datos): array
    {
        $fechas = collect(CarbonPeriod::create($datos['fecha_inicio'], $datos['fecha_fin']))
            ->map(fn (Carbon $fecha) => ['fecha' => $fecha->toDateString(), 'nombre' => $datos['nombre']])
            ->all();

        return $this->registrar($fechas, [
            'tipo'          => $datos['tipo'],
            'sede_id'       => $datos['sede_id'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
        ], false);
    }

    /**
     * Registra los festivos nacionales de Colombia del año.
     *
     * @param int      $anio
     * @param int|null $sedeId NULL = todas las sedes
     * @return array{creados: Collection<int, DiaNoLaborable>, omitidos: array<int, array{fecha: string, motivo: string}>}
     */
    public function generarFestivos(int $anio, ?int $sedeId): array
    {
        return $this->registrar($this->festivos->festivos($anio), [
            'tipo'          => 'festivo',
            'sede_id'       => $sedeId,
            'observaciones' => 'Generado automáticamente (festivos de Colombia ' . $anio . ').',
        ], true);
    }

    /**
     * Crea los días que no estén duplicados.
     *
     * @param array<int, array{fecha: string, nombre: string}> $fechas
     * @param array $atributos  tipo, sede_id, observaciones
     * @param bool  $respetarEliminados  Omitir fechas eliminadas lógicamente para la misma sede
     * @return array{creados: Collection<int, DiaNoLaborable>, omitidos: array<int, array{fecha: string, motivo: string}>}
     */
    private function registrar(array $fechas, array $atributos, bool $respetarEliminados): array
    {
        $creados  = collect();
        $omitidos = [];

        DB::transaction(function () use ($fechas, $atributos, $respetarEliminados, $creados, &$omitidos) {
            foreach ($fechas as $item) {
                $motivo = DiaNoLaborable::motivoDuplicado($item['fecha'], $atributos['sede_id']);

                if (! $motivo && $respetarEliminados && $this->fueEliminado($item['fecha'], $atributos['sede_id'])) {
                    $motivo = "La fecha {$item['fecha']} fue eliminada previamente; restáurela si debe aplicar.";
                }

                if ($motivo) {
                    $omitidos[] = ['fecha' => $item['fecha'], 'motivo' => $motivo];
                    continue;
                }

                $creados->push(DiaNoLaborable::create($atributos + [
                    'fecha'  => $item['fecha'],
                    'nombre' => $item['nombre'],
                    'status' => 1,
                ]));
            }
        });

        return ['creados' => $creados, 'omitidos' => $omitidos];
    }

    /**
     * Indica si la fecha existe eliminada lógicamente para la misma sede.
     */
    private function fueEliminado(string $fecha, ?int $sedeId): bool
    {
        return DiaNoLaborable::onlyTrashed()
            ->whereDate('fecha', $fecha)
            ->when($sedeId === null, fn ($q) => $q->whereNull('sede_id'), fn ($q) => $q->where('sede_id', $sedeId))
            ->exists();
    }
}
