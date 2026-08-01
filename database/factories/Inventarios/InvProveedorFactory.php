<?php

namespace Database\Factories\Inventarios;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvProveedor>
 */
class InvProveedorFactory extends Factory
{
    protected $model = \App\Models\Inventarios\InvProveedor::class;

    public function definition(): array
    {
        return [
            'razon_social' => $this->faker->unique()->company(),
            'nit'          => $this->faker->optional()->numerify('##########-#'),
            'contacto'     => $this->faker->optional()->name(),
            'telefono'     => $this->faker->optional()->phoneNumber(),
            'email'        => $this->faker->optional()->companyEmail(),
            'direccion'    => $this->faker->optional()->address(),
            'notas'        => null,
            'status'       => 1,
        ];
    }

    public function activo(): static
    {
        return $this->state(['status' => 1]);
    }

    public function inactivo(): static
    {
        return $this->state(['status' => 0]);
    }
}
