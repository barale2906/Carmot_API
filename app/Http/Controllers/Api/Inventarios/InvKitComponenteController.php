<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\StoreInvKitComponenteRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvKitComponenteRequest;
use App\Http\Resources\Api\Inventarios\InvKitComponenteResource;
use App\Models\Inventarios\InvKitComponente;
use App\Models\Inventarios\InvProducto;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para gestionar los componentes de un kit.
 *
 * Recurso anidado bajo /productos/{producto}/componentes.
 * El parámetro {producto} siempre debe ser un producto de tipo 'kit'.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvKitComponenteController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_productos')->only(['index']);
        $this->middleware('permission:inv_productosEditar')->only(['store', 'update', 'destroy']);
    }

    /**
     * Lista todos los componentes del kit especificado.
     *
     * @param InvProducto $producto
     * @return JsonResponse
     */
    public function index(InvProducto $producto): JsonResponse
    {
        if ($producto->tipo !== 'kit') {
            return response()->json([
                'message' => 'El producto especificado no es de tipo kit.',
            ], 422);
        }

        $componentes = InvKitComponente::where('kit_id', $producto->id)
            ->with('grupoProducto')
            ->orderBy('orden')
            ->get();

        return response()->json([
            'data' => InvKitComponenteResource::collection($componentes),
        ]);
    }

    /**
     * Agrega un componente al kit especificado.
     *
     * @param StoreInvKitComponenteRequest $request
     * @param InvProducto $producto
     * @return JsonResponse
     */
    public function store(StoreInvKitComponenteRequest $request, InvProducto $producto): JsonResponse
    {
        if ($producto->tipo !== 'kit') {
            return response()->json([
                'message' => 'Solo se pueden agregar componentes a productos de tipo kit.',
            ], 422);
        }

        $componente = InvKitComponente::create([
            'kit_id'            => $producto->id,
            'grupo_producto_id' => $request->grupo_producto_id,
            'cantidad'          => $request->cantidad,
            'orden'             => $request->orden ?? 0,
        ]);

        $componente->load('grupoProducto');

        return response()->json([
            'message' => 'Componente agregado al kit exitosamente.',
            'data'    => new InvKitComponenteResource($componente),
        ], 201);
    }

    /**
     * Actualiza la cantidad u orden de un componente del kit.
     *
     * @param UpdateInvKitComponenteRequest $request
     * @param InvProducto $producto
     * @param InvKitComponente $componente
     * @return JsonResponse
     */
    public function update(UpdateInvKitComponenteRequest $request, InvProducto $producto, InvKitComponente $componente): JsonResponse
    {
        if ($componente->kit_id !== $producto->id) {
            return response()->json([
                'message' => 'El componente no pertenece al kit especificado.',
            ], 422);
        }

        $componente->update($request->only(['cantidad', 'orden']));

        return response()->json([
            'message' => 'Componente actualizado exitosamente.',
            'data'    => new InvKitComponenteResource($componente),
        ]);
    }

    /**
     * Elimina un componente del kit.
     *
     * @param InvProducto $producto
     * @param InvKitComponente $componente
     * @return JsonResponse
     */
    public function destroy(InvProducto $producto, InvKitComponente $componente): JsonResponse
    {
        if ($componente->kit_id !== $producto->id) {
            return response()->json([
                'message' => 'El componente no pertenece al kit especificado.',
            ], 422);
        }

        $componente->delete();

        return response()->json([
            'message' => 'Componente eliminado del kit exitosamente.',
        ]);
    }
}
