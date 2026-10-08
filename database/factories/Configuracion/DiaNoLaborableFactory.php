<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\DiaNoLaborable>
 */
class DiaNoLaborableFactory extends Factory
{
    protected $model = DiaNoLaborable::class;

    /**
     * Define el estado por defecto: festivo general (todas las sedes) y activo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha'         => $this->faker->unique()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'nombre'        => $this->faker->sentence(3),
            'tipo'          => 'festivo',
            'sede_id'       => null,
            'observaciones' => null,
            'status'        => 1,
        ];
    }
}
