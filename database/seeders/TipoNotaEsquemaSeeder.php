<?php

namespace Database\Seeders;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\TipoNotaEsquema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoNotaEsquemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Nota: Este seeder crea tipos de nota adicionales para esquemas que no tienen tipos de nota.
     * Los esquemas creados con factory ya incluyen sus tipos de nota.
     */
    public function run(): void
    {
        // Obtener esquemas que no tienen tipos de nota
        $esquemasSinTipos = EsquemaCalificacion::whereDoesntHave('tiposNota')->get();

        foreach ($esquemasSinTipos as $esquema) {
            // Crear tipos de nota básicos para esquemas sin tipos
            $tiposNota = [
                ['nombre_tipo' => 'Parcial 1', 'peso' => 30, 'orden' => 1],
                ['nombre_tipo' => 'Parcial 2', 'peso' => 30, 'orden' => 2],
                ['nombre_tipo' => 'Proyecto Final', 'peso' => 40, 'orden' => 3],
            ];

            foreach ($tiposNota as $tipo) {
                TipoNotaEsquema::create([
                    'esquema_calificacion_id' => $esquema->id,
                    'nombre_tipo' => $tipo['nombre_tipo'],
                    'peso' => $tipo['peso'],
                    'orden' => $tipo['orden'],
                    'nota_minima' => 0,
                    'nota_maxima' => 5,
                ]);
            }
        }

        $this->command->info('Tipos de nota creados para esquemas sin tipos.');
    }
}
