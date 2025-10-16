<?php

namespace Database\Seeders;

use App\Models\Crm\Referido;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReferidoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Referido::factory(150)->create([
            'status' => 0,
        ]);
    }
}
