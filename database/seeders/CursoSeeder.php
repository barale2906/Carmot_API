<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder CursoSeeder
 *
 * Siembra los cursos reales del instituto. Usa insertOrIgnore para ser
 * idempotente: si el registro ya existe (mismo id) lo salta sin error.
 */
class CursoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $now = now();

        $cursos = [
            [
                'id'         => 1,
                'nombre'     => 'TÉCNICO - MECÁNICA DE VEHICULOS AUTOMOTORES',
                'duracion'   => 240,
                'tipo'       => 1,
                'status'     => 1,
                'created_at' => '2026-06-02 20:49:00',
                'updated_at' => '2026-06-09 21:49:09',
                'deleted_at' => null,
            ],
        ];

        DB::table('cursos')->insertOrIgnore($cursos);

        $this->command->info('CursoSeeder: ' . count($cursos) . ' curso(s) procesado(s).');
    }
}
