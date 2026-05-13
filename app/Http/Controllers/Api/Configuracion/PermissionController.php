<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Configuracion\PermissionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_permisos')->only(['index']);
    }

    /**
     * Listar todos los permisos disponibles.
     *
     * @queryParam search string Filtrar por nombre o descripción. Ejemplo: co_users
     * @queryParam guard_name string Filtrar por guard. Ejemplo: web
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Permission::query();

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            if ($request->filled('guard_name')) {
                $query->where('guard_name', $request->input('guard_name'));
            }

            $perPage = $request->input('per_page', 0);

            // Si per_page = 0 devuelve todos sin paginar
            if ($perPage == 0) {
                $permissions = $query->orderBy('name')->get();
                return PermissionResource::collection($permissions);
            }

            $permissions = $query->orderBy('name')->paginate((int) $perPage);

            return PermissionResource::collection($permissions);

        } catch (\Exception $e) {
            Log::error('Error al obtener los permisos', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener los permisos',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
