<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Ing Alexander Barajas V',
            'email' => 'alexanderbarajas@gmail.com',
            'documento'=>10215300,
            'password' => Hash::make('10203040'),
            //'rol_id'=>1
        ])->assignRole('superusuario');

        $roles = Role::whereIn('name', ['financiero', 'coordinador', 'profesor','alumno'])->get(); // O roles específicos

        User::factory(10)->create()->each(function ($user) use ($roles) {
            $user->assignRole($roles->random());
        });
    }
}
