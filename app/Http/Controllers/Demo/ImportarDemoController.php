<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demo\ImportarDemoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * ImportarDemoController
 *
 * Ruta oculta (sin autenticación, protegida por token en la URL — ver
 * VerificarTokenDemo) para poblar una base de datos VACÍA con un demo.sql generado
 * con `php artisan demo:exportar`. Delega todo el trabajo en los comandos
 * demo:importar (con --solo-vacia) y migrate.
 */
class ImportarDemoController extends Controller
{
    /**
     * Muestra el formulario de carga del archivo demo.sql.
     *
     * @param  string $token Token secreto de la URL
     * @return View
     */
    public function create(string $token): View
    {
        return view('demo.importar', ['token' => $token]);
    }

    /**
     * Recibe el demo.sql, lo importa sobre la base vacía y ejecuta las migraciones
     * pendientes. Responde JSON si el cliente lo solicita (curl/Postman) o la vista
     * con el resultado en caso contrario.
     *
     * @param  ImportarDemoRequest $request
     * @param  string              $token   Token secreto de la URL
     * @return View|Response|JsonResponse
     */
    public function store(ImportarDemoRequest $request, string $token): View|Response|JsonResponse
    {
        set_time_limit(0);

        $directorio = storage_path('app/demo');
        $nombre = 'importacion_' . now()->format('Ymd_His') . '.sql';
        $request->file('archivo')->move($directorio, $nombre);
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;

        try {
            $codigo = Artisan::call('demo:importar', ['--file' => $ruta, '--solo-vacia' => true, '--force' => true]);
            $salida = Artisan::output();

            if ($codigo === 0) {
                Artisan::call('migrate', ['--force' => true]);
                $salida .= Artisan::output();
            }
        } finally {
            @unlink($ruta);
        }

        $exito = $codigo === 0;
        $mensaje = $exito
            ? 'Base de datos poblada correctamente con el demo.'
            : 'No se realizó la importación.';
        $estado = $exito ? 200 : 409;

        if ($request->expectsJson()) {
            return response()->json(['data' => ['salida' => trim($salida)], 'message' => $mensaje], $estado);
        }

        return response()->view('demo.importar', [
            'token'   => $token,
            'exito'   => $exito,
            'mensaje' => $mensaje,
            'salida'  => trim($salida),
        ], $estado);
    }
}
