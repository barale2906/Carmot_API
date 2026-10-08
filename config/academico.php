<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Factor mínimo de ajuste de horas
    |--------------------------------------------------------------------------
    |
    | En ciclos con fecha fin fija, el contenido se reduce proporcionalmente
    | para terminar en la fecha indicada. Si la proporción de horas dictadas
    | queda por debajo de este valor, el ciclo registra una advertencia
    | (no se bloquea la operación).
    |
    */

    'factor_ajuste_minimo' => (float) env('ACADEMICO_FACTOR_AJUSTE_MINIMO', 0.8),

];
