<?php

namespace Database\Seeders;

use App\Models\Configuracion\Poblacion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class poblacionstatuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Poblacion::whereIn('pais', ['Colombia','Perú','Ecuador','Venezuela'])->update(['status' => 1]);
    }
}
