<?php

namespace Database\Seeders;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegurar que existan sedes antes de crear áreas
        $sedes = Sede::count();
        if ($sedes < 5) {
            Sede::factory(5)->create();
        }

        // Crear áreas sin relaciones automáticas para evitar duplicados
        Area::factory(8)->withoutSedes()->create();

        // Crear algunas áreas específicas para ejemplos
        Area::factory()
            ->active()
            ->withoutSedes() // Sin relaciones automáticas
            ->create(['nombre' => 'Área de Ventas'])
            ->sedes()->attach([1, 2]); // Asignar sedes específicas después

        Area::factory()
            ->inactive()
            ->withoutSedes() // Sin sedes
            ->create(['nombre' => 'Área Archivada']);

        // Crear algunas áreas con relaciones aleatorias (pero controladas)
        $areas = Area::whereNotIn('nombre', ['Área de Ventas', 'Área Archivada'])->get();
        $sedes = Sede::all();

        foreach ($areas as $area) {
            // Asignar entre 1 y 3 sedes aleatorias a cada área
            $randomSedes = $sedes->random(rand(1, min(3, $sedes->count())));
            $area->sedes()->attach($randomSedes->pluck('id')->toArray());
        }
    }
}
