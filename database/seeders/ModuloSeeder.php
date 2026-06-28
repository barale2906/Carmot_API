<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder ModuloSeeder
 *
 * Siembra los módulos reales del instituto y sus relaciones con cursos.
 * Usa insertOrIgnore para ser idempotente.
 */
class ModuloSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $modulos = [
            [
                'id'         => 1,
                'nombre'     => 'MECANICA DE PATIO',
                'duracion'   => 60,
                'status'     => 1,
                'deleted_at' => null,
                'created_at' => '2026-06-02 20:47:40',
                'updated_at' => '2026-06-02 20:47:40',
            ],
            [
                'id'         => 2,
                'nombre'     => 'MOTORES, TRANSMISIÓN Y EMBRAGUE',
                'duracion'   => 72,
                'status'     => 1,
                'deleted_at' => null,
                'created_at' => '2026-06-09 15:39:47',
                'updated_at' => '2026-06-09 15:39:47',
            ],
            [
                'id'         => 3,
                'nombre'     => 'ELECTRICIDAD',
                'duracion'   => 60,
                'status'     => 1,
                'deleted_at' => null,
                'created_at' => '2026-06-09 15:58:09',
                'updated_at' => '2026-06-09 15:58:09',
            ],
            [
                'id'         => 4,
                'nombre'     => 'INYECCIÓN ELECTRONICA',
                'duracion'   => 48,
                'status'     => 1,
                'deleted_at' => null,
                'created_at' => '2026-06-09 21:32:24',
                'updated_at' => '2026-06-09 21:46:15',
            ],
        ];

        DB::table('modulos')->insertOrIgnore($modulos);

        // Relación módulo → curso (todos pertenecen al curso 1)
        $moduloCurso = [
            ['id' => 1, 'modulo_id' => 1, 'curso_id' => 1, 'created_at' => '2026-06-02 20:49:00', 'updated_at' => '2026-06-02 20:49:00'],
            ['id' => 2, 'modulo_id' => 2, 'curso_id' => 1, 'created_at' => '2026-06-09 15:39:47', 'updated_at' => '2026-06-09 15:39:47'],
            ['id' => 3, 'modulo_id' => 3, 'curso_id' => 1, 'created_at' => '2026-06-09 15:58:09', 'updated_at' => '2026-06-09 15:58:09'],
            ['id' => 4, 'modulo_id' => 4, 'curso_id' => 1, 'created_at' => '2026-06-09 21:32:24', 'updated_at' => '2026-06-09 21:32:24'],
        ];

        DB::table('modulo_curso')->insertOrIgnore($moduloCurso);

        $this->command->info('ModuloSeeder: ' . count($modulos) . ' módulo(s) y ' . count($moduloCurso) . ' relación(es) módulo-curso procesadas.');
    }
}
