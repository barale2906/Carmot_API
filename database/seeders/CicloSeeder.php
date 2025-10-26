<?php

namespace Database\Seeders;

use App\Models\Academico\Ciclo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CicloSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear ciclos básicos
        Ciclo::factory(10)
            ->activo()
            ->conCalculoAutomatico()
            ->conGruposAleatorios(2)
            ->create();

        // Crear ciclos en curso
        Ciclo::factory(5)
            ->activo()
            ->enCurso()
            ->conCalculoAutomatico()
            ->conGruposAleatorios(3)
            ->create();

        // Crear ciclos por iniciar
        Ciclo::factory(3)
            ->activo()
            ->porIniciar()
            ->conCalculoAutomatico()
            ->conGruposAleatorios(2)
            ->create();

        // Crear ciclos finalizados
        Ciclo::factory(2)
            ->finalizado()
            ->conCalculoManual()
            ->create();

        // Crear ciclos completos con cronograma realista
        Ciclo::factory(3)
            ->activo()
            ->conCronogramaRealista()
            ->create();

        // Crear ciclos de verano
        Ciclo::factory(2)
            ->deVerano()
            ->activo()
            ->conCalculoAutomatico()
            ->conGruposAleatorios(2)
            ->create();

        // Crear ciclos de invierno
        Ciclo::factory(2)
            ->deInvierno()
            ->activo()
            ->conCalculoAutomatico()
            ->conGruposAleatorios(3)
            ->create();

        // Crear ciclos intensivos
        Ciclo::factory(2)
            ->intensivo()
            ->activo()
            ->conCalculoAutomatico()
            ->conGruposAleatorios(4)
            ->create();

        // Crear ciclos especiales
        Ciclo::factory(1)
            ->especial()
            ->activo()
            ->conCalculoManual()
            ->conGruposAleatorios(2)
            ->create();

        // Crear algunos ciclos sin grupos para testing
        Ciclo::factory(3)
            ->activo()
            ->sinGrupos()
            ->conCalculoAutomatico()
            ->create();
    }
}
