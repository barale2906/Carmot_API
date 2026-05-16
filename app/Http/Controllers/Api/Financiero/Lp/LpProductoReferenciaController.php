<?php

namespace App\Http\Controllers\Api\Financiero\Lp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Lp\StoreLpProductoReferenciaRequest;
use App\Http\Requests\Api\Financiero\Lp\SyncLpProductoReferenciasRequest;
use App\Http\Resources\Api\Financiero\Lp\LpProductoReferenciaResource;
use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpProductoReferencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador LpProductoReferenciaController
 *
 * Gestiona los vínculos entre productos LP y sus referencias académicas
 * (cursos o módulos). Implementa la opción "manual guiada" de vinculación:
 * el usuario financiero puede ver qué cursos/módulos aún no tienen producto
 * LP asignado y proceder a vincularlos.
 *
 * Endpoints principales:
 * - index         → Lista de referencias de un producto o todas las referencias
 * - store         → Vincula una referencia académica a un producto
 * - destroy       → Desvincula una referencia específica
 * - sync          → Reemplaza masivamente todas las referencias de un producto
 * - sinVincular   → Lista cursos/módulos que no tienen ningún producto LP asignado
 *
 * @package App\Http\Controllers\Api\Financiero\Lp
 */
class LpProductoReferenciaController extends Controller
{
    /**
     * Constructor del controlador.
     * Configura middlewares de autenticación y permisos granulares.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_lp_producto_referencias')->only(['index', 'show', 'sinVincular']);
        $this->middleware('permission:fin_lp_productoReferenciaVincular')->only(['store', 'sync']);
        $this->middleware('permission:fin_lp_productoReferenciaDesvincular')->only(['destroy']);
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Muestra una lista paginada de referencias de productos LP.
     *
     * Query params disponibles:
     * - lp_producto_id  (int)    Filtra las referencias de un producto específico.
     * - referencia_tipo (string) Filtra por tipo: 'curso' o 'modulo'.
     * - with_producto   (bool)   Si true, incluye los datos del producto LP en la respuesta.
     * - per_page        (int)    Registros por página. Default: 15.
     *
     * @param  Request  $request  Solicitud HTTP con los filtros opcionales.
     * @return JsonResponse       Colección paginada de LpProductoReferenciaResource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = LpProductoReferencia::query();

            if ($request->filled('lp_producto_id')) {
                $query->delProducto($request->integer('lp_producto_id'));
            }

            if ($request->filled('referencia_tipo')) {
                $tipo = $request->string('referencia_tipo')->toString();
                $query->where('referencia_tipo', $tipo);
            }

            if ($request->boolean('with_producto', false)) {
                $query->with('producto.tipoProducto');
            }

            $query->orderBy('created_at', 'desc');

            $referencias = $query->paginate($request->integer('per_page', 15));

            // Eager load de las entidades académicas para evitar N+1
            $this->cargarEntidadesAcademicas(collect($referencias->items()));

            return response()->json([
                'data' => LpProductoReferenciaResource::collection($referencias),
                'meta' => [
                    'current_page' => $referencias->currentPage(),
                    'last_page'    => $referencias->lastPage(),
                    'per_page'     => $referencias->perPage(),
                    'total'        => $referencias->total(),
                    'from'         => $referencias->firstItem(),
                    'to'           => $referencias->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las referencias.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra una referencia específica con su entidad académica resuelta.
     * Carga automáticamente el producto LP y su tipo de producto.
     *
     * @param  LpProductoReferencia  $lpProductoReferencia  Referencia a mostrar (Route Model Binding).
     * @return JsonResponse                                  LpProductoReferenciaResource con datos completos.
     */
    public function show(LpProductoReferencia $lpProductoReferencia): JsonResponse
    {
        try {
            $lpProductoReferencia->load('producto.tipoProducto');
            $this->cargarEntidadesAcademicas(collect([$lpProductoReferencia]));

            return response()->json([
                'data' => new LpProductoReferenciaResource($lpProductoReferencia),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la referencia.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vincula una entidad académica (curso o módulo) a un producto LP.
     * La unicidad del vínculo es validada por StoreLpProductoReferenciaRequest.
     *
     * @param  StoreLpProductoReferenciaRequest  $request  Datos validados del vínculo.
     * @return JsonResponse                                 LpProductoReferenciaResource del vínculo creado (HTTP 201).
     */
    public function store(StoreLpProductoReferenciaRequest $request): JsonResponse
    {
        try {
            $referencia = LpProductoReferencia::create($request->validated());

            $referencia->load('producto.tipoProducto');
            $this->cargarEntidadesAcademicas(collect([$referencia]));

            return response()->json([
                'message' => 'Referencia vinculada exitosamente.',
                'data'    => new LpProductoReferenciaResource($referencia),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al vincular la referencia.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desvincula (elimina) una referencia específica de su producto LP.
     * Operación permanente: no usa soft delete ya que el vínculo no tiene
     * historial propio y puede recrearse en cualquier momento.
     *
     * @param  LpProductoReferencia  $lpProductoReferencia  Referencia a eliminar (Route Model Binding).
     * @return JsonResponse                                  Mensaje de confirmación.
     */
    public function destroy(LpProductoReferencia $lpProductoReferencia): JsonResponse
    {
        try {
            $lpProductoReferencia->delete();

            return response()->json([
                'message' => 'Referencia desvinculada exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al desvincular la referencia.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Operaciones especiales
    // -------------------------------------------------------------------------

    /**
     * Reemplaza masivamente todas las referencias de un producto LP.
     *
     * El array "referencias" enviado se convierte en el nuevo conjunto completo
     * de referencias, eliminando las anteriores y creando las nuevas en una
     * transacción atómica. Enviar "referencias": [] desvincula todo.
     *
     * Ideal para un editor de vínculos tipo checkbox donde el usuario marca/desmarca
     * múltiples cursos/módulos y confirma todos los cambios de una vez.
     *
     * @param  SyncLpProductoReferenciasRequest  $request  Datos validados con el array de referencias.
     * @return JsonResponse                                 Colección de las referencias resultantes y conteo total.
     */
    public function sync(SyncLpProductoReferenciasRequest $request): JsonResponse
    {
        try {
            $productoId  = $request->integer('lp_producto_id');
            $nuevasRefs  = $request->input('referencias', []);

            DB::beginTransaction();

            // Eliminar todas las referencias actuales del producto
            LpProductoReferencia::where('lp_producto_id', $productoId)->delete();

            // Insertar las nuevas referencias
            $creadas = collect($nuevasRefs)->map(fn ($ref) => LpProductoReferencia::create([
                'lp_producto_id'  => $productoId,
                'referencia_id'   => (int) $ref['referencia_id'],
                'referencia_tipo' => $ref['referencia_tipo'],
            ]));

            DB::commit();

            // Eager load para la respuesta
            $creadas->each(fn ($r) => $r->load('producto.tipoProducto'));
            $this->cargarEntidadesAcademicas($creadas);

            return response()->json([
                'message' => 'Referencias sincronizadas exitosamente.',
                'total'   => $creadas->count(),
                'data'    => LpProductoReferenciaResource::collection($creadas),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al sincronizar las referencias.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista cursos o módulos que aún no tienen ningún producto LP vinculado.
     *
     * Es la base de la pantalla "Entidades sin precio" del módulo financiero.
     * Permite al equipo financiero identificar qué cursos/módulos académicos
     * carecen de un producto LP y proceder a crearles uno o vincularlos a uno existente.
     *
     * Solo retorna entidades activas (status = 1) y excluye las que ya aparecen
     * en la tabla lp_producto_referencias para el tipo indicado.
     *
     * Query params:
     * - referencia_tipo (string, requerido) Tipo de entidad a listar: 'curso' o 'modulo'.
     * - search          (string, opcional)  Filtra por nombre (búsqueda parcial).
     * - per_page        (int,    opcional)  Registros por página. Default: 15. Máx: 100.
     *
     * @param  Request  $request  Solicitud HTTP con los filtros y parámetros de paginación.
     * @return JsonResponse       Lista paginada de cursos/módulos sin producto LP asignado.
     */
    public function sinVincular(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'referencia_tipo' => [
                    'required',
                    'string',
                    \Illuminate\Validation\Rule::in(LpProductoReferencia::tiposValidos()),
                ],
                'search'   => 'sometimes|string|max:150',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $tipo = $request->string('referencia_tipo')->toString();

            if ($tipo === LpProductoReferencia::TIPO_CURSO) {
                $query = Curso::query();
            } else {
                $query = Modulo::query();
            }

            // Excluir los que ya tienen al menos un vínculo del tipo correcto
            $query->whereNotIn('id', function ($sub) use ($tipo) {
                $sub->select('referencia_id')
                    ->from('lp_producto_referencias')
                    ->where('referencia_tipo', $tipo);
            });

            // Solo activos
            $query->where('status', 1);

            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where('nombre', 'like', "%{$search}%");
            }

            $query->orderBy('nombre');
            $resultados = $query->paginate($request->integer('per_page', 15));

            $datos = collect($resultados->items())->map(fn ($item) => [
                'id'     => $item->id,
                'nombre' => $item->nombre,
                'codigo' => $item->codigo ?? null,
                'tipo'   => $tipo,
                'tipo_label' => $tipo === LpProductoReferencia::TIPO_CURSO ? 'Curso' : 'Módulo',
            ]);

            return response()->json([
                'data' => $datos,
                'meta' => [
                    'current_page' => $resultados->currentPage(),
                    'last_page'    => $resultados->lastPage(),
                    'per_page'     => $resultados->perPage(),
                    'total'        => $resultados->total(),
                    'from'         => $resultados->firstItem(),
                    'to'           => $resultados->lastItem(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los registros sin vincular.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Carga eficientemente las entidades académicas (Curso o Modulo) para una
     * colección de LpProductoReferencia, evitando el problema N+1.
     *
     * Estrategia: agrupa los IDs por tipo, realiza una única consulta por tipo
     * (máximo 2 queries independientemente del tamaño de la colección) y asigna
     * el modelo cargado como relación en cada instancia de LpProductoReferencia
     * usando setRelation(). El Resource lee esa relación a través del accessor
     * referenciaModel del modelo.
     *
     * Relaciones asignadas por tipo:
     * - 'curso'  → setRelation('curso',  Curso)
     * - 'modulo' → setRelation('modulo', Modulo)
     *
     * @param  \Illuminate\Support\Collection<int, LpProductoReferencia>  $referencias  Colección a procesar.
     * @return void
     */
    private function cargarEntidadesAcademicas(\Illuminate\Support\Collection $referencias): void
    {
        $cursoIds  = $referencias->where('referencia_tipo', LpProductoReferencia::TIPO_CURSO)->pluck('referencia_id')->unique();
        $moduloIds = $referencias->where('referencia_tipo', LpProductoReferencia::TIPO_MODULO)->pluck('referencia_id')->unique();

        $cursos  = $cursoIds->isNotEmpty()  ? Curso::whereIn('id', $cursoIds)->get()->keyBy('id')   : collect();
        $modulos = $moduloIds->isNotEmpty() ? Modulo::whereIn('id', $moduloIds)->get()->keyBy('id') : collect();

        $referencias->each(function (LpProductoReferencia $ref) use ($cursos, $modulos) {
            if ($ref->referencia_tipo === LpProductoReferencia::TIPO_CURSO) {
                $ref->setRelation('curso', $cursos->get($ref->referencia_id));
            } else {
                $ref->setRelation('modulo', $modulos->get($ref->referencia_id));
            }
        });
    }
}
