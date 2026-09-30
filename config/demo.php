<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token de la ruta oculta de importación del demo
    |--------------------------------------------------------------------------
    |
    | Secreto que forma parte de la URL /_demo/{token}. Si está vacío o tiene
    | menos de 32 caracteres, la ruta responde 404 (queda deshabilitada).
    | Generar con: php -r "echo bin2hex(random_bytes(24));"
    |
    */
    'import_token' => env('DEMO_IMPORT_TOKEN'),
];
