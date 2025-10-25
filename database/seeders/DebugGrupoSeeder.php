<?php

namespace Database\Seeders;

use App\Models\Academico\Grupo;
use App\Models\Configuracion\Area;
use Illuminate\Database\Seeder;

class DebugGrupoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Iniciando debug de grupos con horarios...');

        // Verificar que existan áreas
        if (Area::count() === 0) {
            $this->command->warn('No hay áreas disponibles. Creando área de prueba...');
            Area::factory(1)->create();
        }

        $this->command->info('Áreas disponibles: ' . Area::count());

        try {
            // Crear un grupo simple primero
            $grupo = Grupo::create([
                'sede_id' => 1, // Asumiendo que existe
                'modulo_id' => 1, // Asumiendo que existe
                'profesor_id' => 1, // Asumiendo que existe
                'nombre' => 'Grupo Debug Simple',
                'inscritos' => 20,
                'jornada' => 0,
                'status' => 1,
            ]);

            $this->command->info("Grupo creado: {$grupo->nombre} (ID: {$grupo->id})");

            // Crear horario manualmente
            $area = Area::first();
            $this->command->info("Usando área: {$area->nombre} (ID: {$area->id})");

            $horario = \App\Models\Configuracion\Horario::create([
                'sede_id' => $grupo->sede_id,
                'area_id' => $area->id,
                'grupo_id' => $grupo->id,
                'grupo_nombre' => $grupo->nombre,
                'tipo' => false,
                'periodo' => true,
                'dia' => 'lunes',
                'hora' => '08:00',
                'duracion_horas' => 2,
                'status' => 1,
            ]);

            $this->command->info("Horario creado: {$horario->dia} {$horario->hora} ({$horario->duracion_horas}h)");

            // Verificar la relación
            $horariosCount = $grupo->horarios()->count();
            $this->command->info("Horarios del grupo: {$horariosCount}");

        } catch (\Exception $e) {
            $this->command->error("Error: " . $e->getMessage());
            $this->command->error("Trace: " . $e->getTraceAsString());
        }
    }
}
