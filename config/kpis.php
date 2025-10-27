<?php

return [
    'available_kpi_models' => [
        1 => [
            'class' => \App\Models\Academico\Grupo::class,
            'display_name' => 'Grupos',
            'fields' => [
                'id' => 'ID',
                'sede_id' => 'Sede',
                'inscritos' => 'Inscritos',
                'modulo_id' => 'Módulo',
                'profesor_id' => 'Profesor',
                'status' => 'Estado',
                'created_at' => 'Fecha de Creación',
                'updated_at' => 'Fecha de Actualización'
            ]
        ],
        2 => [
            'class' => \App\Models\Academico\Modulo::class,
            'display_name' => 'Módulos',
            'fields' => [
                'id' => 'ID',
                'sede_id' => 'Sede',
                'nombre' => 'Nombre',
                'status' => 'Estado',
                'created_at' => 'Fecha de Creación',
                'updated_at' => 'Fecha de Actualización'
            ]
        ],
        3 => [
            'class' => \App\Models\Academico\Ciclo::class,
            'display_name' => 'Ciclos',
            'fields' => [
                'id' => 'ID',
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'status' => 'Estado',
                'sede_id' => 'Sede',
                'curso_id' => 'Curso',
                'fecha_inicio' => 'Fecha de Inicio',
                'fecha_fin' => 'Fecha de Fin',
                'duracion_dias' => 'Duración (Días)',
                'created_at' => 'Fecha de Creación',
                'updated_at' => 'Fecha de Actualización'
            ]
        ]
    ],
];
