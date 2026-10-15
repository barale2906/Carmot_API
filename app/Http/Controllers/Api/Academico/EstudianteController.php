<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreEstudianteRequest;
use App\Http\Requests\Api\Academico\UpdateEstudianteRequest;
use App\Http\Resources\Api\Academico\EstudianteResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

/**
 * Estudiantes para los flujos de operación: buscar, ver, registrar y corregir datos
 * desde el asistente de matrícula, el de ventas y el formulario de recibos (B49).
 * La gestión de usuarios (/configuracion/users) es exclusiva del superusuario, así que
 * cajeros, coordinadores y auxiliares no podían buscar ni registrar estudiantes.
 */
class EstudianteController extends Controller
{
    /** Quien matricula, vende o cobra necesita encontrar al estudiante. */
    private const PERMISOS_CONSULTA = 'permission:aca_matriculas|aca_matriculaCrear|inv_ventasCrear|fin_recibos_pago|fin_reciboPagoCrear';

    /**
     * Registra los middlewares de permisos.
     */
    public function __construct()
    {
        $this->middleware(self::PERMISOS_CONSULTA)->only(['index', 'show']);
        $this->middleware('permission:aca_matriculaCrear|inv_ventasCrear')->only(['store']);
        $this->middleware('permission:aca_matriculaCrear|aca_matriculaEditar')->only(['update']);
    }

    /**
     * Busca estudiantes por nombre, correo o documento (N01: por defecto no incluye al
     * personal que no estudia). Con `solo_personal=1` busca solo personal (p. ej. el selector
     * de comerciales de la matrícula, hasta 200 por página). Con `incluir_personal=1` busca a
     * cualquier persona, para matricular a alguien del personal; solo con `aca_matriculaCrear`.
     *
     * @param Request $request search, solo_personal, incluir_personal, per_page
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $incluirPersonal = $request->boolean('incluir_personal') && $request->user()->can('aca_matriculaCrear');

        $estudiantes = User::buscar($request->input('search'))
            ->when($request->boolean('solo_personal'), fn ($q) => $q->personal())
            ->when(! $request->boolean('solo_personal') && ! $incluirPersonal, fn ($q) => $q->estudiantes())
            ->with('roles')
            ->orderBy('primer_nombre')
            ->paginate(min((int) $request->input('per_page', 15), 200));

        return EstudianteResource::collection($estudiantes);
    }

    /**
     * Muestra una persona.
     *
     * @param User $estudiante
     * @return EstudianteResource
     */
    public function show(User $estudiante): EstudianteResource
    {
        return new EstudianteResource($estudiante->load('roles'));
    }

    /**
     * Registra un estudiante nuevo con el rol alumno y, si se indican, sus sedes.
     *
     * @param StoreEstudianteRequest $request
     * @return JsonResponse
     */
    public function store(StoreEstudianteRequest $request): JsonResponse
    {
        $estudiante = User::create([
            ...$request->safe()->only(['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'email', 'documento']),
            'password' => Hash::make($request->password),
        ]);
        $estudiante->assignRole(User::ROL_ALUMNO);

        if ($request->filled('sedes')) {
            $estudiante->sedes()->sync($request->sedes);
        }

        return (new EstudianteResource($estudiante->load('roles')))->response()->setStatusCode(201);
    }

    /**
     * Corrige los datos de identificación de un estudiante. Solo aplica a quien solo tiene
     * el rol alumno: los datos del personal se gestionan en Configuración (403).
     *
     * @param UpdateEstudianteRequest $request
     * @param User $estudiante
     * @return EstudianteResource
     */
    public function update(UpdateEstudianteRequest $request, User $estudiante): EstudianteResource
    {
        abort_unless($estudiante->esSoloEstudiante(), 403, 'Los datos del personal se gestionan en Configuración → Usuarios.');

        $estudiante->update($request->validated());

        return new EstudianteResource($estudiante->load('roles'));
    }
}
