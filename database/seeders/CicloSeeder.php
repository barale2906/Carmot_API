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
        Ciclo::factory(20)->create([
            'status' => 1,
        ]);
    }
}
