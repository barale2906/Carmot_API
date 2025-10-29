<?php

return [
    // Operaciones permitidas por tipo de campo para numerador/denominador
    'allowed_operations' => [
        'numeric' => ['count', 'sum', 'avg', 'max', 'min'],
        'integer' => ['count', 'sum', 'avg', 'max', 'min'],
        'float'   => ['count', 'sum', 'avg', 'max', 'min'],
        'string'  => ['count'],
        'boolean' => ['count'],
        'date'    => ['count'],
        'datetime'=> ['count'],
    ],

    // Presets de periodos disponibles en el sistema
    'period_presets' => [
        'daily', 'weekly', 'monthly', 'quarterly', 'yearly'
    ],

    // Modelos disponibles para KPIs y su metadato necesario
    'available_kpi_models' => [
        1 => [
            'class' => \App\Models\Academico\Grupo::class,
            'display_name' => 'Grupos',
            // Campo sugerido para mostrar como etiqueta
            'display_field' => 'id',
            // Campos de fecha válidos para filtrado temporal
            'date_fields' => ['created_at', 'updated_at'],
            'default_date_field' => 'created_at',
            // Definición de campos con su tipo para validar operaciones
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'sede_id' => ['label' => 'Sede', 'type' => 'integer'],
                'inscritos' => ['label' => 'Inscritos', 'type' => 'integer'],
                'modulo_id' => ['label' => 'Módulo', 'type' => 'integer'],
                'profesor_id' => ['label' => 'Profesor', 'type' => 'integer'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
                'updated_at' => ['label' => 'Fecha de Actualización', 'type' => 'datetime'],
            ],
        ],
        2 => [
            'class' => \App\Models\Academico\Modulo::class,
            'display_name' => 'Módulos',
            'display_field' => 'nombre',
            'date_fields' => ['created_at', 'updated_at'],
            'default_date_field' => 'created_at',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'sede_id' => ['label' => 'Sede', 'type' => 'integer'],
                'nombre' => ['label' => 'Nombre', 'type' => 'string'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
                'updated_at' => ['label' => 'Fecha de Actualización', 'type' => 'datetime'],
            ],
        ],
        3 => [
            'class' => \App\Models\Academico\Ciclo::class,
            'display_name' => 'Ciclos',
            'display_field' => 'nombre',
            'date_fields' => ['fecha_inicio', 'fecha_fin', 'created_at', 'updated_at'],
            'default_date_field' => 'created_at',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'nombre' => ['label' => 'Nombre', 'type' => 'string'],
                'descripcion' => ['label' => 'Descripción', 'type' => 'string'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'sede_id' => ['label' => 'Sede', 'type' => 'integer'],
                'curso_id' => ['label' => 'Curso', 'type' => 'integer'],
                'fecha_inicio' => ['label' => 'Fecha de Inicio', 'type' => 'date'],
                'fecha_fin' => ['label' => 'Fecha de Fin', 'type' => 'date'],
                'duracion_dias' => ['label' => 'Duración (Días)', 'type' => 'integer'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
                'updated_at' => ['label' => 'Fecha de Actualización', 'type' => 'datetime'],
            ],
        ],
    ],
];
