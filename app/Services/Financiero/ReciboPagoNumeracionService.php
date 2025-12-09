<?php

namespace App\Services\Financiero;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Support\Facades\DB;

/**
 * Servicio ReciboPagoNumeracionService
 *
 * Gestiona la numeración consecutiva de los recibos de pago.
 * Asegura que cada sede tenga su propio consecutivo por origen (académico/inventario).
 *
 * @package App\Services\Financiero
 */
class ReciboPagoNumeracionService
{
    /**
     * Obtiene el siguiente consecutivo para una sede y origen específicos.
     * Utiliza transacciones y locks para evitar duplicados en entornos concurrentes.
     * 
     * IMPORTANTE: Este método debe llamarse dentro de una transacción activa para que
     * el lock se mantenga hasta que se complete la inserción del registro.
     *
     * @param int $sedeId ID de la sede
     * @param int $origen Origen del recibo (0=Inventarios, 1=Académico)
     * @return int Siguiente consecutivo
     */
    public function obtenerConsecutivo(int $sedeId, int $origen): int
    {
        // Si ya estamos en una transacción, usar esa. Si no, crear una nueva.
        if (DB::transactionLevel() > 0) {
            // Ya estamos en una transacción, usar lockForUpdate directamente
            $ultimoRecibo = ReciboPago::where('sede_id', $sedeId)
                ->where('origen', $origen)
                ->lockForUpdate()
                ->orderBy('consecutivo', 'desc')
                ->first();

            return ($ultimoRecibo ? $ultimoRecibo->consecutivo : 0) + 1;
        } else {
            // No estamos en una transacción, crear una nueva
            return DB::transaction(function () use ($sedeId, $origen) {
                $ultimoRecibo = ReciboPago::where('sede_id', $sedeId)
                    ->where('origen', $origen)
                    ->lockForUpdate()
                    ->orderBy('consecutivo', 'desc')
                    ->first();

                return ($ultimoRecibo ? $ultimoRecibo->consecutivo : 0) + 1;
            });
        }
    }

    /**
     * Genera el número completo del recibo (prefijo + consecutivo formateado).
     *
     * @param int $sedeId ID de la sede
     * @param int $origen Origen del recibo (0=Inventarios, 1=Académico)
     * @return string Número completo del recibo (ej: ACAD-0001)
     * @throws \Exception Si la sede no tiene código configurado
     */
    public function generarNumeroRecibo(int $sedeId, int $origen): string
    {
        $sede = Sede::findOrFail($sedeId);

        $prefijo = $origen === ReciboPago::ORIGEN_ACADEMICO
            ? $sede->codigo_academico
            : $sede->codigo_inventario;

        if (!$prefijo) {
            throw new \Exception(
                "La sede '{$sede->nombre}' no tiene código configurado para " .
                ($origen === ReciboPago::ORIGEN_ACADEMICO ? 'recibos académicos' : 'recibos de inventario') .
                ". Por favor configure el código en la sede."
            );
        }

        $consecutivo = $this->obtenerConsecutivo($sedeId, $origen);
        $consecutivoFormateado = str_pad($consecutivo, 4, '0', STR_PAD_LEFT);

        return "{$prefijo}-{$consecutivoFormateado}";
    }

    /**
     * Valida que el código de la sede esté configurado para el origen especificado.
     *
     * @param int $sedeId ID de la sede
     * @param int $origen Origen del recibo (0=Inventarios, 1=Académico)
     * @return bool True si el código está configurado
     */
    public function validarCodigoSede(int $sedeId, int $origen): bool
    {
        $sede = Sede::findOrFail($sedeId);

        if ($origen === ReciboPago::ORIGEN_ACADEMICO) {
            return !empty($sede->codigo_academico);
        }

        return !empty($sede->codigo_inventario);
    }

    /**
     * Obtiene el prefijo de la sede según el origen.
     *
     * @param int $sedeId ID de la sede
     * @param int $origen Origen del recibo (0=Inventarios, 1=Académico)
     * @return string|null Prefijo de la sede o null si no está configurado
     */
    public function obtenerPrefijo(int $sedeId, int $origen): ?string
    {
        $sede = Sede::findOrFail($sedeId);

        return $origen === ReciboPago::ORIGEN_ACADEMICO
            ? $sede->codigo_academico
            : $sede->codigo_inventario;
    }
}

