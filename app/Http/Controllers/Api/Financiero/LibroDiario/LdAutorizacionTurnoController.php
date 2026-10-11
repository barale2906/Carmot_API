<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdAutorizacionTurnoRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdAutorizacionTurnoResource;
use App\Models\Financiero\LibroDiario\LdAutorizacionTurno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de autorizaciones para abrir un nuevo turno de caja en el día.
 *
 * Cuando un cajero ya cerró caja, solo puede volver a abrir turno ese mismo día
 * si alguien con `fin_ldTurnoAutorizar` le concede una autorización (de un solo uso).
 */
class LdAutorizacionTurnoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_ldTurnoAutorizar');
    }

    /**
     * Lista paginada de autorizaciones concedidas, filtrable por cajero_id y fecha.
     */
    public function index(Request $request): JsonResponse
    {
        $autorizaciones = LdAutorizacionTurno::with(['cajero', 'autorizadoPor'])
            ->when($request->filled('cajero_id'), fn ($q) => $q->where('cajero_id', $request->integer('cajero_id')))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->input('fecha')))
            ->latest('id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdAutorizacionTurnoResource::collection($autorizaciones),
            'meta' => [
                'current_page' => $autorizaciones->currentPage(),
                'last_page' => $autorizaciones->lastPage(),
                'per_page' => $autorizaciones->perPage(),
                'total' => $autorizaciones->total(),
                'from' => $autorizaciones->firstItem(),
                'to' => $autorizaciones->lastItem(),
            ],
        ]);
    }

    /**
     * Autoriza al cajero a abrir un nuevo turno hoy.
     */
    public function store(StoreLdAutorizacionTurnoRequest $request): JsonResponse
    {
        $autorizacion = LdAutorizacionTurno::create([
            'cajero_id' => $request->integer('cajero_id'),
            'fecha' => today(),
            'autorizado_por_id' => $request->user()->id,
            'motivo' => $request->motivo,
        ]);

        return response()->json([
            'message' => 'Autorización concedida. El cajero puede abrir un nuevo turno hoy.',
            'data' => new LdAutorizacionTurnoResource($autorizacion->load(['cajero', 'autorizadoPor'])),
        ], 201);
    }
}
