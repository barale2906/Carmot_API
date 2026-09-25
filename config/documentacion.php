<?php

use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\User;
use App\Services\Academico\Documentacion\Bloques\DocBloqueEstadoCartera;
use App\Services\Academico\Documentacion\Bloques\DocBloqueRecibosPago;
use App\Services\Academico\Documentacion\Bloques\DocBloqueSabanaNotas;

return [

    /*
    |--------------------------------------------------------------------------
    | Datos del instituto
    |--------------------------------------------------------------------------
    |
    | Valores institucionales disponibles como variables globales en cualquier
    | documento (encabezados, pies de página, cláusulas legales).
    |
    */

    'instituto' => [
        'nombre'    => env('INSTITUTO_NOMBRE', env('APP_NAME', 'Instituto')),
        'nit'       => env('INSTITUTO_NIT', ''),
        'direccion' => env('INSTITUTO_DIRECCION', ''),
        'telefono'  => env('INSTITUTO_TELEFONO', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Entidades asociables a un tipo de documento
    |--------------------------------------------------------------------------
    |
    | Clases Eloquent que pueden ser el origen de datos de un documento.
    | `campos_fecha` lista los atributos válidos para `campo_fecha_referencia`
    | cuando el tipo de documento se ata a una fecha (contratos, pagarés).
    |
    */

    'entidades' => [
        Matricula::class => [
            'nombre'       => 'Matrícula',
            'campos_fecha' => ['fecha_matricula', 'fecha_inicio', 'created_at'],
        ],
        Cartera::class => [
            'nombre'       => 'Cuota de cartera',
            'campos_fecha' => ['matricula.fecha_matricula', 'fecha_vencimiento', 'created_at'],
        ],
        User::class => [
            'nombre'       => 'Estudiante',
            'campos_fecha' => ['created_at'],
        ],
        Curso::class => [
            'nombre'       => 'Curso',
            'campos_fecha' => ['created_at'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de variables por entidad
    |--------------------------------------------------------------------------
    |
    | Cada clave es la ruta de resolución sobre la entidad (soporta relaciones
    | y accessors con notación de punto). `type` define el formato aplicado al
    | valor. `origen` permite que una variable calculada (p. ej. un valor en
    | letras) lea su dato de otra ruta.
    |
    | Tipos soportados: string, integer, decimal, money, money_letras, date,
    | date_larga, datetime, boolean.
    |
    | Para agregar una variable nueva basta con registrarla aquí; queda
    | disponible para habilitarla en los tipos de documento que la necesiten.
    |
    */

    'variables' => [

        Matricula::class => [
            'numero_matricula'           => ['label' => 'Número de matrícula', 'type' => 'integer', 'origen' => 'id'],
            'estudiante.name'            => ['label' => 'Nombre completo del estudiante', 'type' => 'string'],
            'estudiante.primer_nombre'   => ['label' => 'Primer nombre del estudiante', 'type' => 'string'],
            'estudiante.primer_apellido' => ['label' => 'Primer apellido del estudiante', 'type' => 'string'],
            'estudiante.documento'       => ['label' => 'Documento del estudiante', 'type' => 'string'],
            'estudiante.email'           => ['label' => 'Correo del estudiante', 'type' => 'string'],
            'tipo_identificacion_texto'  => ['label' => 'Tipo de identificación', 'type' => 'string'],
            'fecha_nacimiento'           => ['label' => 'Fecha de nacimiento', 'type' => 'date'],
            'direccion'                  => ['label' => 'Dirección del estudiante', 'type' => 'string'],
            'celular'                    => ['label' => 'Celular del estudiante', 'type' => 'string'],
            'telefono'                   => ['label' => 'Teléfono del estudiante', 'type' => 'string'],

            'fecha_matricula'            => ['label' => 'Fecha de matrícula', 'type' => 'date'],
            'fecha_matricula_larga'      => ['label' => 'Fecha de matrícula (en texto)', 'type' => 'date_larga', 'origen' => 'fecha_matricula'],
            'fecha_inicio'               => ['label' => 'Fecha de inicio de clases', 'type' => 'date'],

            'monto'                      => ['label' => 'Valor total de la matrícula', 'type' => 'money'],
            'monto_letras'               => ['label' => 'Valor total en letras', 'type' => 'money_letras', 'origen' => 'monto'],
            'valor_cuota'                => ['label' => 'Valor de la cuota', 'type' => 'money'],
            'valor_cuota_letras'         => ['label' => 'Valor de la cuota en letras', 'type' => 'money_letras', 'origen' => 'valor_cuota'],
            'numero_cuotas'              => ['label' => 'Número de cuotas', 'type' => 'integer'],
            'observaciones'              => ['label' => 'Observaciones de la matrícula', 'type' => 'string'],

            'curso.nombre'               => ['label' => 'Nombre del curso', 'type' => 'string'],
            'curso.duracion'             => ['label' => 'Duración del curso (horas)', 'type' => 'decimal'],
            'ciclo.nombre'               => ['label' => 'Ciclo', 'type' => 'string'],
            'sede.nombre'                => ['label' => 'Sede', 'type' => 'string'],
            'sede.direccion'             => ['label' => 'Dirección de la sede', 'type' => 'string'],
            'sede.telefono'              => ['label' => 'Teléfono de la sede', 'type' => 'string'],
        ],

        Cartera::class => [
            'numero_matricula'               => ['label' => 'Número de matrícula', 'type' => 'integer', 'origen' => 'matricula.id'],
            'numero_cuota'                   => ['label' => 'Número de cuota', 'type' => 'integer'],
            'valor'                          => ['label' => 'Valor de la cuota', 'type' => 'money'],
            'valor_letras'                   => ['label' => 'Valor de la cuota en letras', 'type' => 'money_letras', 'origen' => 'valor'],
            'saldo'                          => ['label' => 'Saldo pendiente', 'type' => 'money'],
            'fecha_vencimiento'              => ['label' => 'Fecha de vencimiento', 'type' => 'date'],
            'fecha_vencimiento_larga'        => ['label' => 'Fecha de vencimiento (en texto)', 'type' => 'date_larga', 'origen' => 'fecha_vencimiento'],
            'matricula.estudiante.name'      => ['label' => 'Nombre completo del estudiante', 'type' => 'string'],
            'matricula.estudiante.documento' => ['label' => 'Documento del estudiante', 'type' => 'string'],
            'matricula.curso.nombre'         => ['label' => 'Curso', 'type' => 'string'],
            'matricula.monto'                => ['label' => 'Valor total de la matrícula', 'type' => 'money'],
            'sede.nombre'                    => ['label' => 'Sede', 'type' => 'string'],
        ],

        User::class => [
            'name'            => ['label' => 'Nombre completo', 'type' => 'string'],
            'primer_nombre'   => ['label' => 'Primer nombre', 'type' => 'string'],
            'segundo_nombre'  => ['label' => 'Segundo nombre', 'type' => 'string'],
            'primer_apellido' => ['label' => 'Primer apellido', 'type' => 'string'],
            'segundo_apellido' => ['label' => 'Segundo apellido', 'type' => 'string'],
            'documento'       => ['label' => 'Número de documento', 'type' => 'string'],
            'email'           => ['label' => 'Correo electrónico', 'type' => 'string'],
        ],

        Curso::class => [
            'nombre'   => ['label' => 'Nombre del curso', 'type' => 'string'],
            'duracion' => ['label' => 'Duración (horas)', 'type' => 'decimal'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de bloques por entidad
    |--------------------------------------------------------------------------
    |
    | Un bloque es una consulta que se imprime como tabla dentro del documento
    | (`{{ bloque.clave }}`). Cada clase declara las columnas que sabe traer;
    | cuáles se imprimen y en qué orden lo define el administrador en cada
    | versión de la plantilla (tabla doc_plantilla_bloques).
    |
    | Para agregar un bloque nuevo: crear la clase que implemente
    | DocBloqueContract y registrarla aquí bajo la entidad que corresponda.
    |
    */

    'bloques' => [
        Matricula::class => [
            'sabana_notas' => [
                'label'       => 'Sábana de notas',
                'descripcion' => 'Una fila por módulo del ciclo, con su nota final.',
                'clase'       => DocBloqueSabanaNotas::class,
            ],
            'estado_cartera' => [
                'label'       => 'Estado de cartera',
                'descripcion' => 'Una fila por cuota, con valor, abono, saldo y vencimiento.',
                'clase'       => DocBloqueEstadoCartera::class,
            ],
            'recibos_pago' => [
                'label'       => 'Listado de recibos de pago',
                'descripcion' => 'Una fila por recibo asociado a la matrícula.',
                'clase'       => DocBloqueRecibosPago::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables globales
    |--------------------------------------------------------------------------
    |
    | Disponibles en todos los tipos de documento, sin importar su entidad.
    | Se resuelven desde el contexto de generación, no desde la entidad.
    |
    */

    'variables_globales' => [
        'documento.fecha'        => ['label' => 'Fecha de generación', 'type' => 'date'],
        'documento.fecha_larga'  => ['label' => 'Fecha de generación (en texto)', 'type' => 'date_larga'],
        'documento.tipo'         => ['label' => 'Tipo de documento', 'type' => 'string'],
        'usuario.nombre'         => ['label' => 'Usuario que genera el documento', 'type' => 'string'],
        'instituto.nombre'       => ['label' => 'Nombre del instituto', 'type' => 'string'],
        'instituto.nit'          => ['label' => 'NIT del instituto', 'type' => 'string'],
        'instituto.direccion'    => ['label' => 'Dirección del instituto', 'type' => 'string'],
        'instituto.telefono'     => ['label' => 'Teléfono del instituto', 'type' => 'string'],
    ],
];
