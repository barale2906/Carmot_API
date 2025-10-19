<?php

return [
    'available_kpi_models' => [
        1 => [
            'class' => \App\Models\Academico\Grupo::class,
            'display_name' => 'Grupos por sede',
            'fields' => ['id', 'sede_id', 'inscritos', 'modulo_id', 'profesor_id', 'status', 'created_at', 'updated_at']
        ],
        2 => [
            'class' => \App\Models\Academico\Modulo::class,
            'display_name' => 'Modulos por sede',
            'fields' => ['id', 'sede_id', 'nombre', 'status', 'created_at', 'updated_at']
        ]
    ],
];
