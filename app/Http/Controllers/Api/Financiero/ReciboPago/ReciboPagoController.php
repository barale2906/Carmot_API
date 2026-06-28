<?php

namespace App\Http\Controllers\Api\Financiero\ReciboPago;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\ReciboPago\StoreReciboPagoRequest;
use App\Http\Requests\Api\Financiero\ReciboPago\UpdateReciboPagoRequest;
use App\Http\Resources\Api\Financiero\ReciboPago\ReciboPagoResource;
use App\Mail\ReciboPagoMail;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Services\Financiero\ReciboPagoPDFService;
use App\Services\Financiero\ReciboPagoDistribucionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Controlador ReciboPagoController
 *
 * Gestiona las operaciones CRUD para los recibos de pago del sistema financiero.
 * Permite crear, listar, mostrar, actualizar, eliminar, anular y cerrar recibos de pago.
 * También permite generar PDF, enviar por correo y generar reportes.
 *
 * @package App\Http\Controllers\Api\Financiero\ReciboPago
 */
class ReciboPagoController extends Controller
{
    public function __construct(
        private readonly ReciboPagoDistribucionService $distribucionService,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_recibos_pago')->only(['index', 'show']);
        $this->middleware('permission:fin_reciboPagoCrear')->only(['store']);
        $this->middleware('permission:fin_reciboPagoEditar')->only(['update']);
        $this->middleware('permission:fin_reciboPagoAnular')->only(['anular']);
        $this->middleware('permission:fin_reciboPagoCerrar')->only(['cerrar']);
        $this->middleware('permission:fin_reciboPagoPDF')->only(['generarPDF']);
        $this->middleware('permission:fin_reciboPagoReportes')->only(['reportes']);
    }

    /**
     * Muestra una lista de recibos de pago.
     * Permite filtrar, ordenar y cargar relaciones.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de recibos de pago
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Construir query
            $query = ReciboPago::query();

            // Aplicar filtros
            if ($request->filled('sede_id')) {
                $query->bySede($request->integer('sede_id'));
            }

            if ($request->filled('estudiante_id')) {
                $query->byEstudiante($request->integer('estudiante_id'));
            }

            if ($request->filled('cajero_id')) {
                $query->byCajero($request->integer('cajero_id'));
            }

            if ($request->filled('matricula_id')) {
                $query->byMatricula($request->integer('matricula_id'));
            }

            if ($request->filled('origen')) {
                $query->byOrigen($request->integer('origen'));
            }

            if ($request->filled('status')) {
                $query->byStatus($request->integer('status'));
            }

            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $query->byFechaRange($request->date('fecha_inicio'), $request->date('fecha_fin'));
            }

            if ($request->filled('cierre')) {
                $query->byCierre($request->integer('cierre'));
            }

            if ($request->filled('producto_id')) {
                $query->byProducto($request->integer('producto_id'));
            }

            if ($request->filled('poblacion_id')) {
                $query->byPoblacion($request->integer('poblacion_id'));
            }

            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('numero_recibo', 'like', "%{$search}%")
                      ->orWhere('prefijo', 'like', "%{$search}%");
                });
            }

            // Solo recibos vigentes por defecto (no anulados)
            if ($request->boolean('vigentes', true)) {
                $query->vigentes();
            }

            if ($request->boolean('include_trashed', false)) {
                $query->withTrashed();
            }

            if ($request->boolean('only_trashed', false)) {
                $query->onlyTrashed();
            }

            // Cargar relaciones (usa scopes del trait para acceder a métodos protegidos)
            $relations = $request->filled('with')
                ? explode(',', $request->string('with'))
                : [];
            $query->withRelations($relations);

            // Aplicar ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->withSorting($sortBy, $sortDirection);

            // Paginar
            $recibosPago = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => ReciboPagoResource::collection($recibosPago),
                'meta' => [
                    'current_page' => $recibosPago->currentPage(),
                    'last_page' => $recibosPago->lastPage(),
                    'per_page' => $recibosPago->perPage(),
                    'total' => $recibosPago->total(),
                    'from' => $recibosPago->firstItem(),
                    'to' => $recibosPago->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener recibos de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los recibos de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo recibo de pago usando el modo unificado.
     *
     * El operador ingresa el monto total que paga el estudiante (monto_a_pagar).
     * El servidor distribuye ese monto en dos pasos:
     *  1. Primero cubre los conceptos adicionales (certificados, copias, etc.) al valor
     *     almacenado en conceptos_pago.valor × cantidad.
     *  2. El saldo restante se distribuye entre las cuotas de cartera pendientes de la
     *     matrícula, de la más antigua a la más reciente.
     *
     * @param StoreReciboPagoRequest $request Datos validados del recibo de pago
     * @return JsonResponse Respuesta JSON con el recibo de pago creado
     */
    public function store(StoreReciboPagoRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data      = $request->validated();
            $fecha     = Carbon::parse($data['fecha_transaccion']);
            $matricula = Matricula::findOrFail($data['matricula_id']);

            // ── 1. Conceptos adicionales (certificados, copias, etc.) ──────────
            $lineasAdicionales = [];
            $totalAdicionales  = 0.0;

            foreach ($data['conceptos_adicionales'] ?? [] as $item) {
                $concepto      = ConceptoPago::findOrFail($item['concepto_pago_id']);
                $cantidad      = (int) $item['cantidad'];
                $valorUnitario = (float) $concepto->valor;
                $subtotal      = $valorUnitario * $cantidad;

                $lineasAdicionales[] = compact('concepto', 'cantidad', 'valorUnitario', 'subtotal');
                $totalAdicionales   += $subtotal;
            }

            $montoAPagar  = (float) $data['monto_a_pagar'];
            $montoCartera = $montoAPagar - $totalAdicionales;

            if ($montoCartera < -0.01) {
                DB::rollBack();
                return response()->json([
                    'message' => "El monto a pagar ({$montoAPagar}) es insuficiente para cubrir los conceptos adicionales ({$totalAdicionales}).",
                ], 422);
            }

            // ── 2. Distribución automática entre cuotas de cartera ────────────
            $planCartera    = [];
            $descuentoTotal = 0.0;

            if ($montoCartera > 0.01) {
                $planCartera    = $this->distribucionService->distribuir(
                    $matricula,
                    $montoCartera,
                    (bool) ($data['aplicar_descuento'] ?? false),
                    $fecha
                );
                $descuentoTotal = collect($planCartera)->sum('descuento');
            }

            // ── 3. Crear el encabezado del ReciboPago ─────────────────────────
            $recibo = ReciboPago::create([
                'origen'            => $data['origen'],
                'matricula_id'      => $matricula->id,
                'sede_id'           => $data['sede_id'],
                'estudiante_id'     => $matricula->estudiante_id,
                'cajero_id'         => $data['cajero_id'],
                'fecha_recibo'      => $data['fecha_recibo'],
                'fecha_transaccion' => $data['fecha_transaccion'],
                'valor_total'       => $montoAPagar,
                'descuento_total'   => $descuentoTotal,
                'banco'             => $data['banco'] ?? null,
                'status'            => ReciboPago::STATUS_CREADO,
            ]);

            // Vincular lista de precios de referencia si se informa
            if (! empty($data['lista_precio_id'])) {
                $recibo->listasPrecio()->attach($data['lista_precio_id']);
            }

            // ── 4. Líneas de conceptos adicionales ───────────────────────────
            foreach ($lineasAdicionales as $linea) {
                $recibo->conceptosPago()->attach($linea['concepto']->id, [
                    'tipo'          => $linea['concepto']->tipo,
                    'valor'         => $linea['subtotal'],
                    'cantidad'      => $linea['cantidad'],
                    'unitario'      => $linea['valorUnitario'],
                    'subtotal'      => $linea['subtotal'],
                    'id_relacional' => null,
                    'observaciones' => null,
                ]);
            }

            // ── 5. Líneas de cartera y actualización de saldos ───────────────
            foreach ($planCartera as $linea) {
                /** @var Cartera $cartera */
                $cartera  = $linea['cartera'];
                $concepto = ConceptoPago::porNombre(
                    $cartera->numero_cuota === 0 ? ConceptoPago::MATRICULA : ConceptoPago::MENSUALIDAD
                );

                if ($concepto) {
                    $recibo->conceptosPago()->attach($concepto->id, [
                        'tipo'          => $concepto->tipo,
                        'valor'         => $linea['valor'],
                        'cantidad'      => 1,
                        'unitario'      => $linea['valor'],
                        'subtotal'      => $linea['valor'],
                        'id_relacional' => $cartera->id,
                        'observaciones' => "Pago cuota {$cartera->numero_cuota}",
                    ]);
                }

                // Línea de descuento por pronto pago (valor negativo implícito en el total)
                if (($linea['descuento'] ?? 0) > 0) {
                    $conceptoDesc = ConceptoPago::porNombre(ConceptoPago::DESCUENTO);
                    if ($conceptoDesc) {
                        $recibo->conceptosPago()->attach($conceptoDesc->id, [
                            'tipo'          => $conceptoDesc->tipo,
                            'valor'         => $linea['descuento'],
                            'cantidad'      => 1,
                            'unitario'      => $linea['descuento'],
                            'subtotal'      => $linea['descuento'],
                            'id_relacional' => $cartera->id,
                            'observaciones' => 'Descuento pronto pago',
                        ]);
                    }
                    $cartera->increment('descuento', $linea['descuento']);
                }

                $cartera->aplicarPago($linea['valor']);
            }

            // ── 6. Medios de pago ─────────────────────────────────────────────
            foreach ($data['medios_pago'] as $medio) {
                ReciboPagoMedioPago::create([
                    'recibo_pago_id' => $recibo->id,
                    'medio_pago'     => $medio['medio_pago'],
                    'valor'          => $medio['valor'],
                    'referencia'     => $medio['referencia'] ?? null,
                    'banco'          => $medio['banco'] ?? null,
                ]);
            }

            DB::commit();

            $recibo->load(['sede', 'cajero', 'matricula', 'conceptosPago', 'listasPrecio', 'mediosPago']);

            return response()->json([
                'message' => 'Recibo de pago creado exitosamente.',
                'data'    => new ReciboPagoResource($recibo),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear recibo de pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error al crear el recibo de pago.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el recibo de pago especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param ReciboPago $reciboPago Recibo de pago a mostrar
     * @return JsonResponse Respuesta JSON con los datos del recibo de pago
     */
    public function show(Request $request, ReciboPago $reciboPago): JsonResponse
    {
        try {
            // Cargar relaciones
            $relations = $request->filled('with')
                ? explode(',', $request->string('with'))
                : ['sede', 'cajero'];
            $reciboPago->load($relations);

            return response()->json([
                'data' => new ReciboPagoResource($reciboPago),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener recibo de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el recibo de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el recibo de pago especificado en la base de datos.
     * Solo permite actualizar recibos en proceso.
     *
     * @param UpdateReciboPagoRequest $request Datos validados para actualizar
     * @param ReciboPago $reciboPago Recibo de pago a actualizar
     * @return JsonResponse Respuesta JSON con el recibo de pago actualizado
     */
    public function update(UpdateReciboPagoRequest $request, ReciboPago $reciboPago): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Validar que el recibo esté en proceso
            if (!$reciboPago->estaEnProceso()) {
                return response()->json([
                    'message' => 'Solo se pueden editar recibos en proceso.',
                ], 422);
            }

            $data = $request->validated();

            // Actualizar datos básicos del recibo
            $reciboPago->update($request->only([
                'sede_id',
                'estudiante_id',
                'cajero_id',
                'matricula_id',
                'origen',
                'fecha_recibo',
                'fecha_transaccion',
                'valor_total',
                'descuento_total',
                'banco',
            ]));

            // Actualizar conceptos de pago
            if (isset($data['conceptos_pago'])) {
                $reciboPago->conceptosPago()->detach();
                foreach ($data['conceptos_pago'] as $concepto) {
                    $reciboPago->conceptosPago()->attach($concepto['concepto_pago_id'], [
                        'valor' => $concepto['valor'],
                        'tipo' => $concepto['tipo'],
                        'producto' => $concepto['producto'] ?? null,
                        'cantidad' => $concepto['cantidad'],
                        'unitario' => $concepto['unitario'],
                        'subtotal' => $concepto['subtotal'],
                        'id_relacional' => $concepto['id_relacional'] ?? null,
                        'observaciones' => $concepto['observaciones'] ?? null,
                    ]);
                }
            }

            // Actualizar listas de precio
            if (isset($data['listas_precio'])) {
                $reciboPago->listasPrecio()->sync($data['listas_precio']);
            }

            // Actualizar productos
            if (isset($data['productos'])) {
                $reciboPago->productos()->detach();
                foreach ($data['productos'] as $producto) {
                    $reciboPago->productos()->attach($producto['producto_id'], [
                        'cantidad' => $producto['cantidad'],
                        'precio_unitario' => $producto['precio_unitario'],
                        'subtotal' => $producto['subtotal'],
                    ]);
                }
            }

            // Actualizar descuentos
            if (isset($data['descuentos'])) {
                $reciboPago->descuentos()->detach();
                foreach ($data['descuentos'] as $descuento) {
                    $reciboPago->descuentos()->attach($descuento['descuento_id'], [
                        'valor_descuento' => $descuento['valor_descuento'],
                        'valor_original' => $descuento['valor_original'],
                        'valor_final' => $descuento['valor_final'],
                    ]);
                }
            }

            // Actualizar medios de pago
            if (isset($data['medios_pago'])) {
                $reciboPago->mediosPago()->delete();
                foreach ($data['medios_pago'] as $medio) {
                    ReciboPagoMedioPago::create([
                        'recibo_pago_id' => $reciboPago->id,
                        'medio_pago' => $medio['medio_pago'],
                        'valor' => $medio['valor'],
                        'referencia' => $medio['referencia'] ?? null,
                        'banco' => $medio['banco'] ?? null,
                    ]);
                }
            }

            // Recalcular totales
            $totales = $reciboPago->calcularTotales();
            $reciboPago->update($totales);

            DB::commit();

            // Cargar relaciones para la respuesta
            $reciboPago->load(['sede', 'estudiante', 'cajero', 'matricula', 'conceptosPago', 'listasPrecio', 'productos', 'descuentos', 'mediosPago']);

            return response()->json([
                'message' => 'Recibo de pago actualizado exitosamente.',
                'data' => new ReciboPagoResource($reciboPago->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar recibo de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar el recibo de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) el recibo de pago especificado.
     * Solo permite eliminar recibos en proceso.
     *
     * @param ReciboPago $reciboPago Recibo de pago a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(ReciboPago $reciboPago): JsonResponse
    {
        try {
            // Validar que el recibo esté en proceso
            if (!$reciboPago->estaEnProceso()) {
                return response()->json([
                    'message' => 'Solo se pueden eliminar recibos en proceso.',
                ], 422);
            }

            $reciboPago->delete();

            return response()->json([
                'message' => 'Recibo de pago eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar recibo de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar el recibo de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Anula el recibo de pago especificado.
     * Si el recibo tenía líneas de cartera (id_relacional), revierte el saldo y estado
     * de cada cartera afectada para dejarla como si el pago nunca se hubiera realizado.
     *
     * @param Request    $request
     * @param ReciboPago $reciboPago Recibo de pago a anular
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function anular(Request $request, ReciboPago $reciboPago): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($reciboPago->estaCerrado()) {
                return response()->json(['message' => 'No se puede anular un recibo cerrado.'], 422);
            }
            if ($reciboPago->estaAnulado()) {
                return response()->json(['message' => 'El recibo ya está anulado.'], 422);
            }

            // Revertir carteras afectadas vía la pivot (tipo Cartera = 0)
            $reciboPago->conceptosPago()
                ->wherePivot('tipo', 0)
                ->wherePivotNotNull('id_relacional')
                ->withPivot(['subtotal', 'id_relacional'])
                ->get()
                ->each(function ($concepto) {
                    $cartera = Cartera::find($concepto->pivot->id_relacional);
                    if ($cartera && $concepto->pivot->subtotal > 0) {
                        $cartera->revertirPago((float) $concepto->pivot->subtotal);
                    }
                });

            $reciboPago->anular();

            Log::info('Recibo de pago anulado', [
                'recibo_id'     => $reciboPago->id,
                'numero_recibo' => $reciboPago->numero_recibo,
                'user_id'       => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Recibo de pago anulado exitosamente.',
                'data'    => new ReciboPagoResource($reciboPago->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al anular recibo de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al anular el recibo de pago.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cierra el recibo de pago especificado.
     *
     * @param Request $request Solicitud HTTP con número de cierre opcional
     * @param ReciboPago $reciboPago Recibo de pago a cerrar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function cerrar(Request $request, ReciboPago $reciboPago): JsonResponse
    {
        try {
            // Validar que el recibo no esté anulado
            if ($reciboPago->estaAnulado()) {
                return response()->json([
                    'message' => 'No se puede cerrar un recibo anulado.',
                ], 422);
            }

            // Validar que el recibo no esté ya cerrado
            if ($reciboPago->estaCerrado()) {
                return response()->json([
                    'message' => 'El recibo ya está cerrado.',
                ], 422);
            }

            $numeroCierre = $request->integer('cierre', null);
            $reciboPago->cerrar($numeroCierre);

            Log::info('Recibo de pago cerrado', [
                'recibo_id' => $reciboPago->id,
                'numero_recibo' => $reciboPago->numero_recibo,
                'cierre' => $reciboPago->cierre,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Recibo de pago cerrado exitosamente.',
                'data' => new ReciboPagoResource($reciboPago->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cerrar recibo de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al cerrar el recibo de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera el PDF del recibo de pago.
     *
     * @param ReciboPago $reciboPago Recibo de pago para generar PDF
     * @return \Illuminate\Http\Response Respuesta con el PDF
     */
    public function generarPDF(ReciboPago $reciboPago)
    {
        try {
            $pdfService = new ReciboPagoPDFService();
            $pdf = $pdfService->generarPDF($reciboPago);

            return $pdf->download('Recibo_' . $reciboPago->numero_recibo . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error al generar PDF del recibo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar el PDF del recibo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envía el recibo de pago por correo electrónico al estudiante.
     *
     * @param ReciboPago $reciboPago Recibo de pago a enviar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function enviarEmail(ReciboPago $reciboPago): JsonResponse
    {
        try {
            // Validar que el recibo tenga un estudiante asociado
            if (!$reciboPago->estudiante_id) {
                return response()->json([
                    'message' => 'El recibo no tiene un estudiante asociado.',
                ], 422);
            }

            // Cargar relación estudiante
            $reciboPago->load('estudiante');

            // Validar que el estudiante tenga email
            if (!$reciboPago->estudiante->email) {
                return response()->json([
                    'message' => 'El estudiante no tiene un correo electrónico configurado.',
                ], 422);
            }

            // Enviar correo
            Mail::to($reciboPago->estudiante->email)->send(new ReciboPagoMail($reciboPago));

            Log::info('Recibo de pago enviado por correo', [
                'recibo_id' => $reciboPago->id,
                'numero_recibo' => $reciboPago->numero_recibo,
                'estudiante_email' => $reciboPago->estudiante->email,
            ]);

            return response()->json([
                'message' => 'Recibo de pago enviado por correo exitosamente.',
                'recibo_id' => $reciboPago->id,
                'numero_recibo' => $reciboPago->numero_recibo,
                'estudiante_email' => $reciboPago->estudiante->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar correo del recibo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al enviar el correo del recibo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera reportes de recibos de pago con diferentes filtros.
     *
     * @param Request $request Solicitud HTTP con filtros para el reporte
     * @return JsonResponse Respuesta JSON con los datos del reporte
     */
    public function reportes(Request $request): JsonResponse
    {
        try {
            $query = ReciboPago::query();

            // Aplicar filtros
            if ($request->filled('sede_id')) {
                $query->bySede($request->integer('sede_id'));
            }

            if ($request->filled('estudiante_id')) {
                $query->byEstudiante($request->integer('estudiante_id'));
            }

            if ($request->filled('cajero_id')) {
                $query->byCajero($request->integer('cajero_id'));
            }

            if ($request->filled('origen')) {
                $query->byOrigen($request->integer('origen'));
            }

            if ($request->filled('status')) {
                $query->byStatus($request->integer('status'));
            }

            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $query->byFechaRange($request->date('fecha_inicio'), $request->date('fecha_fin'));
            }

            if ($request->filled('producto_id')) {
                $query->byProducto($request->integer('producto_id'));
            }

            if ($request->filled('poblacion_id')) {
                $query->byPoblacion($request->integer('poblacion_id'));
            }

            // Solo recibos vigentes por defecto
            if ($request->boolean('vigentes', true)) {
                $query->vigentes();
            }

            // Cargar relaciones necesarias
            $query->with(['sede', 'estudiante', 'cajero', 'conceptosPago', 'productos', 'descuentos', 'mediosPago']);

            // Tipo de reporte
            $tipoReporte = $request->get('tipo_reporte', 'resumen');

            switch ($tipoReporte) {
                case 'por_sede':
                    $resultado = $query->selectRaw('sede_id, COUNT(*) as total_recibos, SUM(valor_total) as total_ingresos, SUM(descuento_total) as total_descuentos')
                        ->groupBy('sede_id')
                        ->get();
                    break;

                case 'por_producto':
                    $resultado = $query->join('recibo_pago_producto', 'recibos_pago.id', '=', 'recibo_pago_producto.recibo_pago_id')
                        ->selectRaw('recibo_pago_producto.producto_id, COUNT(DISTINCT recibos_pago.id) as total_recibos, SUM(recibo_pago_producto.subtotal) as total_ventas')
                        ->groupBy('recibo_pago_producto.producto_id')
                        ->get();
                    break;

                case 'por_cajero':
                    $resultado = $query->selectRaw('cajero_id, COUNT(*) as total_recibos, SUM(valor_total) as total_ingresos')
                        ->groupBy('cajero_id')
                        ->get();
                    break;

                case 'por_descuentos':
                    $resultado = $query->join('recibo_pago_descuento', 'recibos_pago.id', '=', 'recibo_pago_descuento.recibo_pago_id')
                        ->selectRaw('recibo_pago_descuento.descuento_id, COUNT(DISTINCT recibos_pago.id) as total_aplicaciones, SUM(recibo_pago_descuento.valor_descuento) as total_descuentos')
                        ->groupBy('recibo_pago_descuento.descuento_id')
                        ->get();
                    break;

                case 'por_poblacion':
                    $resultado = $query->join('sedes', 'recibos_pago.sede_id', '=', 'sedes.id')
                        ->selectRaw('sedes.poblacion_id, COUNT(*) as total_recibos, SUM(recibos_pago.valor_total) as total_ingresos')
                        ->groupBy('sedes.poblacion_id')
                        ->get();
                    break;

                default: // resumen
                    $resultado = [
                        'total_recibos' => $query->count(),
                        'total_ingresos' => $query->sum('valor_total'),
                        'total_descuentos' => $query->sum('descuento_total'),
                        'ingresos_netos' => $query->sum(DB::raw('valor_total - descuento_total')),
                        'por_origen' => $query->selectRaw('origen, COUNT(*) as total, SUM(valor_total) as ingresos')
                            ->groupBy('origen')
                            ->get(),
                        'por_status' => $query->selectRaw('status, COUNT(*) as total, SUM(valor_total) as ingresos')
                            ->groupBy('status')
                            ->get(),
                    ];
                    break;
            }

            return response()->json([
                'message' => 'Reporte generado exitosamente.',
                'tipo_reporte' => $tipoReporte,
                'filtros_aplicados' => $request->only(['sede_id', 'estudiante_id', 'cajero_id', 'origen', 'status', 'fecha_inicio', 'fecha_fin', 'producto_id', 'poblacion_id']),
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al generar reporte: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar el reporte.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

