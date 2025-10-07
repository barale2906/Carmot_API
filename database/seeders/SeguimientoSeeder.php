<?php

namespace Database\Seeders;

use App\Models\Crm\Seguimiento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeguimientoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Seguimiento::factory(1000)->create([]);
    }
}
