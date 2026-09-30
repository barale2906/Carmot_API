<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerificarTokenDemo
 *
 * Protege la ruta oculta de importación del demo: compara el segmento {token} de la
 * URL con config('demo.import_token') en tiempo constante. Ante cualquier fallo
 * (token no configurado, demasiado corto o distinto) responde 404 para que la ruta
 * no revele su existencia.
 */
class VerificarTokenDemo
{
    /** Longitud mínima exigida al token configurado. */
    private const LONGITUD_MINIMA = 32;

    /**
     * Maneja la petición entrante.
     *
     * @param  Request $request
     * @param  Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('demo.import_token');
        $recibido = (string) $request->route('token');

        abort_if(
            strlen($esperado) < self::LONGITUD_MINIMA || !hash_equals($esperado, $recibido),
            Response::HTTP_NOT_FOUND
        );

        return $next($request);
    }
}
