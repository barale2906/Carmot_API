<?php

namespace App\Http\Resources\Api\Academico;

/**
 * Esquema de documentación para CicloResource
 * Este archivo ayuda a Scramble a entender la estructura de la respuesta
 */
class CicloResourceSchema
{
    /**
     * Obtiene el esquema de la respuesta del CicloResource
     *
     * @return array<string, mixed>
     */
    public static function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'nombre' => ['type' => 'string'],
                'descripcion' => ['type' => 'string', 'nullable' => true],
                'fecha_inicio' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                'fecha_fin' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                'fecha_fin_automatica' => ['type' => 'boolean'],
                'duracion_dias' => ['type' => 'integer', 'nullable' => true],
                'duracion_estimada' => ['type' => 'integer', 'nullable' => true],
                'total_horas' => ['type' => 'integer', 'nullable' => true],
                'horas_por_semana' => ['type' => 'integer', 'nullable' => true],
                'en_curso' => ['type' => 'boolean'],
                'finalizado' => ['type' => 'boolean'],
                'por_iniciar' => ['type' => 'boolean'],
                'status' => ['type' => 'integer'],
                'status_text' => ['type' => 'string'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                'deleted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'sede' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'nombre' => ['type' => 'string'],
                        'direccion' => ['type' => 'string'],
                        'telefono' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'hora_inicio' => ['type' => 'string', 'format' => 'time'],
                        'hora_fin' => ['type' => 'string', 'format' => 'time'],
                        'status' => ['type' => 'integer'],
                        'status_text' => ['type' => 'string'],
                    ]
                ],
                'curso' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'nombre' => ['type' => 'string'],
                        'duracion' => ['type' => 'integer'],
                        'status' => ['type' => 'integer'],
                        'status_text' => ['type' => 'string'],
                    ]
                ],
                'grupos' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                            'inscritos' => ['type' => 'integer'],
                            'jornada' => ['type' => 'integer'],
                            'jornada_nombre' => ['type' => 'string'],
                            'status' => ['type' => 'integer'],
                            'status_text' => ['type' => 'string'],
                            'orden' => ['type' => 'integer', 'nullable' => true],
                            'fecha_inicio_grupo' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                            'fecha_fin_grupo' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                            'modulo' => [
                                'type' => 'object',
                                'nullable' => true,
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'nombre' => ['type' => 'string'],
                                    'duracion' => ['type' => 'integer'],
                                ]
                            ],
                            'profesor' => [
                                'type' => 'object',
                                'nullable' => true,
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'name' => ['type' => 'string'],
                                    'email' => ['type' => 'string'],
                                ]
                            ],
                            'horarios' => [
                                'type' => 'array',
                                'nullable' => true,
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'dia' => ['type' => 'string'],
                                        'hora' => ['type' => 'string', 'format' => 'time'],
                                        'duracion_horas' => ['type' => 'integer'],
                                    ]
                                ]
                            ],
                        ]
                    ]
                ],
                'sede_count' => ['type' => 'integer', 'nullable' => true],
                'curso_count' => ['type' => 'integer', 'nullable' => true],
                'grupos_count' => ['type' => 'integer', 'nullable' => true],
            ]
        ];
    }
}
