<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\DashboardResumenResource;
use App\Services\Dashboard\DashboardResumenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resumen del Dashboard de inicio con datos reales.
 */
class DashboardResumenController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(private readonly DashboardResumenService $resumen) {}

    /**
     * Tarjetas, alertas, actividad reciente, próximos eventos y retención.
     *
     * No exige un permiso propio: el dashboard es visible para todo usuario autenticado
     * y cada sección se arma solo con los módulos que el usuario puede ver.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new DashboardResumenResource($this->resumen->para($request->user())),
        ]);
    }
}
