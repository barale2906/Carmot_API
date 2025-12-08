<?php

namespace App\Http\Controllers\Api\Financiero\Descuento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Descuento\StoreDescuentoRequest;
use App\Http\Requests\Api\Financiero\Descuento\UpdateDescuentoRequest;
use App\Http\Resources\Api\Financiero\Descuento\DescuentoAplicadoResource;
use App\Http\Resources\Api\Financiero\Descuento\DescuentoResource;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Descuento\DescuentoAplicado;
use App\Services\Financiero\DescuentoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador DescuentoController
 *
 * Gestiona las operaciones CRUD para los descuentos del sistema financiero.
 * Permite crear, listar, mostrar, actualizar, eliminar y aprobar descuentos.
 * También permite aplicar descuentos y consultar el historial.
 *
 * @package App\Http\Controllers\Api\Financiero\Descuento
 */
class DescuentoController extends Controller
{
    /**
     * Constructor del controlador.
     * Configura los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_descuentos')->only(['index', 'show', 'historial']);
        $this->middleware('permission:fin_descuentoCrear')->only(['store']);
        $this->middleware('permission:fin_descuentoEditar')->only(['update']);
        $this->middleware('permission:fin_descuentoInactivar')->only(['destroy']);
        $this->middleware('permission:fin_descuentoAprobar')->only(['aprobar']);
        $this->middleware('permission:fin_descuentoAplicar')->only(['aplicarDescuento']);
    }

    /**
     * Muestra una lista de descuentos.
     * Permite filtrar, ordenar y cargar relaciones.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de descuentos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Preparar query
            $query = Descuento::query();

            // Aplicar búsqueda si existe
            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo_descuento', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            // Aplicar filtros
            if ($request->filled('tipo')) {
                $query->porTipo($request->string('tipo'));
            }

            if ($request->filled('aplicacion')) {
                $query->porAplicacion($request->string('aplicacion'));
            }

            if ($request->filled('tipo_activacion')) {
                $query->porTipoActivacion($request->string('tipo_activacion'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->integer('status'));
            }

            if ($request->filled('fecha_inicio')) {
                $query->where('fecha_inicio', '>=', $request->date('fecha_inicio'));
            }

            if ($request->filled('fecha_fin')) {
                $query->where('fecha_fin', '<=', $request->date('fecha_fin'));
            }

            if ($request->filled('permite_acumulacion')) {
                $query->where('permite_acumulacion', $request->boolean('permite_acumulacion'));
            }

            if ($request->boolean('include_trashed', false)) {
                $query->withTrashed();
            }

            if ($request->boolean('only_trashed', false)) {
                $query->onlyTrashed();
            }

            // Cargar relaciones opcionales
            $relations = $request->get('relations', []);
            if (!empty($relations)) {
                $query->withRelations(explode(',', $relations));
            }

            // Aplicar ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginar
            $descuentos = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => DescuentoResource::collection($descuentos),
                'meta' => [
                    'current_page' => $descuentos->currentPage(),
                    'last_page' => $descuentos->lastPage(),
                    'per_page' => $descuentos->perPage(),
                    'total' => $descuentos->total(),
                    'from' => $descuentos->firstItem(),
                    'to' => $descuentos->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los descuentos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo descuento en la base de datos.
     *
     * @param StoreDescuentoRequest $request Datos validados del descuento
     * @return JsonResponse Respuesta JSON con el descuento creado
     */
    public function store(StoreDescuentoRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $relaciones = [];

            // Extraer relaciones many-to-many
            if (isset($data['listas_precios'])) {
                $relaciones['listas_precios'] = $data['listas_precios'];
                unset($data['listas_precios']);
            }
            if (isset($data['productos'])) {
                $relaciones['productos'] = $data['productos'];
                unset($data['productos']);
            }
            if (isset($data['sedes'])) {
                $relaciones['sedes'] = $data['sedes'];
                unset($data['sedes']);
            }
            if (isset($data['poblaciones'])) {
                $relaciones['poblaciones'] = $data['poblaciones'];
                unset($data['poblaciones']);
            }

            // Crear descuento
            $descuento = Descuento::create($data);

            // Sincronizar relaciones
            if (isset($relaciones['listas_precios'])) {
                $descuento->listasPrecios()->sync($relaciones['listas_precios']);
            }
            if (isset($relaciones['productos'])) {
                $descuento->productos()->sync($relaciones['productos']);
            }
            if (isset($relaciones['sedes'])) {
                $descuento->sedes()->sync($relaciones['sedes']);
            }
            if (isset($relaciones['poblaciones'])) {
                $descuento->poblaciones()->sync($relaciones['poblaciones']);
            }

            // Cargar relaciones para la respuesta
            $descuento->load(['listasPrecios', 'productos', 'sedes', 'poblaciones']);

            return response()->json([
                'message' => 'Descuento creado exitosamente.',
                'data' => new DescuentoResource($descuento),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el descuento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el descuento especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param Descuento $descuento Descuento a mostrar
     * @return JsonResponse Respuesta JSON con los datos del descuento
     */
    public function show(Request $request, Descuento $descuento): JsonResponse
    {
        try {
            // Cargar relaciones opcionales
            $relations = $request->get('relations', []);
            if (!empty($relations)) {
                $descuento->load(explode(',', $relations));
            }

            return response()->json([
                'data' => new DescuentoResource($descuento),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el descuento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el descuento especificado en la base de datos.
     *
     * @param UpdateDescuentoRequest $request Datos validados para actualizar
     * @param Descuento $descuento Descuento a actualizar
     * @return JsonResponse Respuesta JSON con el descuento actualizado
     */
    public function update(UpdateDescuentoRequest $request, Descuento $descuento): JsonResponse
    {
        try {
            $data = $request->validated();
            $relaciones = [];

            // Extraer relaciones many-to-many
            if (isset($data['listas_precios'])) {
                $relaciones['listas_precios'] = $data['listas_precios'];
                unset($data['listas_precios']);
            }
            if (isset($data['productos'])) {
                $relaciones['productos'] = $data['productos'];
                unset($data['productos']);
            }
            if (isset($data['sedes'])) {
                $relaciones['sedes'] = $data['sedes'];
                unset($data['sedes']);
            }
            if (isset($data['poblaciones'])) {
                $relaciones['poblaciones'] = $data['poblaciones'];
                unset($data['poblaciones']);
            }

            // Actualizar descuento
            $descuento->update($data);

            // Sincronizar relaciones si están presentes
            if (isset($relaciones['listas_precios'])) {
                $descuento->listasPrecios()->sync($relaciones['listas_precios']);
            }
            if (isset($relaciones['productos'])) {
                $descuento->productos()->sync($relaciones['productos']);
            }
            if (isset($relaciones['sedes'])) {
                $descuento->sedes()->sync($relaciones['sedes']);
            }
            if (isset($relaciones['poblaciones'])) {
                $descuento->poblaciones()->sync($relaciones['poblaciones']);
            }

            // Cargar relaciones para la respuesta
            $descuento->load(['listasPrecios', 'productos', 'sedes', 'poblaciones']);

            return response()->json([
                'message' => 'Descuento actualizado exitosamente.',
                'data' => new DescuentoResource($descuento->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el descuento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) el descuento especificado.
     *
     * @param Descuento $descuento Descuento a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(Descuento $descuento): JsonResponse
    {
        try {
            $descuento->delete();

            return response()->json([
                'message' => 'Descuento eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el descuento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprueba un descuento cambiando su estado a "Aprobado".
     *
     * @param Descuento $descuento Descuento a aprobar
     * @return JsonResponse Respuesta JSON con el descuento aprobado
     */
    public function aprobar(Descuento $descuento): JsonResponse
    {
        try {
            $descuento->update(['status' => Descuento::STATUS_APROBADO]);

            return response()->json([
                'message' => 'Descuento aprobado exitosamente.',
                'data' => new DescuentoResource($descuento->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aprobar el descuento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aplica descuentos a un precio y retorna los valores calculados.
     *
     * @param Request $request Solicitud HTTP con los parámetros necesarios
     * @return JsonResponse Respuesta JSON con los precios calculados
     */
    public function aplicarDescuento(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'precio_total' => 'required|numeric|min:0',
                'matricula' => 'required|numeric|min:0',
                'valor_cuota' => 'required|numeric|min:0',
                'producto_id' => 'required|exists:lp_productos,id',
                'lista_precio_id' => 'required|exists:lp_listas_precios,id',
                'sede_id' => 'nullable|exists:sedes,id',
                'poblacion_id' => 'nullable|exists:poblacions,id',
                'codigo_promocional' => 'nullable|string|max:50',
                'fecha_pago' => 'nullable|date',
                'fecha_programada' => 'nullable|date',
            ]);

            $service = new DescuentoService();

            $fechaPago = $request->filled('fecha_pago') ? Carbon::parse($request->date('fecha_pago')) : null;
            $fechaProgramada = $request->filled('fecha_programada') ? Carbon::parse($request->date('fecha_programada')) : null;

            $resultado = $service->calcularPrecioConDescuentos(
                precioTotal: $request->numeric('precio_total'),
                matricula: $request->numeric('matricula'),
                valorCuota: $request->numeric('valor_cuota'),
                productoId: $request->integer('producto_id'),
                listaPrecioId: $request->integer('lista_precio_id'),
                sedeId: $request->integer('sede_id'),
                poblacionId: $request->integer('poblacion_id'),
                codigoPromocional: $request->string('codigo_promocional'),
                fechaPago: $fechaPago,
                fechaProgramada: $fechaProgramada
            );

            return response()->json([
                'message' => 'Descuentos aplicados exitosamente.',
                'data' => $resultado,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aplicar los descuentos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el historial de descuentos aplicados.
     * Permite filtrar y paginar los registros.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con el historial paginado
     */
    public function historial(Request $request): JsonResponse
    {
        try {
            $query = DescuentoAplicado::query();

            // Aplicar filtros
            if ($request->filled('descuento_id')) {
                $query->where('descuento_id', $request->integer('descuento_id'));
            }

            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->integer('producto_id'));
            }

            if ($request->filled('lista_precio_id')) {
                $query->where('lista_precio_id', $request->integer('lista_precio_id'));
            }

            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->integer('sede_id'));
            }

            if ($request->filled('concepto_tipo')) {
                $query->where('concepto_tipo', $request->string('concepto_tipo'));
            }

            if ($request->filled('fecha_desde')) {
                $query->where('created_at', '>=', $request->date('fecha_desde'));
            }

            if ($request->filled('fecha_hasta')) {
                $query->where('created_at', '<=', $request->date('fecha_hasta'));
            }

            // Cargar relaciones opcionales
            $relations = $request->get('relations', []);
            if (!empty($relations)) {
                $query->withRelations(explode(',', $relations));
            } else {
                $query->with(['descuento']);
            }

            // Aplicar ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginar
            $historial = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => DescuentoAplicadoResource::collection($historial),
                'meta' => [
                    'current_page' => $historial->currentPage(),
                    'last_page' => $historial->lastPage(),
                    'per_page' => $historial->perPage(),
                    'total' => $historial->total(),
                    'from' => $historial->firstItem(),
                    'to' => $historial->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el historial de descuentos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

