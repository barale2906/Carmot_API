<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'primer_nombre'    => 'Ing Alexander',
            'segundo_nombre'   => null,
            'primer_apellido'  => 'Barajas',
            'segundo_apellido' => 'V',
            'email'            => 'alexanderbarajas@gmail.com',
            'documento'        => '10215300',
            'password'         => Hash::make('10203040'),
        ])->assignRole('superusuario');

        User::factory()->create([
            'primer_nombre'    => 'Daniel',
            'segundo_nombre'   => null,
            'primer_apellido'  => 'Nastar',
            'segundo_apellido' => null,
            'email'            => 'danielnastar@gmail.com',
            'documento'        => '10215500',
            'password'         => Hash::make('10203040'),
        ])->assignRole('superusuario');

        $roles = Role::whereIn('name', ['financiero', 'coordinador', 'profesor', 'alumno'])->get();
        // User::factory(10)->create()->each(fn ($user) => $user->assignRole($roles->random()));
    }
}
