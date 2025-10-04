<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            foreach (glob(base_path('routes/*.php')) as $routeFile) {
                $filename = basename($routeFile);

                // Saltar los archivos por defecto
                if (in_array($filename, ['api.php', 'web.php'])) {
                    continue;
                }

                // Prefijo basado en el nombre del archivo (sin extensión)
                $prefix = 'api/' . pathinfo($filename, PATHINFO_FILENAME);

                Route::middleware('api')
                    ->prefix($prefix)
                    ->group($routeFile);
            }/*

            Route::middleware('api')
                ->prefix('api/configuracion')
                //->name('configuracion.')
                ->group(base_path('routes/configuracion.php')); */

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

        });
    }
}
