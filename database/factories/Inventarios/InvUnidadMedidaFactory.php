<?php

namespace Database\Factories\Inventarios;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvUnidadMedida>
 */
class InvUnidadMedidaFactory extends Factory
{
    protected $model = \App\Models\Inventarios\InvUnidadMedida::class;

    public function definition(): array
    {
        $unidades = [
            ['nombre' => 'Unidad',  'abreviatura' => 'UND'],
            ['nombre' => 'Par',     'abreviatura' => 'PAR'],
            ['nombre' => 'Docena',  'abreviatura' => 'DOC'],
            ['nombre' => 'Caja',    'abreviatura' => 'CJA'],
            ['nombre' => 'Resma',   'abreviatura' => 'RSM'],
            ['nombre' => 'Paquete', 'abreviatura' => 'PQT'],
        ];

        $choice = $this->faker->unique()->randomElement($unidades);

        return [
            'nombre'      => $choice['nombre'] . ' ' . $this->faker->word(),
            'abreviatura' => $choice['abreviatura'],
            'status'      => $this->faker->randomElement([0, 1]),
        ];
    }

    public function activa(): static
    {
        return $this->state(['status' => 1]);
    }

    public function inactiva(): static
    {
        return $this->state(['status' => 0]);
    }
}
