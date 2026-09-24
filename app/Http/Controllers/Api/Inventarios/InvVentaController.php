<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\AbonarInvPedidoRequest;
use App\Http\Requests\Api\Inventarios\StoreInvVentaRequest;
use App\Http\Resources\Api\Inventarios\InvPedidoResource;
use App\Http\Resources\Api\Financiero\ReciboPago\ReciboPagoResource;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Inventarios\InvPedido;
use App\Notifications\Financiero\TransferenciaAprobadaNotification;
use App\Notifications\Financiero\TransferenciaPendienteNotification;
use App\Notifications\Financiero\TransferenciaRechazadaNotification;
use App\Services\Financiero\AjusteService;
use App\Services\Financiero\ReciboPagoNumeracionService;
use App\Services\Inventarios\InvVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador para la creación de ventas de inventario y abonos a pedidos.
 *
 * Crea el pedido con el primer abono (o pago total) y permite registrar
 * abonos posteriores hasta saldar. Cuando el saldo llega a 0, dispara el
 * despacho automáticamente.
 *
 * Aplica la misma metodología de medios de pago y sobrecargos que el módulo
 * académico, diferenciando siempre el origen=INVENTARIOS para numeración y datos.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvVentaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct(
        private readonly AjusteService $ajusteService,
        private readonly ReciboPagoNumeracionService $numeracionService,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_ventasCrear')->only(['store', 'aprobarTransferencia', 'rechazarTransferencia', 'reenviarTransferencia', 'precalcularSobrecargos']);
        $this->middleware('permission:inv_ventasAbonar')->only(['abonar']);
    }

    /**
     * Crea un nuevo pedido de inventario con el primer abono o pago total.
     *
     * Resuelve el precio de cada producto desde la lista vigente para la sede.
     * Si el abono cubre el total, dispara el despacho automáticamente.
     * Aplica sobrecargos por medio de pago si se informan.
     * Si el medio de pago es transferencia, el recibo queda en PENDIENTE_APROBACION.
     *
     * @param StoreInvVentaRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvVentaRequest $request): JsonResponse
    {
        try {
            $data = $request->only([
                'estudiante_id', 'sede_id', 'almacen_id', 'items',
                'monto_abono', 'medios_pago', 'sobrecargos', 'observaciones', 'variantes_kit',
            ]);
            $data['cajero_id'] = $request->user()->id;

            $resultado = InvVentaService::crearPedido($data);

            $recibo            = $resultado['recibo'];
            $mediosPagoCreados = $resultado['mediosPagoCreados'];
            $esTransferencia   = $resultado['esTransferencia'];

            // Aplicar sobrecargos (misma lógica que flujo académico)
            $sobrecargoTotal = 0.0;
            foreach ($data['sobrecargos'] ?? [] as $sc) {
                $sobrecargo = Descuento::findOrFail($sc['descuento_id']);
                $medioPago  = $mediosPagoCreados[$sc['medio_pago_index']];
                $registro   = $this->ajusteService->aplicarSobrecargo($sobrecargo, $recibo, $medioPago);
                $sobrecargoTotal += (float) $registro->valor_sobrecargo;
            }

            if ($sobrecargoTotal > 0) {
                $recibo->increment('sobrecargo_total', $sobrecargoTotal);
                $recibo->increment('valor_total', $sobrecargoTotal);
            }

            // Guardar comprobante de transferencia fuera de la transacción con move()
            $comprobanteInfo = null;
            if ($esTransferencia && $request->hasFile('comprobante')) {
                $file      = $request->file('comprobante');
                $extension = $file->getClientOriginalExtension() ?: $file->extension();
                $nombre    = $recibo->id . '_' . now()->format('Ymd_Hi') . ($extension ? ".{$extension}" : '');
                $destDir   = storage_path('app/public/recibos_transferencia');
                if (! is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                try {
                    $file->move($destDir, $nombre);
                    $comprobanteInfo = ['id' => $mediosPagoCreados[0]->id, 'path' => 'recibos_transferencia/' . $nombre];
                } catch (\Exception $e) {
                    Log::warning('No se pudo guardar el comprobante de transferencia (inventario)', [
                        'recibo_id' => $recibo->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            if ($comprobanteInfo) {
                DB::table('recibo_pago_medio_pago')
                    ->where('id', $comprobanteInfo['id'])
                    ->update(['comprobante_path' => $comprobanteInfo['path'], 'updated_at' => now()]);
            }

            $mensaje = $esTransferencia
                ? 'Pedido creado. Recibo por transferencia pendiente de aprobación.'
                : 'Pedido creado exitosamente.';

            return response()->json([
                'message' => $mensaje,
                'data'    => new InvPedidoResource($resultado['pedido']),
                'recibo'  => [
                    'id'            => $recibo->id,
                    'numero_recibo' => $recibo->numero_recibo,
                    'valor_total'   => $recibo->valor_total,
                    'status'        => $recibo->status,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Registra un abono a un pedido activo.
     *
     * Si el abono cubre el saldo restante, dispara el despacho automáticamente.
     * Aplica sobrecargos por medio de pago si se informan.
     *
     * @param AbonarInvPedidoRequest $request
     * @param InvPedido              $pedido
     * @return JsonResponse
     */
    public function abonar(AbonarInvPedidoRequest $request, InvPedido $pedido): JsonResponse
    {
        try {
            $data = array_merge(
                $request->only(['monto_abono', 'medios_pago', 'sobrecargos', 'variantes_kit']),
                ['cajero_id' => $request->user()->id]
            );

            $resultado = InvVentaService::abonarPedido($pedido, $data);

            $recibo            = $resultado['recibo'];
            $mediosPagoCreados = $resultado['mediosPagoCreados'];
            $esTransferencia   = $resultado['esTransferencia'];

            // Aplicar sobrecargos
            $sobrecargoTotal = 0.0;
            foreach ($data['sobrecargos'] ?? [] as $sc) {
                $sobrecargo = Descuento::findOrFail($sc['descuento_id']);
                $medioPago  = $mediosPagoCreados[$sc['medio_pago_index']];
                $registro   = $this->ajusteService->aplicarSobrecargo($sobrecargo, $recibo, $medioPago);
                $sobrecargoTotal += (float) $registro->valor_sobrecargo;
            }

            if ($sobrecargoTotal > 0) {
                $recibo->increment('sobrecargo_total', $sobrecargoTotal);
                $recibo->increment('valor_total', $sobrecargoTotal);
            }

            // Comprobante de transferencia
            $comprobanteInfo = null;
            if ($esTransferencia && $request->hasFile('comprobante')) {
                $file      = $request->file('comprobante');
                $extension = $file->getClientOriginalExtension() ?: $file->extension();
                $nombre    = $recibo->id . '_' . now()->format('Ymd_Hi') . ($extension ? ".{$extension}" : '');
                $destDir   = storage_path('app/public/recibos_transferencia');
                if (! is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                try {
                    $file->move($destDir, $nombre);
                    $comprobanteInfo = ['id' => $mediosPagoCreados[0]->id, 'path' => 'recibos_transferencia/' . $nombre];
                } catch (\Exception $e) {
                    Log::warning('No se pudo guardar el comprobante de transferencia (inventario-abonar)', [
                        'recibo_id' => $recibo->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            if ($comprobanteInfo) {
                DB::table('recibo_pago_medio_pago')
                    ->where('id', $comprobanteInfo['id'])
                    ->update(['comprobante_path' => $comprobanteInfo['path'], 'updated_at' => now()]);
            }

            $mensaje = $esTransferencia
                ? 'Abono registrado. Recibo por transferencia pendiente de aprobación.'
                : 'Abono registrado exitosamente.';

            return response()->json([
                'message' => $mensaje,
                'data'    => new InvPedidoResource($resultado['pedido']),
                'recibo'  => [
                    'id'            => $recibo->id,
                    'numero_recibo' => $recibo->numero_recibo,
                    'valor_total'   => $recibo->valor_total,
                    'status'        => $recibo->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Pre-calcula los sobrecargos aplicables a una lista de medios de pago sin persistir nada.
     * El cajero lo llama al seleccionar el medio de pago para ver el recargo antes de confirmar.
     *
     * @param Request $request medios_pago: [{medio_pago, tipo_tarjeta, valor}]
     * @return JsonResponse Sobrecargos calculados y total
     */
    public function precalcularSobrecargos(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'medios_pago'                  => 'required|array|min:1',
                'medios_pago.*.medio_pago'     => ['required', 'string'],
                'medios_pago.*.tipo_tarjeta'   => 'nullable|string|max:60',
                'medios_pago.*.valor'          => 'required|numeric|min:0',
            ]);

            $resultado = $this->ajusteService->precalcular($request->input('medios_pago'));

            return response()->json(['data' => $resultado]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Error de validación.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al pre-calcular sobrecargos.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * El cajero notifica al validador que el recibo de transferencia de inventario está listo.
     * Solo aplica a recibos en estado PENDIENTE_APROBACION con origen=INVENTARIOS.
     *
     * @param ReciboPago $reciboPago
     * @return JsonResponse
     */
    public function notificarTransferencia(ReciboPago $reciboPago): JsonResponse
    {
        if ($reciboPago->origen !== ReciboPago::ORIGEN_INVENTARIOS) {
            return response()->json(['message' => 'Este recibo no pertenece al módulo de inventarios.'], 422);
        }

        if (! $reciboPago->estaPendienteAprobacion()) {
            return response()->json(['message' => 'Solo se pueden notificar recibos pendientes de aprobación.'], 422);
        }

        $reciboPago->load(['cajero', 'sede', 'estudiante']);

        $aprobadores = \App\Models\User::permission('fin_reciboPagoAprobar')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superusuario'))
            ->get();

        if ($aprobadores->isEmpty()) {
            return response()->json(['message' => 'No hay usuarios con permiso de aprobación registrados.'], 422);
        }

        foreach ($aprobadores as $aprobador) {
            $aprobador->notify(new TransferenciaPendienteNotification($reciboPago));
        }

        return response()->json([
            'message'     => "Notificación enviada a {$aprobadores->count()} validador(es).",
            'aprobadores' => $aprobadores->count(),
        ]);
    }

    /**
     * El validador aprueba un recibo de transferencia de inventario.
     * Asigna el número de recibo con prefijo de inventario y cierra el recibo.
     * No ejecuta distribución de cartera (no aplica para inventarios).
     *
     * @param ReciboPago $reciboPago
     * @return JsonResponse
     */
    public function aprobarTransferencia(ReciboPago $reciboPago): JsonResponse
    {
        if ($reciboPago->origen !== ReciboPago::ORIGEN_INVENTARIOS) {
            return response()->json(['message' => 'Este recibo no pertenece al módulo de inventarios.'], 422);
        }

        if (! $reciboPago->estaPendienteAprobacion()) {
            return response()->json(['message' => 'Solo se pueden aprobar recibos pendientes de aprobación.'], 422);
        }

        DB::beginTransaction();
        try {
            $numeroRecibo = $this->numeracionService->generarNumeroRecibo(
                $reciboPago->sede_id,
                ReciboPago::ORIGEN_INVENTARIOS
            );
            preg_match('/-(\d+)$/', $numeroRecibo, $matches);
            $consecutivo = isset($matches[1]) ? (int) $matches[1] : 1;
            $prefijo     = $this->numeracionService->obtenerPrefijo($reciboPago->sede_id, ReciboPago::ORIGEN_INVENTARIOS);

            $reciboPago->aprobar(auth()->id(), $numeroRecibo, $consecutivo, $prefijo);

            DB::commit();

            $reciboPago->load(['sede', 'cajero', 'estudiante', 'mediosPago.banco']);

            // Notificar al cajero
            $reciboPago->cajero?->notify(new TransferenciaAprobadaNotification($reciboPago));

            Log::info('Recibo de transferencia de inventario aprobado', [
                'recibo_id'     => $reciboPago->id,
                'numero_recibo' => $reciboPago->numero_recibo,
                'aprobado_por'  => auth()->id(),
            ]);

            return response()->json([
                'message' => "Recibo {$reciboPago->numero_recibo} aprobado exitosamente.",
                'data'    => new ReciboPagoResource($reciboPago),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar recibo de transferencia (inventario): ' . $e->getMessage());
            return response()->json(['message' => 'Error al aprobar el recibo.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * El validador rechaza un recibo de transferencia de inventario e informa el motivo al cajero.
     *
     * @param Request    $request motivo_rechazo (requerido)
     * @param ReciboPago $reciboPago
     * @return JsonResponse
     */
    public function rechazarTransferencia(Request $request, ReciboPago $reciboPago): JsonResponse
    {
        $request->validate(['motivo_rechazo' => 'required|string|max:500'], [
            'motivo_rechazo.required' => 'El motivo del rechazo es obligatorio.',
        ]);

        if ($reciboPago->origen !== ReciboPago::ORIGEN_INVENTARIOS) {
            return response()->json(['message' => 'Este recibo no pertenece al módulo de inventarios.'], 422);
        }

        if (! $reciboPago->estaPendienteAprobacion()) {
            return response()->json(['message' => 'Solo se pueden rechazar recibos pendientes de aprobación.'], 422);
        }

        $reciboPago->rechazar(auth()->id(), $request->string('motivo_rechazo'));
        $reciboPago->load(['cajero', 'estudiante']);

        $reciboPago->cajero?->notify(
            new TransferenciaRechazadaNotification($reciboPago, $request->string('motivo_rechazo'))
        );

        Log::info('Recibo de transferencia de inventario rechazado', [
            'recibo_id'      => $reciboPago->id,
            'rechazado_por'  => auth()->id(),
            'motivo_rechazo' => $request->string('motivo_rechazo'),
        ]);

        return response()->json([
            'message' => 'Recibo rechazado. Se notificó al cajero con el motivo.',
            'data'    => new ReciboPagoResource($reciboPago->fresh()),
        ]);
    }

    /**
     * El cajero corrige un recibo de inventario rechazado y lo reenvía a aprobación.
     *
     * @param Request    $request banco_id, numero_transaccion, comprobante (file)
     * @param ReciboPago $reciboPago
     * @return JsonResponse
     */
    public function reenviarTransferencia(Request $request, ReciboPago $reciboPago): JsonResponse
    {
        $request->validate([
            'banco_id'           => 'nullable|exists:bancos,id',
            'numero_transaccion' => 'nullable|string|max:100',
            'comprobante'        => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        if ($reciboPago->origen !== ReciboPago::ORIGEN_INVENTARIOS) {
            return response()->json(['message' => 'Este recibo no pertenece al módulo de inventarios.'], 422);
        }

        if (! $reciboPago->estaRechazado()) {
            return response()->json(['message' => 'Solo se pueden reenviar recibos en estado Rechazado.'], 422);
        }

        $medioPago = $reciboPago->mediosPago()->where('medio_pago', 'transferencia')->first();

        if (! $medioPago) {
            return response()->json(['message' => 'No se encontró el medio de pago por transferencia.'], 422);
        }

        $updateData  = [];
        $bancoNombre = null;

        if ($request->filled('banco_id')) {
            $bancoNombre = \App\Models\Configuracion\Banco::find($request->integer('banco_id'))?->nombre;
            $updateData['banco_id'] = $request->integer('banco_id');
            $updateData['banco']    = $bancoNombre;
        }
        if ($request->filled('numero_transaccion')) {
            $updateData['numero_transaccion'] = $request->string('numero_transaccion');
        }

        $comprobanteReenvioPath = null;
        if ($request->hasFile('comprobante')) {
            if ($medioPago->comprobante_path && $medioPago->comprobante_path !== '0') {
                Storage::disk('public')->delete($medioPago->comprobante_path);
            }
            $file      = $request->file('comprobante');
            $extension = $file->getClientOriginalExtension() ?: $file->extension();
            $nombre    = $reciboPago->id . '_' . now()->format('Ymd_Hi') . ($extension ? ".{$extension}" : '');
            $destDir   = storage_path('app/public/recibos_transferencia');
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            try {
                $file->move($destDir, $nombre);
                $comprobanteReenvioPath = 'recibos_transferencia/' . $nombre;
            } catch (\Exception $e) {
                Log::warning('No se pudo guardar el comprobante en reenvío (inventario)', [
                    'recibo_id' => $reciboPago->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (! empty($updateData) || $comprobanteReenvioPath !== null) {
            if ($comprobanteReenvioPath !== null) {
                $updateData['comprobante_path'] = $comprobanteReenvioPath;
            }
            DB::table('recibo_pago_medio_pago')
                ->where('id', $medioPago->id)
                ->update($updateData + ['updated_at' => now()]);
        }

        $reciboUpdate = ['status' => ReciboPago::STATUS_PENDIENTE_APROBACION, 'motivo_rechazo' => null, 'aprobado_por_id' => null];
        if ($bancoNombre !== null) {
            $reciboUpdate['banco'] = $bancoNombre;
        }
        $reciboPago->update($reciboUpdate);
        $reciboPago->load(['cajero', 'sede', 'estudiante']);

        $aprobadores = \App\Models\User::permission('fin_reciboPagoAprobar')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superusuario'))
            ->get();
        foreach ($aprobadores as $aprobador) {
            $aprobador->notify(new TransferenciaPendienteNotification($reciboPago));
        }

        return response()->json([
            'message' => 'Recibo corregido y reenviado a aprobación.',
            'data'    => new ReciboPagoResource($reciboPago->fresh(['mediosPago.banco'])),
        ]);
    }
}
