<?php

namespace Database\Seeders;

use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class GrupoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear grupos sin horarios (20%)
        Grupo::factory(16)->create([
            'status' => 1,
        ]);

        // Crear grupos con horarios aleatorios (30%)
        Grupo::factory(24)->conHorarios()->create([
            'status' => 1,
        ]);

        // Crear grupos de mañana con horarios específicos (15%)
        Grupo::factory(12)->manana()->conHorariosManana()->create([
            'status' => 1,
        ]);

        // Crear grupos de tarde con horarios específicos (15%)
        Grupo::factory(12)->tarde()->conHorariosTarde()->create([
            'status' => 1,
        ]);

        // Crear grupos de noche con horarios específicos (10%)
        Grupo::factory(8)->noche()->conHorariosNoche()->create([
            'status' => 1,
        ]);

        // Crear grupos de fin de semana con horarios específicos (5%)
        Grupo::factory(4)->finDeSemana()->conHorariosFinSemana()->create([
            'status' => 1,
        ]);

        // Crear grupos intensivos (5%)
        Grupo::factory(4)->conHorariosIntensivos()->create([
            'status' => 1,
        ]);
    }
}
