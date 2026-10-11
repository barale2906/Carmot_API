<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdSoporteRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdSoporteResource;
use App\Models\Financiero\LibroDiario\LdSoporte;
use App\Services\Financiero\LibroDiario\LdSoporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador de archivos de soporte del libro diario (facturas, recibos, comprobantes).
 *
 * Se pueden cargar soportes en cualquier momento (la factura puede llegar después
 * del cierre); solo se eliminan mientras el turno del registro siga abierto.
 */
class LdSoporteController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct(private readonly LdSoporteService $soporteService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_libroDiario')->only(['archivo']);
        $this->middleware('permission:fin_ldRegistroCrear')->only(['store']);
        $this->middleware('permission:fin_ldRegistroEditar')->only(['destroy']);
    }

    /**
     * Carga un archivo de soporte para un movimiento o una consignación.
     */
    public function store(StoreLdSoporteRequest $request): JsonResponse
    {
        $registro = $request->soportable();
        abort_unless($request->user()->tieneAccesoSede($registro->sede_id), 403, 'No tiene asignada la sede de este registro.');

        $soporte = $this->soporteService->subir($registro, $request->file('archivo'), $request->tipo, $request->user());

        return response()->json([
            'message' => 'Soporte cargado exitosamente.',
            'data' => new LdSoporteResource($soporte->load('usuario')),
        ], 201);
    }

    /**
     * Descarga el archivo de soporte.
     */
    public function archivo(Request $request, LdSoporte $ldSoporte): StreamedResponse
    {
        $this->autorizarSede($request, $ldSoporte);

        $disco = Storage::disk(LdSoporte::DISCO);
        abort_unless($disco->exists($ldSoporte->archivo_path), 404, 'El archivo de soporte no existe.');

        return $disco->download($ldSoporte->archivo_path, $ldSoporte->nombre_original);
    }

    /**
     * Elimina el soporte y su archivo mientras el turno del registro siga abierto.
     */
    public function destroy(Request $request, LdSoporte $ldSoporte): JsonResponse
    {
        $this->autorizarSede($request, $ldSoporte);

        if (! $ldSoporte->soportable->turnoCaja->estaAbierto()) {
            return response()->json([
                'message' => 'No se pueden eliminar soportes de un turno de caja cerrado.',
            ], 422);
        }

        $this->soporteService->eliminar($ldSoporte);

        return response()->json([
            'message' => 'Soporte eliminado exitosamente.',
        ]);
    }

    /**
     * El registro del soporte debe ser de una sede asignada al usuario.
     */
    private function autorizarSede(Request $request, LdSoporte $soporte): void
    {
        abort_unless(
            $request->user()->tieneAccesoSede($soporte->soportable->sede_id),
            403,
            'No tiene asignada la sede de este registro.'
        );
    }
}
