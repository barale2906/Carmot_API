<?php

namespace Database\Seeders;

use App\Models\Academico\Programacion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProgramacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear programaciones básicas con grupos del curso
        Programacion::factory(10)
            ->activo()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones en curso
        Programacion::factory(5)
            ->activo()
            ->enCurso()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones por iniciar
        Programacion::factory(3)
            ->activo()
            ->porIniciar()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones finalizadas
        Programacion::factory(2)
            ->finalizada()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones de mañana
        Programacion::factory(4)
            ->activo()
            ->conGruposManana()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones de tarde
        Programacion::factory(4)
            ->activo()
            ->conGruposTarde()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones de noche
        Programacion::factory(3)
            ->activo()
            ->conGruposNoche()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones de fin de semana
        Programacion::factory(2)
            ->activo()
            ->conGruposFinDeSemana()
            ->conGruposDelCurso()
            ->create();

        // Crear programaciones con muchos grupos
        Programacion::factory(2)
            ->activo()
            ->conMuchosGrupos()
            ->create();

        // Crear programaciones con pocos grupos
        Programacion::factory(3)
            ->activo()
            ->conPocosGrupos()
            ->create();

        // Crear algunas programaciones sin grupos para testing
        Programacion::factory(3)
            ->activo()
            ->sinGrupos()
            ->create();
    }
}
