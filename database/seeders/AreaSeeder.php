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

        // Crear áreas (las relaciones se crean automáticamente en el factory)
        Area::factory(10)->create();

        // Crear algunas áreas específicas para ejemplos
        Area::factory()
            ->active()
            ->withSedes([1, 2]) // Con sedes específicas
            ->create(['nombre' => 'Área de Ventas']);

        Area::factory()
            ->inactive()
            ->withoutSedes() // Sin sedes
            ->create(['nombre' => 'Área Archivada']);
    }
}
