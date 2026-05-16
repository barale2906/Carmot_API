<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación en el contenedor IoC.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Inicializa los servicios de la aplicación después de registrarlos.
     *
     * Nota sobre el módulo LP y relaciones polimórficas:
     * La tabla lp_producto_referencias almacena 'curso' y 'modulo' en la columna
     * referencia_tipo, pero NO usa el sistema de morphs de Eloquent (MorphTo/MorphToMany).
     * La resolución de la entidad académica se realiza mediante BelongsToMany con
     * wherePivot() y el accessor LpProductoReferencia::getReferenciaModelAttribute(),
     * por lo que no se requiere registrar ningún morph map aquí.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
