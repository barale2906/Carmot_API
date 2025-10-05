<?php

namespace Database\Seeders;

use App\Models\Academico\Curso;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CursoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Curso::factory(3)->create([
            'status' => 1,
        ]);
    }
}
