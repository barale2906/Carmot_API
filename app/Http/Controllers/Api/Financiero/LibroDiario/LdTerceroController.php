<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\UpdateLdTerceroRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdTerceroResource;
use App\Models\Financiero\LibroDiario\LdTercero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del catálogo de terceros del libro diario.
 *
 * Los terceros se crean desde el registro del movimiento (si la identificación no
 * existe); aquí se buscan para seleccionarlos y se corrigen sus datos. No se eliminan
 * porque quedan referenciados en los movimientos: se inactivan con `status`.
 */
class LdTerceroController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // La búsqueda la usa quien registra o edita movimientos para seleccionar el tercero
        $this->middleware('permission:fin_libroDiario|fin_ldRegistroCrear|fin_ldRegistroEditar|fin_ldCatalogos')->only(['index', 'show']);
        $this->middleware('permission:fin_ldCatalogoEditar')->only(['update']);
    }

    /**
     * Lista paginada de terceros. Filtros: search (identificación que empieza por el
     * texto o nombre que lo contiene), status y tipo_identificacion.
     */
    public function index(Request $request): JsonResponse
    {
        $terceros = LdTercero::withFilters($request->only(['search', 'status', 'tipo_identificacion']))
            ->withCount('movimientos')
            ->withSorting($request->get('sort_by', 'nombre'), $request->get('sort_direction', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdTerceroResource::collection($terceros),
            'meta' => [
                'current_page' => $terceros->currentPage(),
                'last_page' => $terceros->lastPage(),
                'per_page' => $terceros->perPage(),
                'total' => $terceros->total(),
                'from' => $terceros->firstItem(),
                'to' => $terceros->lastItem(),
            ],
        ]);
    }

    /**
     * Muestra un tercero con la cantidad de movimientos registrados a su nombre.
     */
    public function show(LdTercero $ldTercero): JsonResponse
    {
        return response()->json([
            'data' => new LdTerceroResource($ldTercero->loadCount('movimientos')),
        ]);
    }

    /**
     * Actualiza los datos del tercero o lo inactiva. Los movimientos ya registrados
     * conservan la copia del tercero que tenían.
     */
    public function update(UpdateLdTerceroRequest $request, LdTercero $ldTercero): JsonResponse
    {
        $ldTercero->update($request->validated());

        return response()->json([
            'message' => 'Tercero actualizado exitosamente.',
            'data' => new LdTerceroResource($ldTercero),
        ]);
    }
}
