<?php

namespace Database\Seeders;

use App\Models\Academico\TipoAplazamiento;
use Illuminate\Database\Seeder;

class TipoAplazamientoSeeder extends Seeder
{
    /**
     * Carga los tipos de aplazamiento predefinidos del sistema.
     */
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Demanda incompleta',       'descripcion' => 'No hay suficientes estudiantes inscritos para iniciar o continuar el ciclo.'],
            ['nombre' => 'Renuncia del profesor',    'descripcion' => 'El docente asignado renuncia y se debe buscar un reemplazo.'],
            ['nombre' => 'Enfermedad del profesor',  'descripcion' => 'El docente asignado se encuentra en incapacidad médica temporal.'],
            ['nombre' => 'Alistamiento / licencia',  'descripcion' => 'El docente asignado debe cumplir obligaciones militares, cívicas o de licencia reglamentaria.'],
            ['nombre' => 'Obras locativas',          'descripcion' => 'La sede requiere adecuaciones físicas que impiden el normal desarrollo de las clases.'],
            ['nombre' => 'Fuerza mayor',             'descripcion' => 'Eventos imprevistos e irresistibles (desastres naturales, emergencias sanitarias, orden público, etc.).'],
            ['nombre' => 'Suspensión administrativa','descripcion' => 'Decisión administrativa interna que requiere pausar el ciclo temporalmente.'],
        ];

        foreach ($tipos as $tipo) {
            TipoAplazamiento::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                array_merge($tipo, ['status' => 1])
            );
        }
    }
}
