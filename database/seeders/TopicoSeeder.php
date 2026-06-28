<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder TopicoSeeder
 *
 * Siembra los tópicos reales del instituto y sus relaciones con módulos.
 * Usa insertOrIgnore para ser idempotente.
 */
class TopicoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $topicos = [
            // Módulo 1: MECANICA DE PATIO
            ['id' =>  1, 'nombre' => 'SERVICIO EXPRES',                           'descripcion' => 'SERVICIO EXPRES - ALISTAMIENTO DE VEHÍCULOS',                                                        'duracion' =>  9, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 19:45:21', 'updated_at' => '2026-06-02 20:17:13'],
            ['id' =>  2, 'nombre' => 'SISTEMA DE DIRECCIÓN',                       'descripcion' => 'SISTEMA DE DIRECCIÓN',                                                                               'duracion' =>  6, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 19:50:07', 'updated_at' => '2026-06-02 20:17:30'],
            ['id' =>  3, 'nombre' => 'SISTEMA DE SUSPENCIÓN',                      'descripcion' => 'SISTEMAS DE SUSPENSION, MANTENIMIENTO Y DIAGNOSTICO.',                                               'duracion' => 15, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 20:03:02', 'updated_at' => '2026-06-02 20:19:06'],
            ['id' =>  4, 'nombre' => 'SISTEMA DE FRENOS',                          'descripcion' => 'TIPOS, COMPONENTES, MANTENIMIENTO Y DIAGNOSTICO',                                                    'duracion' => 15, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 20:12:02', 'updated_at' => '2026-06-02 20:12:02'],
            ['id' =>  5, 'nombre' => 'LLANTAS',                                    'descripcion' => 'TIPOS, PATRON DE DIBUJO , DIAGNOSTICO',                                                              'duracion' =>  6, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 20:16:11', 'updated_at' => '2026-06-02 20:16:11'],
            ['id' =>  6, 'nombre' => 'METROLOGÍA MECANICA DE PATIO',               'descripcion' => 'HERRAMIENTA BASICA, HERRAMIENTA DE MEDICIÓN',                                                        'duracion' =>  6, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 20:43:55', 'updated_at' => '2026-06-02 20:43:55'],
            ['id' =>  7, 'nombre' => 'CONOCIMIENTOS GENERALES DE AUTOMÓVILES',     'descripcion' => 'CONOCIMIENTOS GENERALES DE AUTOMÓVILES',                                                             'duracion' =>  2, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 20:45:30', 'updated_at' => '2026-06-02 20:45:30'],
            ['id' =>  8, 'nombre' => 'SEGURIDAD INDUSTRIAL DEL TALLER',            'descripcion' => 'SEGURIDAD INDUSTRIAL DEL TALLER',                                                                    'duracion' =>  1, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-02 20:46:30', 'updated_at' => '2026-06-02 20:46:30'],
            // Módulo 2: MOTORES, TRANSMISIÓN Y EMBRAGUE
            ['id' =>  9, 'nombre' => 'SEGURIDAD INDUSTRIAL DEL TALLER MOTORES',    'descripcion' => 'SEGURIDAD INDUSTRIAL DEL TALLER MOTORES',                                                            'duracion' =>  1, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 13:46:05', 'updated_at' => '2026-06-09 13:46:05'],
            ['id' => 10, 'nombre' => 'HERRAMIENTA DE METROLOGÍA',                  'descripcion' => 'HERRAMIENTA DE METROLOGÍA',                                                                          'duracion' =>  8, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 13:49:18', 'updated_at' => '2026-06-09 13:49:18'],
            ['id' => 11, 'nombre' => 'DESPIECE Y RECONOCIMIENTO DE MOTOR',         'descripcion' => 'DESPIECE Y RECONOCIMIENTO DE MOTOR',                                                                  'duracion' =>  9, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 14:17:11', 'updated_at' => '2026-06-09 14:17:11'],
            ['id' => 12, 'nombre' => 'FUNDAMENTOS DEL MOTOR',                      'descripcion' => 'FUNDAMENTOS DEL MOTOR',                                                                              'duracion' =>  6, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 14:19:48', 'updated_at' => '2026-06-09 14:19:48'],
            ['id' => 13, 'nombre' => 'MOTORES DE GASOLINA',                        'descripcion' => 'MOTORES DE GASOLINA',                                                                                'duracion' => 18, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 14:24:42', 'updated_at' => '2026-06-09 14:25:39'],
            ['id' => 14, 'nombre' => 'MOTORES DIESEL',                             'descripcion' => 'MOTORES DIESEL',                                                                                     'duracion' =>  7, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 14:38:10', 'updated_at' => '2026-06-09 14:38:10'],
            ['id' => 15, 'nombre' => 'DIAGNOSTICO DE MOTORES DE COMBUSTION INTERNA','descripcion' => 'DIAGNOSTICO DE MOTORES DE COMBUSTION INTERNA',                                                     'duracion' =>  5, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:29:41', 'updated_at' => '2026-06-09 15:29:41'],
            ['id' => 16, 'nombre' => 'TRANSMISION Y EMBRAGUE',                     'descripcion' => 'TRANSMISIÓN Y EMBRAGUE',                                                                             'duracion' =>  6, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:32:30', 'updated_at' => '2026-06-09 15:32:30'],
            ['id' => 17, 'nombre' => 'TRANSMISION DE POTENCIA',                    'descripcion' => 'TRANSMISION DE POTENCIA',                                                                            'duracion' => 12, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:37:56', 'updated_at' => '2026-06-09 15:37:56'],
            // Módulo 3: ELECTRICIDAD
            ['id' => 18, 'nombre' => 'SEGURIDAD INDUSTRIAL DEL TALLER - ELECTRICIDAD', 'descripcion' => 'SEGURIDAD INDUSTRIAL DEL TALLER - ELECTRICIDAD',                                                'duracion' =>  3, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:45:11', 'updated_at' => '2026-06-09 15:45:11'],
            ['id' => 19, 'nombre' => 'METROLOGIA ELECTRICIDAD',                    'descripcion' => 'METROLOGIA ELECTRICIDAD',                                                                            'duracion' =>  9, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:47:33', 'updated_at' => '2026-06-09 15:47:33'],
            ['id' => 20, 'nombre' => 'FUNDAMENTOS DE LA ELECTRICIDAD',             'descripcion' => 'FUNDAMENTOS DE LA ELECTRICIDAD',                                                                     'duracion' => 24, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:52:54', 'updated_at' => '2026-06-09 15:52:54'],
            ['id' => 21, 'nombre' => 'SISTEMAS ELECTRICOS',                        'descripcion' => 'SISTEMAS ELECTRICOS',                                                                                'duracion' => 24, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 15:56:46', 'updated_at' => '2026-06-09 15:56:46'],
            // Módulo 4: INYECCIÓN ELECTRONICA
            ['id' => 22, 'nombre' => 'SEGURIDAD INDUSTRIAL EN EL TALLER INYECCIÓN', 'descripcion' => 'SEGURIDAD INDUSTRIAL EN EL TALLER INYECCIÓN',                                                      'duracion' =>  1, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 16:41:55', 'updated_at' => '2026-06-09 16:41:55'],
            ['id' => 23, 'nombre' => 'BASES DE INYECCIÓN ELECTRONICA',             'descripcion' => 'BASES DE INYECCIÓN ELECTRONICA',                                                                     'duracion' =>  5, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 16:44:57', 'updated_at' => '2026-06-09 16:44:57'],
            ['id' => 24, 'nombre' => 'CLASIFICACION DEL SISTEMA DE INYECCION',     'descripcion' => 'CLASIFICACION DEL SISTEMA DE INYECCION',                                                            'duracion' =>  3, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 16:48:10', 'updated_at' => '2026-06-09 16:48:10'],
            ['id' => 25, 'nombre' => 'SISTEMA DE COMBUSTIBLE -ESTUDIO DE ECU',     'descripcion' => 'SISTEMA DE COMBUSTIBLE -ESTUDIO DE ECU',                                                            'duracion' =>  8, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 16:50:55', 'updated_at' => '2026-06-09 16:50:55'],
            ['id' => 26, 'nombre' => 'SISTEMA DE ENCENDIDO, CONTROL DE EMISIONES Y SENSORES', 'descripcion' => 'SISTEMA DE ENCENDIDO, CONTROL DE EMISIONES Y SENSORES',                                  'duracion' => 16, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 21:20:20', 'updated_at' => '2026-06-09 21:42:24'],
            ['id' => 27, 'nombre' => 'MANUALES, SISTEMAS DRIVE BY WIRE, PROTOCOLOS E INSTRUMENTOS DE DIAGNOSTICO', 'descripcion' => 'MANUALES, SISTEMAS DRIVE BY WIRE, PROTOCOLOS E INSTRUMENTOS DE DIAGNOSTICO', 'duracion' => 15, 'status' => 1, 'deleted_at' => null, 'created_at' => '2026-06-09 21:30:59', 'updated_at' => '2026-06-09 21:30:59'],
        ];

        DB::table('topicos')->insertOrIgnore($topicos);

        // Relación tópico → módulo
        $topicoModulo = [
            // Módulo 1: MECANICA DE PATIO (tópicos 1-8)
            ['id' =>  1, 'topico_id' =>  1, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  2, 'topico_id' =>  2, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  3, 'topico_id' =>  3, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  4, 'topico_id' =>  4, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  5, 'topico_id' =>  5, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  6, 'topico_id' =>  6, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  7, 'topico_id' =>  7, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            ['id' =>  8, 'topico_id' =>  8, 'modulo_id' => 1, 'created_at' => '2026-06-02 20:47:40', 'updated_at' => '2026-06-02 20:47:40'],
            // Módulo 2: MOTORES (tópicos 9-17)
            ['id' =>  9, 'topico_id' =>  9, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 10, 'topico_id' => 10, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 11, 'topico_id' => 11, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 12, 'topico_id' => 12, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 13, 'topico_id' => 13, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 14, 'topico_id' => 14, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 15, 'topico_id' => 15, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 16, 'topico_id' => 16, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 17, 'topico_id' => 17, 'modulo_id' => 2, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            // Módulo 3: ELECTRICIDAD (tópicos 18-21)
            ['id' => 18, 'topico_id' => 18, 'modulo_id' => 3, 'created_at' => '2026-06-09 15:58:09', 'updated_at' => '2026-06-09 15:58:09'],
            ['id' => 19, 'topico_id' => 19, 'modulo_id' => 3, 'created_at' => '2026-06-09 15:58:09', 'updated_at' => '2026-06-09 15:58:09'],
            ['id' => 20, 'topico_id' => 20, 'modulo_id' => 3, 'created_at' => '2026-06-09 15:58:09', 'updated_at' => '2026-06-09 15:58:09'],
            ['id' => 21, 'topico_id' => 21, 'modulo_id' => 3, 'created_at' => '2026-06-09 15:58:09', 'updated_at' => '2026-06-09 15:58:09'],
            // Módulo 4: INYECCIÓN (tópicos 22-27)
            ['id' => 22, 'topico_id' => 22, 'modulo_id' => 4, 'created_at' => '2026-06-09 21:32:24', 'updated_at' => '2026-06-09 21:32:24'],
            ['id' => 23, 'topico_id' => 23, 'modulo_id' => 4, 'created_at' => '2026-06-09 21:32:24', 'updated_at' => '2026-06-09 21:32:24'],
            ['id' => 24, 'topico_id' => 24, 'modulo_id' => 4, 'created_at' => '2026-06-09 21:32:24', 'updated_at' => '2026-06-09 21:32:24'],
            ['id' => 25, 'topico_id' => 26, 'modulo_id' => 4, 'created_at' => '2026-06-09 21:32:24', 'updated_at' => '2026-06-09 21:32:24'],
            ['id' => 26, 'topico_id' => 27, 'modulo_id' => 4, 'created_at' => '2026-06-09 21:32:24', 'updated_at' => '2026-06-09 21:32:24'],
            ['id' => 27, 'topico_id' => 25, 'modulo_id' => 4, 'created_at' => '2026-06-09 21:34:44', 'updated_at' => '2026-06-09 21:34:44'],
        ];

        DB::table('topico_modulo')->insertOrIgnore($topicoModulo);

        $this->command->info('TopicoSeeder: ' . count($topicos) . ' tópico(s) y ' . count($topicoModulo) . ' relación(es) tópico-módulo procesadas.');
    }
}
