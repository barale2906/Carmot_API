<?php

namespace Database\Seeders;

use App\Models\Academico\Grupo;
use App\Models\Configuracion\Area;
use Illuminate\Database\Seeder;

class TestGrupoHorariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar que existan áreas
        if (Area::count() === 0) {
            $this->command->warn('No hay áreas disponibles. Creando área de prueba...');
            Area::factory(3)->create();
        }

        $this->command->info('Creando grupos de prueba con horarios...');

        // Crear un grupo con horarios
        $grupo = Grupo::factory()->conHorarios()->create([
            'nombre' => 'Grupo de Prueba con Horarios',
            'status' => 1,
        ]);

        $this->command->info("Grupo creado: {$grupo->nombre}");
        $this->command->info("Horarios asignados: {$grupo->horarios()->count()}");
        
        // Mostrar detalles de los horarios
        foreach ($grupo->horarios as $horario) {
            $this->command->info("- {$horario->dia} {$horario->hora->format('H:i')} ({$horario->duracion_horas}h) - Área: {$horario->area->nombre}");
        }

        // Crear un grupo de mañana
        $grupoManana = Grupo::factory()->manana()->conHorariosManana()->create([
            'nombre' => 'Grupo Mañana con Horarios',
            'status' => 1,
        ]);

        $this->command->info("Grupo mañana creado: {$grupoManana->nombre}");
        $this->command->info("Horarios asignados: {$grupoManana->horarios()->count()}");
    }
}
