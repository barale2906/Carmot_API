<?php

namespace App\Http\Controllers\Api\Financiero\ConceptoPago;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\ConceptoPago\StoreConceptoPagoRequest;
use App\Http\Requests\Api\Financiero\ConceptoPago\UpdateConceptoPagoRequest;
use App\Http\Resources\Api\Financiero\ConceptoPago\ConceptoPagoResource;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador ConceptoPagoController
 *
 * Gestiona las operaciones CRUD para los conceptos de pago del sistema financiero.
 * Permite crear, listar, mostrar, actualizar y eliminar conceptos de pago.
 *
 * @package App\Http\Controllers\Api\Financiero\ConceptoPago
 */
class ConceptoPagoController extends Controller
{
    /**
     * Constructor del controlador.
     * Configura los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_conceptos_pago')->only(['index', 'show']);
        $this->middleware('permission:fin_conceptoPagoCrear')->only(['store']);
        $this->middleware('permission:fin_conceptoPagoEditar')->only(['update']);
        $this->middleware('permission:fin_conceptoPagoInactivar')->only(['destroy']);
    }

    /**
     * Muestra una lista de conceptos de pago.
     * Permite filtrar, ordenar y cargar relaciones.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de conceptos de pago
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Preparar filtros
            $filters = $request->only([
                'search',
                'tipo',
                'valor_min',
                'valor_max',
                'include_trashed',
                'only_trashed'
            ]);

            // Construir query
            $query = ConceptoPago::query();

            // Aplicar búsqueda si existe
            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%");
                });
            }

            // Aplicar filtro por tipo
            if ($request->filled('tipo')) {
                $tipoBuscado = $request->string('tipo');
                $query->where('tipo', $tipoBuscado);
            }

            // Aplicar filtro por valor mínimo
            if ($request->filled('valor_min')) {
                $query->where('valor', '>=', $request->numeric('valor_min'));
            }

            // Aplicar filtro por valor máximo
            if ($request->filled('valor_max')) {
                $query->where('valor', '<=', $request->numeric('valor_max'));
            }

            if ($request->boolean('include_trashed', false)) {
                $query->withTrashed();
            }

            if ($request->boolean('only_trashed', false)) {
                $query->onlyTrashed();
            }

            // Aplicar ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginar
            $conceptosPago = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => ConceptoPagoResource::collection($conceptosPago),
                'meta' => [
                    'current_page' => $conceptosPago->currentPage(),
                    'last_page' => $conceptosPago->lastPage(),
                    'per_page' => $conceptosPago->perPage(),
                    'total' => $conceptosPago->total(),
                    'from' => $conceptosPago->firstItem(),
                    'to' => $conceptosPago->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los conceptos de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo concepto de pago en la base de datos.
     *
     * @param StoreConceptoPagoRequest $request Datos validados del concepto de pago
     * @return JsonResponse Respuesta JSON con el concepto de pago creado
     */
    public function store(StoreConceptoPagoRequest $request): JsonResponse
    {
        try {
            $conceptoPago = ConceptoPago::create($request->validated());

            return response()->json([
                'message' => 'Concepto de pago creado exitosamente.',
                'data' => new ConceptoPagoResource($conceptoPago),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el concepto de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el concepto de pago especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param ConceptoPago $conceptoPago Concepto de pago a mostrar
     * @return JsonResponse Respuesta JSON con los datos del concepto de pago
     */
    public function show(Request $request, ConceptoPago $conceptoPago): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ConceptoPagoResource($conceptoPago),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el concepto de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el concepto de pago especificado en la base de datos.
     *
     * @param UpdateConceptoPagoRequest $request Datos validados para actualizar
     * @param ConceptoPago $conceptoPago Concepto de pago a actualizar
     * @return JsonResponse Respuesta JSON con el concepto de pago actualizado
     */
    public function update(UpdateConceptoPagoRequest $request, ConceptoPago $conceptoPago): JsonResponse
    {
        try {
            $conceptoPago->update($request->validated());

            return response()->json([
                'message' => 'Concepto de pago actualizado exitosamente.',
                'data' => new ConceptoPagoResource($conceptoPago->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el concepto de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) el concepto de pago especificado.
     *
     * @param ConceptoPago $conceptoPago Concepto de pago a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(ConceptoPago $conceptoPago): JsonResponse
    {
        try {
            $conceptoPago->delete();

            return response()->json([
                'message' => 'Concepto de pago eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el concepto de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agrega un nuevo tipo al array de tipos disponibles del sistema.
     * Este método agrega un nuevo tipo que podrá ser usado por todos los conceptos de pago.
     *
     * @param Request $request Solicitud HTTP con el nuevo tipo
     * @return JsonResponse Respuesta JSON con el nuevo índice del tipo agregado
     */
    public function agregarTipo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tipo' => 'required|string|max:255',
            ]);

            $nuevoTipo = $request->string('tipo');
            $nuevoIndice = ConceptoPago::agregarTipo($nuevoTipo);

            if ($nuevoIndice !== null) {
                return response()->json([
                    'message' => 'Tipo agregado exitosamente al sistema.',
                    'data' => [
                        'indice' => $nuevoIndice,
                        'nombre' => $nuevoTipo,
                        'tipos_disponibles' => ConceptoPago::getTiposDisponibles(),
                    ],
                ], 201);
            } else {
                return response()->json([
                    'message' => 'El tipo ya existe en el sistema.',
                    'tipos_disponibles' => ConceptoPago::getTiposDisponibles(),
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar el tipo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene todos los tipos disponibles del sistema.
     *
     * @return JsonResponse Respuesta JSON con los tipos disponibles
     */
    public function obtenerTipos(): JsonResponse
    {
        try {
            return response()->json([
                'data' => ConceptoPago::getTiposDisponibles(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los tipos disponibles.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

