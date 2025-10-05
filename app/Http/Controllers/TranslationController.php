<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

class TranslationController extends Controller
{
    /**
     * Cambiar el idioma de la aplicación
     */
    public function setLocale(Request $request)
    {
        $locale = $request->input('locale', 'es');

        if (in_array($locale, ['es', 'en'])) {
            App::setLocale($locale);

            return response()->json([
                'message' => 'Idioma cambiado exitosamente',
                'locale' => $locale,
                'success' => true
            ]);
        }

        return response()->json([
            'message' => __('api.error.validation_failed'),
            'errors' => ['locale' => 'Idioma no soportado'],
            'success' => false
        ], 400);
    }

    /**
     * Obtener mensajes de la API en el idioma actual
     */
    public function getMessages()
    {
        return response()->json([
            'success' => [
                'created' => __('api.success.created'),
                'updated' => __('api.success.updated'),
                'deleted' => __('api.success.deleted'),
                'retrieved' => __('api.success.retrieved'),
            ],
            'error' => [
                'not_found' => __('api.error.not_found'),
                'unauthorized' => __('api.error.unauthorized'),
                'forbidden' => __('api.error.forbidden'),
                'validation_failed' => __('api.error.validation_failed'),
                'server_error' => __('api.error.server_error'),
            ],
            'user' => [
                'registered' => __('api.user.registered'),
                'logged_in' => __('api.user.logged_in'),
                'logged_out' => __('api.user.logged_out'),
                'profile_updated' => __('api.user.profile_updated'),
            ],
            'locale' => App::getLocale()
        ]);
    }

    /**
     * Ejemplo de uso de traducciones en validaciones
     */
    public function validateExample(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        return response()->json([
            'message' => __('api.success.created'),
            'data' => $request->only(['name', 'email']),
            'success' => true
        ]);
    }

    /**
     * Obtener información de zona horaria
     */
    public function getTimezoneInfo()
    {
        return response()->json([
            'timezone' => config('app.timezone'),
            'current_time' => now()->format('Y-m-d H:i:s T'),
            'current_timestamp' => now()->timestamp,
            'locale' => App::getLocale(),
            'formatted_date' => now()->format('d/m/Y H:i:s'),
            'day_name' => now()->locale('es')->dayName,
            'month_name' => now()->locale('es')->monthName,
        ]);
    }
}
