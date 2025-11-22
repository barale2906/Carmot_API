<?php

namespace Database\Seeders;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MatriculaSeeder extends Seeder
{
    /**
     * Ejecuta los seeders de la base de datos.
     *
     * Nota: Los contadores de inscritos en ciclos y grupos se actualizan automáticamente
     * mediante eventos del modelo Matricula cuando se crean matrículas activas (status = 1).
     */
    public function run(): void
    {
        // Verificar que existan los datos necesarios
        if (Curso::count() === 0) {
            $this->command->warn('No hay cursos disponibles. Por favor ejecuta CursoSeeder primero.');
            return;
        }

        if (Ciclo::count() === 0) {
            $this->command->warn('No hay ciclos disponibles. Por favor ejecuta CicloSeeder primero.');
            return;
        }

        if (User::count() === 0) {
            $this->command->warn('No hay usuarios disponibles. Por favor ejecuta UserSeeder primero.');
            return;
        }

        // Obtener datos existentes
        $cursos = Curso::all();
        $ciclos = Ciclo::all();
        $usuarios = User::all();

        // Crear matrículas activas (mayoría)
        Matricula::factory(50)
            ->activa()
            ->create();

        // Crear matrículas inactivas
        Matricula::factory(10)
            ->inactiva()
            ->create();

        // Crear matrículas anuladas
        Matricula::factory(5)
            ->anulada()
            ->create();

        // Crear matrículas recientes (último mes)
        Matricula::factory(15)
            ->activa()
            ->reciente()
            ->create();

        // Crear matrículas futuras (próximos meses)
        Matricula::factory(10)
            ->activa()
            ->futura()
            ->create();

        // Crear matrículas con montos específicos para pruebas
        Matricula::factory(5)
            ->activa()
            ->conMonto(1000000)
            ->create();

        Matricula::factory(5)
            ->activa()
            ->conMonto(2500000)
            ->create();

        // Crear matrículas distribuidas por curso
        foreach ($cursos->take(3) as $curso) {
            Matricula::factory(10)
                ->activa()
                ->conCurso($curso->id)
                ->create();
        }

        // Crear matrículas distribuidas por ciclo
        foreach ($ciclos->take(5) as $ciclo) {
            Matricula::factory(8)
                ->activa()
                ->conCiclo($ciclo->id)
                ->create();
        }

        $this->command->info('Matrículas creadas exitosamente.');
    }
}
