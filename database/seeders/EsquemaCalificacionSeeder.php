<?php

namespace Database\Seeders;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EsquemaCalificacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener módulos existentes
        $modulos = Modulo::all();
        $grupos = Grupo::all();

        // Crear esquemas generales por módulo (sin grupo específico)
        foreach ($modulos->take(30) as $modulo) {
            // Crear esquema estándar para el módulo
            EsquemaCalificacion::factory()
                ->activo()
                ->esquemaEstandar()
                ->paraModulo($modulo->id)
                ->create();
        }

        // Crear esquemas específicos por grupo
        foreach ($grupos->take(20) as $grupo) {
            // 50% de probabilidad de crear esquema con participación
            if (rand(0, 1)) {
                EsquemaCalificacion::factory()
                    ->activo()
                    ->esquemaConParticipacion()
                    ->paraGrupo($grupo->id)
                    ->create();
            } else {
                EsquemaCalificacion::factory()
                    ->activo()
                    ->esquemaEstandar()
                    ->paraGrupo($grupo->id)
                    ->create();
            }
        }

        // Crear algunos esquemas intensivos
        foreach ($grupos->skip(20)->take(10) as $grupo) {
            EsquemaCalificacion::factory()
                ->activo()
                ->esquemaIntensivo()
                ->paraGrupo($grupo->id)
                ->create();
        }

        // Crear algunos esquemas inactivos (para pruebas)
        EsquemaCalificacion::factory(5)
            ->inactivo()
            ->esquemaEstandar()
            ->create();

        $this->command->info('Esquemas de calificación creados exitosamente.');
    }
}
