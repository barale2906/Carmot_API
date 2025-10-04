<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superusuario = User::factory()->create([
            'name' => 'Ing Alexander Barajas V',
            'email' => 'alexanderbarajas@gmail.com',
            'documento'=>10215300,
            'password'=>bcrypt('10203040'),
            //'rol_id'=>1
        ])->assignRole('superusuario');
    }
}
