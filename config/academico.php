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

    /*
    |--------------------------------------------------------------------------
    | Días de cierre del ciclo
    |--------------------------------------------------------------------------
    |
    | Días después de la fecha fin en los que el ciclo sigue abierto para
    | registrar la asistencia de las últimas clases y cerrar notas. Pasado
    | ese plazo sus grupos dejan de aparecer en asistencia y calificaciones.
    |
    */

    'dias_cierre_ciclo' => (int) env('ACADEMICO_DIAS_CIERRE_CICLO', 15),

    /*
    |--------------------------------------------------------------------------
    | Nota aprobatoria
    |--------------------------------------------------------------------------
    |
    | Nota final mínima con la que se aprueba un módulo (escala de 0 a 5).
    | Para graduar una matrícula todos los módulos de su ciclo deben tener
    | la nota completa y alcanzar este valor.
    |
    */

    'nota_aprobatoria' => (float) env('ACADEMICO_NOTA_APROBATORIA', 3.0),

];
