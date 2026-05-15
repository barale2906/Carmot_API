<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreMatriculaRequest;
use App\Http\Requests\Api\Academico\UpdateMatriculaRequest;
use App\Http\Resources\Api\Academico\MatriculaResource;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\Poblacion;
use App\Models\User;
use App\Traits\HasActiveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatriculaController extends Controller
{
    use HasActiveStatus;

    /** Campos permitidos en store/update (excluye los gestionados automáticamente). */
    private const FILLABLE_FIELDS = [
        'curso_id', 'ciclo_id', 'estudiante_id', 'matriculado_por_id', 'comercial_id',
        'fecha_matricula', 'fecha_inicio', 'monto', 'valor_cuota', 'observaciones',
        'tipo_identificacion', 'departamento_expedicion', 'ciudad_expedicion',
        'fecha_nacimiento', 'genero', 'estado_civil', 'grupo_sanguineo', 'rh',
        'direccion', 'lugar_origen_id', 'celular', 'telefono',
        'nivel_educacion', 'ocupacion', 'empresa', 'estrato', 'regimen_salud',
        'enfermedad_prioritaria', 'discapacidad',
        'conocimiento_curso', 'como_entero_curso',
        'talla_overol', 'talla_botas',
        'nombre_contacto', 'telefono_contacto', 'correo_contacto',
        'aprueba_uso_imagen', 'multiculturalidad', 'foto',
        'status',
    ];

    public function __construct()
    {
        $this->middleware('permission:aca_matriculas')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_matriculaCrear')->only(['store']);
        $this->middleware('permission:aca_matriculaEditar')->only(['update']);
        $this->middleware('permission:aca_matriculaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de matrículas con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'status', 'curso_id', 'ciclo_id', 'estudiante_id',
            'fecha_matricula_inicio', 'fecha_matricula_fin',
            'monto_min', 'monto_max', 'include_trashed', 'only_trashed',
        ]);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'ciclo', 'estudiante'];

        $matriculas = Matricula::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => MatriculaResource::collection($matriculas),
            'meta' => [
                'current_page' => $matriculas->currentPage(),
                'last_page'    => $matriculas->lastPage(),
                'per_page'     => $matriculas->perPage(),
                'total'        => $matriculas->total(),
                'from'         => $matriculas->firstItem(),
                'to'           => $matriculas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva matrícula.
     */
    public function store(StoreMatriculaRequest $request): JsonResponse
    {
        $data = $request->only(self::FILLABLE_FIELDS);
        $data['status'] = $data['status'] ?? 1;

        $matricula = Matricula::create($data);
        $matricula->load(['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial', 'lugarOrigen']);

        return response()->json([
            'message' => 'Matrícula creada exitosamente.',
            'data'    => new MatriculaResource($matricula),
        ], 201);
    }

    /**
     * Muestra la matrícula especificada.
     */
    public function show(Request $request, Matricula $matricula): JsonResponse
    {
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial', 'lugarOrigen'];

        $matricula->load($relations);

        return response()->json([
            'data' => new MatriculaResource($matricula),
        ]);
    }

    /**
     * Actualiza la matrícula especificada.
     */
    public function update(UpdateMatriculaRequest $request, Matricula $matricula): JsonResponse
    {
        $matricula->update($request->only(self::FILLABLE_FIELDS));
        $matricula->load(['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial', 'lugarOrigen']);

        return response()->json([
            'message' => 'Matrícula actualizada exitosamente.',
            'data'    => new MatriculaResource($matricula),
        ]);
    }

    /**
     * Elimina la matrícula (soft delete).
     */
    public function destroy(Matricula $matricula): JsonResponse
    {
        $matricula->delete();

        return response()->json([
            'message' => 'Matrícula eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una matrícula eliminada.
     */
    public function restore(int $id): JsonResponse
    {
        $matricula = Matricula::onlyTrashed()->findOrFail($id);
        $matricula->restore();
        $matricula->load(['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial', 'lugarOrigen']);

        return response()->json([
            'message' => 'Matrícula restaurada exitosamente.',
            'data'    => new MatriculaResource($matricula),
        ]);
    }

    /**
     * Elimina permanentemente una matrícula.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $matricula = Matricula::onlyTrashed()->findOrFail($id);
        $matricula->forceDelete();

        return response()->json([
            'message' => 'Matrícula eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las matrículas eliminadas.
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters               = $request->only([
            'search', 'status', 'curso_id', 'ciclo_id', 'estudiante_id',
            'fecha_matricula_inicio', 'fecha_matricula_fin', 'monto_min', 'monto_max',
        ]);
        $filters['only_trashed'] = true;

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'ciclo', 'estudiante'];

        $matriculas = Matricula::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => MatriculaResource::collection($matriculas),
            'meta' => [
                'current_page' => $matriculas->currentPage(),
                'last_page'    => $matriculas->lastPage(),
                'per_page'     => $matriculas->perPage(),
                'total'        => $matriculas->total(),
                'from'         => $matriculas->firstItem(),
                'to'           => $matriculas->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene los catálogos de filtros y opciones de enumerados para el frontend.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status_options'           => self::getActiveStatusOptions(),
                'tipos_identificacion'     => Matricula::TIPOS_IDENTIFICACION,
                'generos'                  => Matricula::GENEROS,
                'estados_civiles'          => Matricula::ESTADOS_CIVILES,
                'grupos_sanguineos'        => Matricula::GRUPOS_SANGUINEOS,
                'rhs'                      => Matricula::RHS,
                'niveles_educacion'        => Matricula::NIVELES_EDUCACION,
                'regimenes_salud'          => Matricula::REGIMENES_SALUD,
                'cursos'                   => Curso::select('id', 'nombre')->get(),
                'ciclos'                   => Ciclo::select('id', 'nombre')->get(),
                'estudiantes'              => User::select('id', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'email')->get(),
                'poblaciones'              => Poblacion::where('status', 1)->select('id', 'pais', 'provincia', 'nombre')->get(),
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de matrículas.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total'     => Matricula::count(),
                'activas'   => Matricula::whereNull('deleted_at')->count(),
                'eliminadas' => Matricula::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activas'   => Matricula::where('status', 1)->count(),
                'inactivas' => Matricula::where('status', 0)->count(),
                'anuladas'  => Matricula::where('status', 2)->count(),
            ],
            'por_curso' => Matricula::with('curso')
                ->selectRaw('curso_id, COUNT(*) as total')
                ->groupBy('curso_id')
                ->get()
                ->map(fn ($item) => [
                    'curso' => $item->curso->nombre ?? 'Sin curso',
                    'total' => $item->total,
                ]),
            'por_ciclo' => Matricula::with('ciclo')
                ->selectRaw('ciclo_id, COUNT(*) as total')
                ->groupBy('ciclo_id')
                ->get()
                ->map(fn ($item) => [
                    'ciclo' => $item->ciclo->nombre ?? 'Sin ciclo',
                    'total' => $item->total,
                ]),
            'monto_total'   => Matricula::sum('monto'),
            'monto_promedio' => Matricula::avg('monto'),
            'monto_minimo'  => Matricula::min('monto'),
            'monto_maximo'  => Matricula::max('monto'),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
