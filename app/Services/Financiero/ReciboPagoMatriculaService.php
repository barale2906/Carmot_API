<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Support\Facades\DB;

/**
 * ReciboPagoMatriculaService
 *
 * Genera automáticamente el ReciboPago de la cuota 0 (matrícula)
 * al completar el proceso de matrícula.
 *
 * Lógica:
 * - En pago de contado: crea 1 recibo que cubre la cartera contado y la marca Cerrada.
 * - En pago a cuotas:   crea 1 recibo que cubre la cartera cuota 0 (matrícula) y la marca Cerrada.
 *   Las cuotas 1..N quedan Activas para pagarse manualmente después.
 *
 * El recibo se crea con status STATUS_CREADO y queda listo para cerrar en el cierre de caja.
 */
class ReciboPagoMatriculaService
{
    /**
     * Genera el ReciboPago de la cuota 0 para una matrícula recién creada.
     * Debe llamarse tras CarteraGeneradorService::generarParaMatricula().
     *
     * @param  Matricula $matricula  debe tener lpPrecioProducto cargado
     */
    public function generarParaMatricula(Matricula $matricula): void
    {
        $lp = $matricula->lpPrecioProducto;

        // Obtener la cartera de cuota 0 recién creada
        $cartera = Cartera::where('matricula_id', $matricula->id)
            ->where('numero_cuota', 0)
            ->first();

        if (! $cartera) {
            return;
        }

        $sedeId = $cartera->sede_id;

        DB::transaction(function () use ($matricula, $lp, $cartera, $sedeId) {
            // Crear el ReciboPago
            $recibo = ReciboPago::create([
                'origen'            => ReciboPago::ORIGEN_ACADEMICO,
                'matricula_id'      => $matricula->id,
                'cartera_id'        => $cartera->id,
                'sede_id'           => $sedeId,
                'estudiante_id'     => $matricula->estudiante_id,
                'cajero_id'         => $matricula->matriculado_por_id,
                'fecha_recibo'      => $matricula->fecha_matricula,
                'fecha_transaccion' => now(),
                'valor_total'       => $cartera->valor,
                'descuento_total'   => 0,
                'status'            => ReciboPago::STATUS_CREADO,
            ]);

            // Vincular la lista de precios al recibo
            if ($lp->lista_precio_id) {
                $recibo->listasPrecio()->attach($lp->lista_precio_id);
            }

            // Determinar el concepto según el tipo de pago
            $numeroCuotas = (int) ($lp->numero_cuotas ?? 0);
            $nombreConcepto = $numeroCuotas === 0
                ? ConceptoPago::MENSUALIDAD   // Contado: todo el valor se considera mensualidad/pago único
                : ConceptoPago::MATRICULA;    // Cuotas: cuota 0 es el cargo de matrícula

            $concepto = ConceptoPago::porNombre($nombreConcepto);

            if ($concepto) {
                $recibo->conceptosPago()->attach($concepto->id, [
                    'tipo'          => $concepto->tipo,
                    'valor'         => $cartera->valor,
                    'cantidad'      => 1,
                    'unitario'      => $cartera->valor,
                    'subtotal'      => $cartera->valor,
                    'id_relacional' => $cartera->id,
                    'observaciones' => "Auto-generado al matricular — cuota {$cartera->numero_cuota}",
                ]);
            }

            // Marcar la cartera cuota 0 como Cerrada
            $cartera->aplicarPago($cartera->valor);
        });
    }
}
