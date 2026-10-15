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

    // Modelos disponibles para KPIs y su metadato necesario.
    // `excluded_status`: estados que no cuentan por defecto (anulados, pendientes, reestructurados).
    // Se ignora si el KPI filtra por `status` explícitamente (N27).
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

        // Modelos de operación más pedidos en los tableros (B41)
        4 => [
            'class' => \App\Models\Academico\Matricula::class,
            'display_name' => 'Matrículas',
            // Anulada
            'excluded_status' => [2],
            'display_field' => 'id',
            'date_fields' => ['fecha_matricula', 'fecha_inicio', 'created_at'],
            'default_date_field' => 'fecha_matricula',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'sede_id' => ['label' => 'Sede', 'type' => 'integer'],
                'curso_id' => ['label' => 'Curso', 'type' => 'integer'],
                'ciclo_id' => ['label' => 'Ciclo', 'type' => 'integer'],
                'comercial_id' => ['label' => 'Comercial', 'type' => 'integer'],
                'monto' => ['label' => 'Monto', 'type' => 'numeric'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'estado_academico' => ['label' => 'Estado académico', 'type' => 'integer'],
                'fecha_matricula' => ['label' => 'Fecha de matrícula', 'type' => 'date'],
                'fecha_inicio' => ['label' => 'Fecha de inicio', 'type' => 'date'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
            ],
        ],
        5 => [
            'class' => \App\Models\Financiero\ReciboPago\ReciboPago::class,
            'display_name' => 'Recibos de pago',
            // Anulado, transferencia pendiente de aprobación y rechazada: no son recaudo
            'excluded_status' => [
                \App\Models\Financiero\ReciboPago\ReciboPago::STATUS_ANULADO,
                \App\Models\Financiero\ReciboPago\ReciboPago::STATUS_PENDIENTE_APROBACION,
                \App\Models\Financiero\ReciboPago\ReciboPago::STATUS_RECHAZADO,
            ],
            'display_field' => 'numero_recibo',
            'date_fields' => ['fecha_recibo', 'fecha_transaccion', 'created_at'],
            'default_date_field' => 'fecha_recibo',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'sede_id' => ['label' => 'Sede', 'type' => 'integer'],
                'origen' => ['label' => 'Origen', 'type' => 'integer'],
                'cajero_id' => ['label' => 'Cajero', 'type' => 'integer'],
                'valor_total' => ['label' => 'Valor total', 'type' => 'numeric'],
                'descuento_total' => ['label' => 'Descuento total', 'type' => 'numeric'],
                'sobrecargo_total' => ['label' => 'Recargos', 'type' => 'numeric'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'fecha_recibo' => ['label' => 'Fecha del recibo', 'type' => 'date'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
            ],
        ],
        6 => [
            'class' => \App\Models\Financiero\Cartera\Cartera::class,
            'display_name' => 'Cartera',
            // Anulada y En Acuerdo (su saldo pasó a las cuotas del acuerdo)
            'excluded_status' => [3, 4],
            'display_field' => 'id',
            'date_fields' => ['fecha_vencimiento', 'created_at'],
            'default_date_field' => 'fecha_vencimiento',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'sede_id' => ['label' => 'Sede', 'type' => 'integer'],
                'matricula_id' => ['label' => 'Matrícula', 'type' => 'integer'],
                'valor' => ['label' => 'Valor', 'type' => 'numeric'],
                'abono' => ['label' => 'Abonado', 'type' => 'numeric'],
                'descuento' => ['label' => 'Descuento', 'type' => 'numeric'],
                'saldo' => ['label' => 'Saldo', 'type' => 'numeric'],
                'mora_acumulada' => ['label' => 'Mora acumulada', 'type' => 'numeric'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'fecha_vencimiento' => ['label' => 'Vencimiento', 'type' => 'date'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
            ],
        ],
        7 => [
            'class' => \App\Models\Academico\Asistencia::class,
            'display_name' => 'Asistencias',
            'display_field' => 'id',
            'date_fields' => ['fecha_registro', 'created_at'],
            'default_date_field' => 'fecha_registro',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'estudiante_id' => ['label' => 'Estudiante', 'type' => 'integer'],
                'grupo_id' => ['label' => 'Grupo', 'type' => 'integer'],
                'ciclo_id' => ['label' => 'Ciclo', 'type' => 'integer'],
                'curso_id' => ['label' => 'Curso', 'type' => 'integer'],
                'modulo_id' => ['label' => 'Módulo', 'type' => 'integer'],
                'estado' => ['label' => 'Estado', 'type' => 'string'],
                'fecha_registro' => ['label' => 'Fecha de registro', 'type' => 'datetime'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
            ],
        ],
        8 => [
            'class' => \App\Models\Academico\NotaEstudiante::class,
            'display_name' => 'Notas',
            'display_field' => 'id',
            'date_fields' => ['fecha_registro', 'created_at'],
            'default_date_field' => 'fecha_registro',
            'fields' => [
                'id' => ['label' => 'ID', 'type' => 'integer'],
                'estudiante_id' => ['label' => 'Estudiante', 'type' => 'integer'],
                'grupo_id' => ['label' => 'Grupo', 'type' => 'integer'],
                'modulo_id' => ['label' => 'Módulo', 'type' => 'integer'],
                'nota' => ['label' => 'Nota', 'type' => 'numeric'],
                'nota_ponderada' => ['label' => 'Nota ponderada', 'type' => 'numeric'],
                'status' => ['label' => 'Estado', 'type' => 'integer'],
                'fecha_registro' => ['label' => 'Fecha de registro', 'type' => 'date'],
                'created_at' => ['label' => 'Fecha de Creación', 'type' => 'datetime'],
            ],
        ],
    ],
];
